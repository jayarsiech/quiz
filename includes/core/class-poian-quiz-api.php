<?php
defined( 'ABSPATH' ) || exit;

/**
 * API عمومی برای افزونه‌های دیگر — خواندن سریع از ایندکس‌ها.
 */
final class Poian_Quiz_API {

	public static function get_latest_result( $form_id, $actor_key = null ) {
		$actor = $actor_key ? $actor_key : Poian_Quiz_Security::actor_key();
		return Poian_Quiz_Repository::get_latest( $form_id, $actor );
	}

	public static function get_score( $form_id, $dim_key, $user_id ) {
		global $wpdb;
		$val = $wpdb->get_var( $wpdb->prepare(
			"SELECT dim_value FROM {$wpdb->prefix}poian_quiz_scores WHERE form_id = %d AND user_id = %d AND dim_key = %s ORDER BY id DESC LIMIT 1",
			(int) $form_id, (int) $user_id, (string) $dim_key
		) );
		return null === $val ? null : (float) $val;
	}

	/**
	 * فیلتر سازمانی: query_scores( ['form_id'=>1,'dim_key'=>'R','operator'=>'>=','value'=>30,'limit'=>100,'offset'=>0] )
	 */
	public static function query_scores( array $args ) {
		global $wpdb;
		$where  = 'form_id = %d';
		$params = array( (int) $args['form_id'] );

		if ( ! empty( $args['dim_key'] ) ) { $where .= ' AND dim_key = %s'; $params[] = (string) $args['dim_key']; }
		if ( ! empty( $args['operator'] ) && in_array( $args['operator'], array( '=', '>', '<', '>=', '<=' ), true ) && isset( $args['value'] ) ) {
			$where .= " AND dim_value {$args['operator']} %f"; $params[] = (float) $args['value'];
		}

		$limit  = isset( $args['limit'] ) ? min( 1000, max( 1, (int) $args['limit'] ) ) : 100;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$params[] = $limit; $params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, actor_key, dim_key, dim_value, attempt_id FROM {$wpdb->prefix}poian_quiz_scores WHERE {$where} ORDER BY dim_value DESC LIMIT %d OFFSET %d",
			$params
		), ARRAY_A );
	}
}
