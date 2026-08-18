<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Engine_Registry {

	private static $engines = null;

	public static function all() {
		if ( null === self::$engines ) {
			$list          = array( new Poian_Quiz_Engine_None(), new Poian_Quiz_Engine_Weighted_Sum(), new Poian_Quiz_Engine_Mehdyar(), new Poian_Quiz_Engine_Kolb() );
			self::$engines = apply_filters( 'poian_quiz_register_engines', $list );
		}
		return self::$engines;
	}

	public static function get( $id ) {
		foreach ( self::all() as $e ) {
			if ( $e instanceof Poian_Quiz_Engine && $e->get_id() === $id ) {
				return $e;
			}
		}
		return null;
	}
}
