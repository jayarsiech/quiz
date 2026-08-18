<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Engine_Kolb extends Poian_Quiz_Engine {

	// نگاشت گزینه‌ها به مولفه‌ها (مطابق داکیومنت کلب)
	const COMPONENTS = array( 'a' => 'ce', 'b' => 'ro', 'c' => 'ac', 'd' => 'ae' );
	const AXIS_MAX   = 36;

	const QUADRANTS = array(
		'innovator' => 'مبتکر',
		'doer'      => 'عملگرا',
		'planner'   => 'برنامه‌ریز',
		'decider'   => 'تصمیم‌گیر',
	);

	public function get_id() { return 'kolb'; }
	public function get_title() { return __( 'سبک یادگیری کلب', 'poian-quiz' ); }
	public function get_field_types() { return array( 'rank', 'description', 'heading' ); }

	public function compute( array $answers, array $schema ) {
		$scores = array( 'ce' => 0, 'ro' => 0, 'ac' => 0, 'ae' => 0 );
		$weights = array( 4, 3, 2, 1 ); // موقعیت اول تا چهارم

		$fields = Poian_Quiz_Schema::all_fields( $schema );
		foreach ( $fields as $f ) {
			if ( 'rank' !== $f['type'] || ! isset( $answers[ $f['id'] ] ) || ! is_array( $answers[ $f['id'] ] ) ) {
				continue;
			}
			foreach ( array_values( $answers[ $f['id'] ] ) as $pos => $opt ) {
				if ( isset( self::COMPONENTS[ $opt ], $weights[ $pos ] ) ) {
					$scores[ self::COMPONENTS[ $opt ] ] += $weights[ $pos ];
				}
			}
		}

		$x = (int) $scores['ro'] - (int) $scores['ae']; // محور افقی
		$y = (int) $scores['ce'] - (int) $scores['ac']; // محور عمودی

		$slug = ( $x >= 0 ) ? ( ( $y >= 0 ) ? 'innovator' : 'planner' ) : ( ( $y >= 0 ) ? 'doer' : 'decider' );

		return array(
			'scores'       => $scores,
			'result_slug'  => $slug,
			'result_label' => self::QUADRANTS[ $slug ],
			'extra'        => array( 'x' => $x, 'y' => $y, 'axis_max' => self::AXIS_MAX ),
		);
	}

	public function render_result( array $result, array $attempt ) {
		ob_start();
		include POIAN_QUIZ_PLUGIN_DIR . 'includes/engines/kolb/templates/poian-quiz-result-kolb.php';
		return ob_get_clean();
	}
		/**
	 * لود استایل/اسکریپت اختصاصی موتور کلب.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'poian-quiz-engine-kolb',
			POIAN_QUIZ_PLUGIN_URL . 'includes/engines/kolb/assets/kolb.css',
			array( 'poian-quiz-front' ),
			POIAN_QUIZ_VERSION
		);
	}
}
