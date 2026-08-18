<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Admin_Form_Editor {

	public function register() {
		add_action( 'admin_post_poian_quiz_save_form', array( $this, 'save' ) );
		add_action( 'admin_post_poian_quiz_delete_form', array( $this, 'delete_form' ) );
	}

	public function delete_form() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$form_id = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
		check_admin_referer( 'poian_quiz_delete_' . $form_id );
		if ( $form_id ) { wp_delete_post( $form_id, false ); }
		wp_safe_redirect( add_query_arg( array( 'page' => Poian_Quiz_Admin::MAIN_SLUG, 'pq_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		check_admin_referer( 'poian_quiz_save_form' );

		// ۱) دریافت form_id و engine
		$form_id   = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$engine_id = isset( $_POST['pq_engine'] ) ? sanitize_key( wp_unslash( $_POST['pq_engine'] ) ) : 'none';

		if ( ! Poian_Quiz_Engine_Registry::get( $engine_id ) ) {
			wp_die( esc_html__( 'موتور نامعتبر.', 'poian-quiz' ) );
		}

		// ۲) Parse و sanitize schema
		$schema_raw = json_decode( isset( $_POST['pq_schema_json'] ) ? wp_unslash( $_POST['pq_schema_json'] ) : '', true );
		if ( ! is_array( $schema_raw ) ) {
			wp_die( esc_html__( 'اسکیمای فرم نامعتبر است.', 'poian-quiz' ) );
		}
		$schema  = Poian_Quiz_Schema::sanitize_schema( $schema_raw );
		$actions = Poian_Quiz_Schema::sanitize_actions( json_decode( isset( $_POST['pq_actions_json'] ) ? wp_unslash( $_POST['pq_actions_json'] ) : '', true ) ?: array() );
		$settings = $this->read_form_settings( $form_id );
		// ۳) ساخت یا به‌روزرسانی فرم
		if ( ! $form_id ) {
			$form_id = Poian_Quiz_Forms::create(
				isset( $schema['title'] ) && $schema['title'] ? $schema['title'] : __( 'فرم جدید', 'poian-quiz' ),
				$engine_id
			);
			if ( is_wp_error( $form_id ) || ! $form_id ) {
				wp_die( esc_html__( 'خطا در ساخت فرم.', 'poian-quiz' ) );
			}
		} else {
			wp_update_post( array( 'ID' => $form_id, 'post_title' => isset( $schema['title'] ) ? $schema['title'] : '' ) );
			Poian_Quiz_Forms::update_schema( $form_id, $schema );
		}

		// ۴) ذخیره متاها
		update_post_meta( $form_id, Poian_Quiz_Forms::META_ENGINE, $engine_id );
		update_post_meta( $form_id, Poian_Quiz_Forms::META_ACTIONS, $actions );
		update_post_meta( $form_id, Poian_Quiz_Forms::META_SETTINGS, $settings );

		// ۵) ذخیره engine_config (مولفه‌ها + شخصیت‌ها) به صورت یکپارچه
		$engine_config_raw = json_decode( isset( $_POST['pq_engine_config_json'] ) ? wp_unslash( $_POST['pq_engine_config_json'] ) : '', true );
		$sanitized_config = $this->sanitize_engine_config_unified( $engine_config_raw );
		Poian_Quiz_Forms::save_engine_config( $form_id, $sanitized_config );

		// ۶) اگر نسخه قبلی وجود نداشت، یک snapshot بساز
		if ( ! get_post_meta( $form_id, Poian_Quiz_Forms::META_VERSION, true ) ) {
			Poian_Quiz_Forms::update_schema( $form_id, $schema );
		}

		wp_safe_redirect( add_query_arg( array(
			'page'      => Poian_Quiz_Admin::MAIN_SLUG,
			'view'      => 'editor',
			'form_id'   => $form_id,
			'pq_notice' => 'saved',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Sanitize یکپارچه engine_config شامل dimensions و personalities.
	 * این متد جایگزین sanitize_engine_config قدیمی می‌شود.
	 */
	private function sanitize_engine_config_unified( $raw ) {
		$out = array(
			'dimensions'    => array(),
			'personalities' => array(),
		);

		if ( ! is_array( $raw ) ) { return $out; }

		// dimensions: کلید => نام فارسی
		if ( isset( $raw['dimensions'] ) && is_array( $raw['dimensions'] ) ) {
			foreach ( $raw['dimensions'] as $key => $val ) {
				$s_key = sanitize_key( $key );
				if ( '' === $s_key ) { continue; }
				$out['dimensions'][ $s_key ] = sanitize_text_field( (string) $val );
			}
		}

		// personalities: slug => {title, content}
		if ( isset( $raw['personalities'] ) && is_array( $raw['personalities'] ) ) {
			foreach ( $raw['personalities'] as $slug => $p ) {
				if ( ! preg_match( '/^[a-z0-9]+_[a-z0-9]+$/', (string) $slug ) || ! is_array( $p ) ) { continue; }

				$clean = array();

				// عنوان شخصیت
				if ( isset( $p['title'] ) && '' !== (string) $p['title'] ) {
					$clean['title'] = sanitize_text_field( (string) $p['title'] );
				}

				// 🆕 ساختار جدید: content (HTML با wp_kses_post)
				if ( isset( $p['content'] ) && '' !== (string) $p['content'] ) {
					$clean['content'] = wp_kses_post( (string) $p['content'] );
				}

				// ساختار قدیمی: texts (backward compatibility)
				if ( isset( $p['texts'] ) && is_array( $p['texts'] ) ) {
					$texts = array();
					foreach ( array( 'character', 'treasure', 'compass', 'call' ) as $k ) {
						if ( isset( $p['texts'][ $k ] ) && '' !== (string) $p['texts'][ $k ] ) {
							$texts[ $k ] = sanitize_textarea_field( (string) $p['texts'][ $k ] );
						}
					}
					if ( $texts ) { $clean['texts'] = $texts; }
				}

				if ( $clean ) { $out['personalities'][ $slug ] = $clean; }
			}
		}

		return $out;
	}
	private function read_form_settings( $form_id = 0 ) {
		// دریافت تنظیمات فعلی فرم برای حفظ مقادیری که تغییر نکرده‌اند
		$current = array();
		if ( $form_id > 0 ) {
			$current = get_post_meta( $form_id, Poian_Quiz_Forms::META_SETTINGS, true );
			if ( ! is_array( $current ) ) { $current = array(); }
		}
		
		$out = array();
		$map = array(
			'require_login'    => array( '0', '1' ),
			'cooldown_minutes' => 'int',
			'max_per_day'      => 'int',
			'max_total'        => 'int',
			'display_mode'     => array( 'all', 'single' ),
			'show_progress'    => array( '0', '1' ),
			'retake_mode'      => array( 'cooldown', 'unlimited', 'once' ),
			'history_count'    => array( '0', '5', '10', '-1' ),
		);
		foreach ( $map as $key => $rule ) {
			$raw = isset( $_POST[ 'fs_' . $key ] ) ? wp_unslash( $_POST[ 'fs_' . $key ] ) : null;
			if ( null === $raw || '' === $raw ) {
				// اگر مقدار ارسال نشده، از تنظیمات فعلی استفاده کن یا 'inherit'
				$out[ $key ] = isset( $current[ $key ] ) ? $current[ $key ] : 'inherit';
				continue;
			}
			if ( 'inherit' === $raw ) { $out[ $key ] = 'inherit'; continue; }
			if ( is_array( $rule ) ) { $out[ $key ] = in_array( $raw, $rule, true ) ? $raw : 'inherit'; }
			else { $out[ $key ] = max( 0, absint( $raw ) ); }
		}
		return $out;
	}

	public function render_page() {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$pk_form_id = ( $form_id && Poian_Quiz_Forms::is_valid_form( $form_id ) ) ? $form_id : 0;

		$pk_schema   = $pk_form_id ? Poian_Quiz_Forms::get_schema( $pk_form_id ) : array( 'title' => '', 'fields' => array() );
		$pk_actions  = $pk_form_id ? Poian_Quiz_Forms::get_actions( $pk_form_id ) : array();
		$pk_settings = $pk_form_id ? get_post_meta( $pk_form_id, Poian_Quiz_Forms::META_SETTINGS, true ) : array();
		$pk_engine   = $pk_form_id ? Poian_Quiz_Forms::get_engine_id( $pk_form_id ) : 'none';
		$pk_engine_config = $pk_form_id ? Poian_Quiz_Forms::get_engine_config( $pk_form_id ) : array();
		if ( ! is_array( $pk_engine_config ) ) { $pk_engine_config = array(); }

		$pk_engines = array();
		foreach ( Poian_Quiz_Engine_Registry::all() as $e ) {
			$pk_engines[] = array( 'id' => $e->get_id(), 'title' => $e->get_title(), 'weights' => $e->supports_option_weights() );
		}

		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-form-editor.php';
	}
}
