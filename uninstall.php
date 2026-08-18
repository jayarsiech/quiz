<?php
/**
 * Uninstall — حذف تمیز فقط با رضایت صریح ادمین.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// پاک‌سازی Capability همیشه انجام می‌شود.
$pk_role = get_role( 'administrator' );
if ( $pk_role ) {
	$pk_role->remove_cap( 'poian_quiz_manage' );
}

$pk_settings = get_option( 'poian_quiz_settings', array() );

if ( ! empty( $pk_settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}poian_quiz_attempts" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}poian_quiz_scores" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}poian_quiz_form_versions" );

	delete_option( 'poian_quiz_settings' );
	delete_option( 'poian_quiz_db_version' );
	delete_option( 'poian_quiz_kolb_form_id' );

	// حذف فرم‌ها (CPT) + متاهایشان
	$pk_ids = get_posts( array( 'post_type' => 'poian_quiz', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $pk_ids as $pk_id ) {
		wp_delete_post( $pk_id, true );
	}
}
