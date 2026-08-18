<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Autoloader {

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Poian_Quiz_' ) ) {
			return;
		}
		$file = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		$dirs = array( 'includes/', 'includes/core/', 'includes/engines/', 'includes/engines/mehdyar/', 'includes/engines/kolb/', 'includes/front/', 'includes/admin/' );
		foreach ( $dirs as $dir ) {
			$path = POIAN_QUIZ_PLUGIN_DIR . $dir . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
