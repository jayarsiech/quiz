<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Activator {

	public static function activate() {
		self::create_tables();
		self::add_caps();
		self::add_default_settings();
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		/*
		 * attempts: ردیف سبک و ثابت؛ answers_json بدون ایندکس (فقط خوان PK).
		 * ایندکس مرکب‌ها برای اینباکس/آخرین نتیجه/تاریخچه مهمان.
		 */
		$sql_attempts = "CREATE TABLE {$wpdb->prefix}poian_quiz_attempts (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			form_version INT UNSIGNED NOT NULL DEFAULT 1,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			actor_key VARCHAR(64) NOT NULL DEFAULT '',
			mobile VARCHAR(20) NOT NULL DEFAULT '',
			display_name VARCHAR(150) NOT NULL DEFAULT '',
			answers_json LONGTEXT NOT NULL,
			result_json LONGTEXT NULL,
			result_slug VARCHAR(60) NOT NULL DEFAULT '',
			result_label VARCHAR(150) NOT NULL DEFAULT '',
			status TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY form_user_status_created (form_id,user_id,status,created_at),
			KEY actor_form_status (actor_key,form_id,status),
			KEY created_at (created_at)
		) {$charset};";

		/*
		 * scores: جدول تحلیلی ایندکس‌دار — فیلتر میلیونی بدون اسکن JSON.
		 */
		$sql_scores = "CREATE TABLE {$wpdb->prefix}poian_quiz_scores (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attempt_id BIGINT(20) UNSIGNED NOT NULL,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			actor_key VARCHAR(64) NOT NULL DEFAULT '',
			dim_key VARCHAR(60) NOT NULL DEFAULT '',
			dim_value DECIMAL(10,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY form_dim_value (form_id,dim_key,dim_value),
			KEY user_form (user_id,form_id),
			KEY attempt_id (attempt_id)
		) {$charset};";

		/*
		 * form_versions: اسنپ‌شات اسکیما per نسخه → ثبت‌های قدیمی پس از ویرایش فرم درست می‌مانند.
		 */
		$sql_versions = "CREATE TABLE {$wpdb->prefix}poian_quiz_form_versions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			version INT UNSIGNED NOT NULL,
			schema_json LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY form_version (form_id,version)
		) {$charset};";

		dbDelta( $sql_attempts );
		dbDelta( $sql_scores );
		dbDelta( $sql_versions );

		update_option( 'poian_quiz_db_version', POIAN_QUIZ_VERSION, 'no' );
	}

	private static function add_caps() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( POIAN_QUIZ_CAP ) ) {
			$role->add_cap( POIAN_QUIZ_CAP );
		}
	}

	private static function add_default_settings() {
		if ( false === get_option( POIAN_QUIZ_OPTION_KEY, false ) ) {
			add_option( POIAN_QUIZ_OPTION_KEY, self::default_settings(), '', 'no' );
		}
	}

	public static function default_settings() {
		return array(
			'require_login'       => 1,    // پیش‌فرض: نیاز به ورود (per-فرم قابل اوراید)
			'cooldown_minutes'    => 0,    // 0 = بدون فاصله
			'max_per_day'         => 0,    // 0 = نامحدود
			'max_total'           => 0,    // 0 = نامحدود
			'display_mode'        => 'all', // all | single
			'show_progress'       => 0,
			'delete_on_uninstall' => 0,
			'retake_mode'         => 'cooldown', // cooldown | unlimited | once
			'history_count'       => 0,    // 0 = خاموش، 5، 10، -1 = همه
		);
	}
}
