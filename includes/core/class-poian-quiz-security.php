<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Security {

	/**
	 * لایه ۱: بلک‌لیست صریح کلیدهای حساس (مطابق استاندارد Review).
	 */
	const META_BLACKLIST = array(
		'wp_capabilities',
		'wp_user_level',
		'session_tokens',
		'admin_color',
		'rich_editing',
		'comment_shortcuts',
		'show_admin_bar_front',
		'user-settings',
		'user-settings-time',
	);

	public static function user_can_manage() {
		return current_user_can( POIAN_QUIZ_CAP );
	}

	public static function verify_submit_nonce( $nonce ) {
		return (bool) wp_verify_nonce( (string) $nonce, POIAN_QUIZ_NONCE_ACTION );
	}

	public static function sanitize_mobile( $mobile ) {
		$mobile = preg_replace( '/[^0-9]/', '', (string) $mobile );
		return substr( $mobile, 0, 15 );
	}

	/**
	 * کلید هویت: کاربر لاگین = u:ID | مهمان = g:hash (کوکی ماندگار).
	 */
	public static function actor_key() {
		if ( is_user_logged_in() ) {
			return 'u:' . get_current_user_id();
		}

		$cookie = isset( $_COOKIE['poian_quiz_actor'] ) ? preg_replace( '/[^a-z0-9]/', '', (string) $_COOKIE['poian_quiz_actor'] ) : '';
		if ( 32 !== strlen( $cookie ) ) {
			$cookie = md5( uniqid( 'pq', true ) );
			if ( ! headers_sent() ) {
				setcookie( 'poian_quiz_actor', $cookie, time() + YEAR_IN_SECONDS, '/', '', false, true );
			}
		}
		return 'g:' . $cookie;
	}

	/**
	 * اعتبارسنجی کلید متا — سه لایه:
	 * ۱) بلک‌لیست صریح  ۲) وایلدکارد wp_ و کاراکتر غیرمجاز  ۳) وایت‌لیست فرم + فیلتر توسعه‌دهنده
	 */
	public static function is_meta_key_allowed( $key, array $form_allowed_keys ) {
		$key = trim( (string) $key );

		// شکل کلید
		if ( '' === $key || strlen( $key ) > 100 || ! preg_match( '/^[a-z0-9_]+$/i', $key ) ) {
			return false;
		}
		// لایه ۱
		if ( in_array( strtolower( $key ), self::META_BLACKLIST, true ) ) {
			return false;
		}
		// لایه ۲
		if ( 0 === strpos( strtolower( $key ), 'wp_' ) ) {
			return false;
		}
		// لایه ۳: فقط کلیدهای تعریف‌شده در اکشن فرم (+ فیلتر توسعه‌دهنده)
		$whitelist = apply_filters( 'poian_quiz_allowed_meta_keys', array_values( array_map( 'strval', $form_allowed_keys ) ) );
		return in_array( $key, $whitelist, true );
	}
}
