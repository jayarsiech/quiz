<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		// repair cap در plugins_loaded (قبل از admin_menu و بعد از لود توابع وردپرس)
		add_action( 'plugins_loaded', array( $this, 'repair_admin_cap' ), 99 );

		// CPT فرم‌ها همیشه ثبت شود (نیاز فرانت/REST/ادمین)
		if ( class_exists( 'Poian_Quiz_Forms' ) ) {
			add_action( 'init', array( 'Poian_Quiz_Forms', 'register_cpt' ) );
		}
		// REST فقط در درخواست REST
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		// فرانت فقط در صورت وجود کلاس و فقط با شورت‌کد asset لود می‌کند
		if ( class_exists( 'Poian_Quiz_Frontend' ) ) {
			$front = new Poian_Quiz_Frontend();
			$front->register();
		}

		// ادمین فقط در ادمین + asset فقط در صفحات خود افزونه
		if ( is_admin() && class_exists( 'Poian_Quiz_Admin' ) ) {
			$admin = new Poian_Quiz_Admin();
			$admin->register();
		}
	}
	public function repair_admin_cap() {
		if ( ! defined( 'POIAN_QUIZ_CAP' ) ) { return; }

		// لایه ۱: اضافه به role administrator (دائمی)
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( POIAN_QUIZ_CAP ) ) {
			$role->add_cap( POIAN_QUIZ_CAP );
		}

		// لایه ۲: اضافه مستقیم به کاربر فعلی با ذخیره در دیتابیس
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			$current = wp_get_current_user();
			if ( ! $current->has_cap( POIAN_QUIZ_CAP ) ) {
				$caps = $current->caps;
				if ( ! is_array( $caps ) ) { $caps = array(); }
				$caps[ POIAN_QUIZ_CAP ] = true;
				
				$role = get_role( 'administrator' );
				if ( $role ) {
					$role->add_cap( POIAN_QUIZ_CAP );
				}
				
				clean_user_cache( $current->ID );
			}
		}
	}
	public function register_rest() {
		if ( class_exists( 'Poian_Quiz_REST' ) ) {
			$rest = new Poian_Quiz_REST();
			$rest->register_routes();
		}
	}
}
