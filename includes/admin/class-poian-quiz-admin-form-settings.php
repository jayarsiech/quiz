<?php
defined( 'ABSPATH' ) || exit;

/**
 * مدیریت تنظیمات اختصاصی هر فرم (جدا از تنظیمات کلی).
 */
final class Poian_Quiz_Admin_Form_Settings {

	public function register() {
		add_action( 'admin_post_poian_quiz_save_form_settings', array( $this, 'save' ) );
	}

	/**
	 * ذخیره تنظیمات فرم.
	 */
	public function save() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		check_admin_referer( 'poian_quiz_save_form_settings_' . $form_id );

		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			wp_die( esc_html__( 'فرم نامعتبر.', 'poian-quiz' ) );
		}

		$settings = $this->sanitize_settings( wp_unslash( $_POST ) );
		update_post_meta( $form_id, Poian_Quiz_Forms::META_SETTINGS, $settings );

		wp_safe_redirect( add_query_arg( array(
			'page'      => Poian_Quiz_Admin::MAIN_SLUG,
			'view'      => 'settings',
			'form_id'   => $form_id,
			'pq_notice' => 'saved',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Sanitize تنظیمات فرم.
	 */
	private function sanitize_settings( array $input ) {
		$out = array();

		// نیاز ورود
		if ( isset( $input['fs_require_login'] ) ) {
			$val = $input['fs_require_login'];
			$out['require_login'] = ( 'inherit' === $val || '' === $val ) ? 'inherit' : ( in_array( $val, array( '0', '1' ), true ) ? $val : 'inherit' );
		}

		// cooldown
		if ( isset( $input['fs_cooldown_minutes'] ) ) {
			$val = $input['fs_cooldown_minutes'];
			$out['cooldown_minutes'] = ( 'inherit' === $val || '' === $val ) ? 'inherit' : max( 0, absint( $val ) );
		}

		// سقف روزانه
		if ( isset( $input['fs_max_per_day'] ) ) {
			$val = $input['fs_max_per_day'];
			$out['max_per_day'] = ( 'inherit' === $val || '' === $val ) ? 'inherit' : max( 0, absint( $val ) );
		}

		// سقف کل
		if ( isset( $input['fs_max_total'] ) ) {
			$val = $input['fs_max_total'];
			$out['max_total'] = ( 'inherit' === $val || '' === $val ) ? 'inherit' : max( 0, absint( $val ) );
		}

		// سیاست آزمون مجدد
		if ( isset( $input['fs_retake_mode'] ) ) {
			$val = $input['fs_retake_mode'];
			$allowed = array( 'inherit', 'cooldown', 'unlimited', 'once' );
			$out['retake_mode'] = in_array( $val, $allowed, true ) ? $val : 'inherit';
		}

		// نمایش تاریخچه
		if ( isset( $input['fs_history_count'] ) ) {
			$val = $input['fs_history_count'];
			$allowed = array( 'inherit', '0', '5', '10', '-1' );
			$out['history_count'] = in_array( $val, $allowed, true ) ? $val : 'inherit';
		}

		return $out;
	}

	/**
	 * رندر صفحه تنظیمات فرم.
	 */
	public function render_page( $form_id ) {
		if ( ! Poian_Quiz_Security::user_can_manage() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 );
		}

		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			wp_die( esc_html__( 'فرم نامعتبر.', 'poian-quiz' ) );
		}

		$pk_form   = get_post( $form_id );
		$pk_notice = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
		$pk_raw    = get_post_meta( $form_id, Poian_Quiz_Forms::META_SETTINGS, true );
		if ( ! is_array( $pk_raw ) ) { $pk_raw = array(); }
		$pk_effective = Poian_Quiz_Forms::effective_settings( $form_id );
		$pk_global    = Poian_Quiz_Settings::all();
		$pk_active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-form-settings.php';
	}
}
