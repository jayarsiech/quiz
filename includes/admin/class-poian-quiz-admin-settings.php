<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Admin_Settings {

	public function register() {
		add_action( 'admin_post_poian_quiz_save_settings', array( $this, 'save' ) );
	}

	public function save() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		check_admin_referer( 'poian_quiz_save_settings' );
		Poian_Quiz_Settings::update( wp_unslash( $_POST ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'poian-quiz-settings', 'pq_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$pk_s = Poian_Quiz_Settings::all();
		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-settings.php';
	}
}
