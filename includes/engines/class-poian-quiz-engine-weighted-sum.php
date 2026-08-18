<?php
defined( 'ABSPATH' ) || exit;

class Poian_Quiz_Engine_Weighted_Sum extends Poian_Quiz_Engine {

	public function get_id() { return 'weighted-sum'; }
	public function get_title() { return __( 'جمع وزنی per بُعد', 'poian-quiz' ); }
	public function supports_option_weights() { return true; }

	public function compute( array $answers, array $schema ) {
		$scores = array();
		$fields = Poian_Quiz_Schema::all_fields( $schema );

		foreach ( $fields as $field ) {
			$fid = (string) $field['id'];
			if ( empty( $field['dim'] ) || ! isset( $answers[ $fid ] ) ) {
				continue;
			}
			$dim = (string) $field['dim'];
			if ( ! isset( $scores[ $dim ] ) ) { $scores[ $dim ] = 0; }

			$weights = array();
			if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $o ) {
					if ( isset( $o['key'] ) ) { $weights[ (string) $o['key'] ] = isset( $o['weight'] ) ? (float) $o['weight'] : 0; }
				}
			}

			$val = $answers[ $fid ];
			if ( 'radio' === $field['type'] ) {
				$scores[ $dim ] += isset( $weights[ $val ] ) ? $weights[ $val ] : 0;
			} elseif ( 'checkbox' === $field['type'] && is_array( $val ) ) {
				foreach ( $val as $k ) {
					$scores[ $dim ] += isset( $weights[ $k ] ) ? $weights[ $k ] : 0;
				}
			}
		}

		return array( 'scores' => $scores, 'result_slug' => '', 'result_label' => '', 'extra' => array() );
	}
}
