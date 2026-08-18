<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_REST {

	public function register_routes() {
		register_rest_route( 'poian-quiz/v1', '/submit', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'submit' ),
		) );
		register_rest_route( 'poian-quiz/v1', '/retake', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'retake' ),
		) );
	}

	public function retake( WP_REST_Request $request ) {
		$form_id = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;
		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			return new WP_Error( 'no_form', __( 'فرم یافت نشد.', 'poian-quiz' ), array( 'status' => 404 ) );
		}
		$settings = Poian_Quiz_Forms::effective_settings( $form_id );
		if ( 1 === (int) $settings['require_login'] && ! is_user_logged_in() ) {
			return new WP_Error( 'login_required', __( 'برای این فرم باید وارد شوید.', 'poian-quiz' ), array( 'status' => 403 ) );
		}
		if ( ! Poian_Quiz_Security::verify_submit_nonce( $request->get_header( 'X-Poian-Nonce' ) ) ) {
			return new WP_Error( 'bad_nonce', __( 'توکن امنیتی نامعتبر است.', 'poian-quiz' ), array( 'status' => 403 ) );
		}
		$actor   = Poian_Quiz_Security::actor_key();
		$allowed = Poian_Quiz_Rate_Limiter::check( $actor, $form_id, $settings );
		if ( is_wp_error( $allowed ) ) { return $allowed; }
		if ( (int) $settings['max_total'] > 0 && Poian_Quiz_Repository::count_by_actor( $form_id, $actor ) >= (int) $settings['max_total'] ) {
			return new WP_Error( 'total_limit', __( 'سهمیه شما به پایان رسیده است.', 'poian-quiz' ), array( 'status' => 429 ) );
		}
		set_transient( 'poian_quiz_retaking_' . $form_id . '_' . md5( $actor ), 1, 10 * MINUTE_IN_SECONDS );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function submit( WP_REST_Request $request ) {
		$form_id = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;
		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			return new WP_Error( 'no_form', __( 'فرم یافت نشد.', 'poian-quiz' ), array( 'status' => 404 ) );
		}

		$settings = Poian_Quiz_Forms::effective_settings( $form_id );

		if ( 1 === (int) $settings['require_login'] && ! is_user_logged_in() ) {
			return new WP_Error( 'login_required', __( 'برای این فرم باید وارد شوید.', 'poian-quiz' ), array( 'status' => 403 ) );
		}

		if ( ! Poian_Quiz_Security::verify_submit_nonce( $request->get_header( 'X-Poian-Nonce' ) ) ) {
			return new WP_Error( 'bad_nonce', __( 'توکن امنیتی نامعتبر است.', 'poian-quiz' ), array( 'status' => 403 ) );
		}

		$actor = Poian_Quiz_Security::actor_key();

		$allowed = Poian_Quiz_Rate_Limiter::check( $actor, $form_id, $settings );
		if ( is_wp_error( $allowed ) ) { return $allowed; }

		if ( (int) $settings['max_total'] > 0 && Poian_Quiz_Repository::count_by_actor( $form_id, $actor ) >= (int) $settings['max_total'] ) {
			return new WP_Error( 'total_limit', __( 'سهمیه کل آزمون‌های شما به پایان رسیده است.', 'poian-quiz' ), array( 'status' => 429 ) );
		}

		$engine = Poian_Quiz_Engine_Registry::get( Poian_Quiz_Forms::get_engine_id( $form_id ) );
		if ( ! $engine ) {
			return new WP_Error( 'no_engine', __( 'موتور فرم معتبر نیست.', 'poian-quiz' ), array( 'status' => 400 ) );
		}

		$schema = Poian_Quiz_Forms::get_schema( $form_id );
		$json   = $request->get_json_params();
		$raw    = ( is_array( $json ) && isset( $json['answers'] ) && is_array( $json['answers'] ) ) ? $json['answers'] : array();

		$answers = Poian_Quiz_Schema::validate_submission( $schema, $raw );
		if ( is_wp_error( $answers ) ) { return $answers; }

		$result = $engine->compute( $answers, $schema );

		// متاهای وایت‌لیست‌شده (فقط لاگین)
		$meta_writes = array();
		$actions     = Poian_Quiz_Forms::get_actions( $form_id );
		$user_id     = get_current_user_id();
		if ( $user_id && ! empty( $actions['meta'] ) && is_array( $actions['meta'] ) ) {
			$declared = array();
			foreach ( $actions['meta'] as $m ) { if ( isset( $m['key'] ) ) { $declared[] = (string) $m['key']; } }
			foreach ( $actions['meta'] as $m ) {
				$key = isset( $m['key'] ) ? (string) $m['key'] : '';
				if ( ! Poian_Quiz_Security::is_meta_key_allowed( $key, $declared ) ) {
					continue; // لایه امنیتی: رد بی‌صدا
				}
				$val = $this->resolve_source( isset( $m['source'] ) ? (string) $m['source'] : '', $result, $answers, $user_id );
				if ( null !== $val ) { $meta_writes[ $key ] = $val; }
			}
		}

		$attempt_id = Poian_Quiz_Repository::submit_transactional(
			array(
				'form_id'      => $form_id,
				'form_version' => Poian_Quiz_Forms::get_current_version( $form_id ),
				'user_id'      => $user_id,
				'actor_key'    => $actor,
				'mobile'       => $user_id ? Poian_Quiz_Security::sanitize_mobile( apply_filters( 'poian_quiz_user_mobile', get_user_meta( $user_id, 'jay_mobile', true ), $user_id ) ) : '',
				'display_name' => $user_id ? (string) wp_get_current_user()->display_name : '',
				'answers'      => $answers,
				'result'       => $result,
				'result_slug'  => isset( $result['result_slug'] ) ? $result['result_slug'] : '',
				'result_label' => isset( $result['result_label'] ) ? $result['result_label'] : '',
			),
			isset( $result['scores'] ) && is_array( $result['scores'] ) ? $result['scores'] : array(),
			$meta_writes
		);
		if ( is_wp_error( $attempt_id ) ) { return $attempt_id; }

		Poian_Quiz_Rate_Limiter::record( $actor, $form_id, $settings );
		delete_transient( 'poian_quiz_retaking_' . $form_id . '_' . md5( $actor ) );
		do_action( 'poian_quiz_after_submit', $attempt_id, $form_id, $result, $answers, $user_id );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => array(
				'attempt_id'  => $attempt_id,
				'result'      => $result,
				'show_result' => ( 'none' !== Poian_Quiz_Forms::get_engine_id( $form_id ) ),
				'redirect'    => isset( $actions['redirect'] ) ? (string) $actions['redirect'] : '',
			),
		) );
		
	    
	}

	private function resolve_source( $source, array $result, array $answers, $user_id ) {
		if ( '' === $source ) { return null; }
		if ( 'result_label' === $source ) { return isset( $result['result_label'] ) ? $result['result_label'] : ''; }
		if ( 'result_slug' === $source ) { return isset( $result['result_slug'] ) ? $result['result_slug'] : ''; }
		if ( 0 === strpos( $source, 'score:' ) ) {
			$dim = substr( $source, 6 );
			return isset( $result['scores'][ $dim ] ) ? $result['scores'][ $dim ] : null;
		}
		if ( 0 === strpos( $source, 'answer:' ) ) {
			$fid = substr( $source, 7 );
			return isset( $answers[ $fid ] ) ? ( is_array( $answers[ $fid ] ) ? implode( ',', $answers[ $fid ] ) : $answers[ $fid ] ) : null;
		}
		if ( 'mobile' === $source ) { return get_user_meta( $user_id, 'jay_mobile', true ); }
		if ( 'display_name' === $source ) { return wp_get_current_user()->display_name; }
		return null;
	}
}
