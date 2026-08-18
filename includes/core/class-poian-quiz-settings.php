<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Settings {

	public static function all() {
		$saved = get_option( POIAN_QUIZ_OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), Poian_Quiz_Activator::default_settings() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $input ) {
		$clean = self::sanitize( $input );
		update_option( POIAN_QUIZ_OPTION_KEY, $clean, 'no' );
		return $clean;
	}

	private static function sanitize( array $input ) {
		$cur = self::all();
		$out = $cur;

		if ( array_key_exists( 'require_login', $input ) ) {
			$out['require_login'] = empty( $input['require_login'] ) ? 0 : 1;
		}
		if ( isset( $input['cooldown_minutes'] ) ) {
			$out['cooldown_minutes'] = max( 0, min( 1440, absint( $input['cooldown_minutes'] ) ) );
		}
		if ( isset( $input['max_per_day'] ) ) {
			$out['max_per_day'] = max( 0, min( 1000, absint( $input['max_per_day'] ) ) );
		}
		if ( isset( $input['max_total'] ) ) {
			$out['max_total'] = max( 0, min( 1000, absint( $input['max_total'] ) ) );
		}
		if ( isset( $input['display_mode'] ) && in_array( $input['display_mode'], array( 'all', 'single' ), true ) ) {
			$out['display_mode'] = $input['display_mode'];
		}
		if ( array_key_exists( 'show_progress', $input ) ) {
			$out['show_progress'] = empty( $input['show_progress'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'delete_on_uninstall', $input ) ) {
			$out['delete_on_uninstall'] = empty( $input['delete_on_uninstall'] ) ? 0 : 1;
		}
		if ( isset( $input['retake_mode'] ) && in_array( $input['retake_mode'], array( 'cooldown', 'unlimited', 'once' ), true ) ) {
			$out['retake_mode'] = $input['retake_mode'];
		}
		if ( isset( $input['history_count'] ) ) {
			$allowed = array( 0, 5, 10, -1 );
			$val = (int) $input['history_count'];
			$out['history_count'] = in_array( $val, $allowed, true ) ? $val : 0;
		}
		return $out;
	}
	/**
	 * تنظیمات مؤثر برای یک فرم: اورایدهای فرم روی پیش‌فرض‌های کلی.
	 * مقدار 'inherit' یعنی استفاده از تنظیمات کلی.
	 */
	public static function effective_for_form( array $form_settings ) {
		$global = self::all();
		$out    = $global;
		foreach ( $global as $key => $val ) {
			if ( isset( $form_settings[ $key ] ) && 'inherit' !== $form_settings[ $key ] ) {
				$out[ $key ] = $form_settings[ $key ];
			}
		}
		// نرمال‌سازی تایپ‌ها
		$out['require_login']    = (int) $out['require_login'];
		$out['cooldown_minutes'] = (int) $out['cooldown_minutes'];
		$out['max_per_day']      = (int) $out['max_per_day'];
		$out['max_total']        = (int) $out['max_total'];
		$out['show_progress']    = (int) $out['show_progress'];
		$out['history_count']    = (int) $out['history_count'];
		if ( ! in_array( $out['retake_mode'], array( 'cooldown', 'unlimited', 'once' ), true ) ) {
			$out['retake_mode'] = 'cooldown';
		}
		return $out;
	}
}
