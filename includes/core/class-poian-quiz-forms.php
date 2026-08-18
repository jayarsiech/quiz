<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Forms {

	const META_ENGINE        = 'pq_engine';
	const META_SCHEMA        = 'pq_schema';
	const META_ACTIONS       = 'pq_actions';
	const META_SETTINGS      = 'pq_settings';
	const META_VERSION       = 'pq_current_version';
	const META_ENGINE_CONFIG = 'pq_engine_config'; // 🆕 مولفه‌ها و شخصیت‌ها

	public static function register_cpt() {
		register_post_type( POIAN_QUIZ_CPT, array(
			'labels'       => array(
				'name'          => __( 'فرم‌های آزمون', 'poian-quiz' ),
				'singular_name' => __( 'فرم آزمون', 'poian-quiz' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'supports'     => array( 'title' ),
		) );
	}

	public static function create( $title, $engine_id ) {
		$id = wp_insert_post( array(
			'post_type'   => POIAN_QUIZ_CPT,
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => 'publish',
		) );
		if ( is_wp_error( $id ) || ! $id ) {
			return $id;
		}
		update_post_meta( $id, self::META_ENGINE, sanitize_key( $engine_id ) );
		// ساختار جدید: fields به جای pages
		update_post_meta( $id, self::META_SCHEMA, array( 'title' => $title, 'fields' => array() ) );
		update_post_meta( $id, self::META_ACTIONS, array() );
		update_post_meta( $id, self::META_SETTINGS, array() );
		update_post_meta( $id, self::META_VERSION, 0 );
		update_post_meta( $id, self::META_ENGINE_CONFIG, array() );
		self::snapshot( $id );
		return (int) $id;
	}

	public static function update_schema( $id, array $schema ) {
		update_post_meta( $id, self::META_SCHEMA, $schema );
		self::snapshot( $id );
	}

	private static function snapshot( $id ) {
		global $wpdb;
		$version = (int) get_post_meta( $id, self::META_VERSION, true ) + 1;
		update_post_meta( $id, self::META_VERSION, $version );
		$wpdb->insert(
			$wpdb->prefix . 'poian_quiz_form_versions',
			array(
				'form_id'     => (int) $id,
				'version'     => $version,
				'schema_json' => wp_json_encode( get_post_meta( $id, self::META_SCHEMA, true ) ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	public static function is_valid_form( $id ) {
		return 'publish' === get_post_status( $id );
	}

	public static function get_current_version( $id ) {
		return (int) get_post_meta( $id, self::META_VERSION, true );
	}

	public static function get_schema( $id, $version = null ) {
		if ( null === $version || (int) $version === self::get_current_version( $id ) ) {
			$s = get_post_meta( $id, self::META_SCHEMA, true );
			// ساختار جدید fields را برگردان، یا backward compat برای pages
			if ( is_array( $s ) ) {
				if ( isset( $s['fields'] ) ) { return $s; }
				if ( isset( $s['pages'] ) ) { return $s; }
				return array( 'title' => '', 'fields' => array() );
			}
			return array( 'title' => '', 'fields' => array() );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT schema_json FROM {$wpdb->prefix}poian_quiz_form_versions WHERE form_id = %d AND version = %d",
			(int) $id, (int) $version
		), ARRAY_A );
		$s = $row ? json_decode( $row['schema_json'], true ) : null;
		if ( is_array( $s ) ) {
			if ( isset( $s['fields'] ) ) { return $s; }
			if ( isset( $s['pages'] ) ) { return $s; }
		}
		return array( 'title' => '', 'fields' => array() );
	}

	public static function get_engine_id( $id ) {
		return (string) get_post_meta( $id, self::META_ENGINE, true );
	}

	public static function get_actions( $id ) {
		$a = get_post_meta( $id, self::META_ACTIONS, true );
		return is_array( $a ) ? $a : array();
	}

	public static function effective_settings( $id ) {
		$s = get_post_meta( $id, self::META_SETTINGS, true );
		return Poian_Quiz_Settings::effective_for_form( is_array( $s ) ? $s : array() );
	}

	/**
	 * دریافت engine_config یک فرم (مولفه‌ها و شخصیت‌ها).
	 */
	public static function get_engine_config( $id ) {
		$config = get_post_meta( (int) $id, self::META_ENGINE_CONFIG, true );
		return is_array( $config ) ? $config : array();
	}

	/**
	 * ذخیره engine_config یک فرم.
	 */
	public static function save_engine_config( $id, array $config ) {
		update_post_meta( (int) $id, self::META_ENGINE_CONFIG, $config );
	}
}
