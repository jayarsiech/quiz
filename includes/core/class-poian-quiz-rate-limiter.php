<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Rate_Limiter {

	const CD_PREFIX  = 'poian_quiz_cd_';
	const DAY_PREFIX = 'poian_quiz_day_';

	/**
	 * @param string $actor_key خروجی Security::actor_key()
	 * @param int    $form_id
	 * @param array  $settings  تنظیمات مؤثر فرم (effective_for_form)
	 * @return true|WP_Error
	 */
	public static function check( $actor_key, $form_id, array $settings ) {
		$suffix = (int) $form_id . '_' . md5( $actor_key );

		if ( (int) $settings['cooldown_minutes'] > 0 ) {
			$wait = get_transient( self::CD_PREFIX . $suffix );
			if ( false !== $wait ) {
				return new WP_Error(
					'cooldown',
					sprintf( __( 'لطفاً %d دقیقه دیگر تلاش کنید.', 'poian-quiz' ), max( 1, (int) ceil( (int) $wait / 60 ) ) ),
					array( 'status' => 429 )
				);
			}
		}

		if ( (int) $settings['max_per_day'] > 0 ) {
			$day = (int) get_transient( self::DAY_PREFIX . $suffix );
			if ( $day >= (int) $settings['max_per_day'] ) {
				return new WP_Error( 'daily_limit', __( 'سهمیه روزانه شما به پایان رسیده است.', 'poian-quiz' ), array( 'status' => 429 ) );
			}
		}

		return true;
	}

	public static function record( $actor_key, $form_id, array $settings ) {
		$suffix = (int) $form_id . '_' . md5( $actor_key );

		if ( (int) $settings['cooldown_minutes'] > 0 ) {
			$seconds = (int) $settings['cooldown_minutes'] * MINUTE_IN_SECONDS;
			set_transient( self::CD_PREFIX . $suffix, $seconds, $seconds );
		}

		$day  = (int) get_transient( self::DAY_PREFIX . $suffix );
		$left = max( 60, (int) ( strtotime( 'tomorrow' ) - time() ) );
		set_transient( self::DAY_PREFIX . $suffix, $day + 1, $left );
	}
}
