<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Engine_None extends Poian_Quiz_Engine {

	public function get_id() { return 'none'; }
	public function get_title() { return __( 'فرم ساده (بدون نمره)', 'poian-quiz' ); }

	public function compute( array $answers, array $schema ) {
		return array( 'scores' => array(), 'result_slug' => '', 'result_label' => '', 'extra' => array() );
	}
}
