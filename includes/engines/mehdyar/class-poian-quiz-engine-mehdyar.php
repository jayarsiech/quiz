<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Engine_Mehdyar extends Poian_Quiz_Engine_Weighted_Sum {

	const MAX = 20;

	const DIMS = array( 'fiqh', 'belief', 'growth', 'mission' );

	/**
	 * برچسب‌های پیش‌فرض (fallback). اگر در engine_config نبود، از این‌ها استفاده می‌شود.
	 */
	const DEFAULT_DIM_LABELS = array(
		'fiqh'    => 'فقه زندگی',
		'belief'  => 'بینش اعتقادی',
		'growth'  => 'رشد فردی',
		'mission' => 'کشف رسالت',
	);

	// قانون برابری (تأیید کاربر): بینش اعتقادی > فقه زندگی > رشد فردی > کشف رسالت
	const TIE_PRIORITY = array( 'belief', 'fiqh', 'growth', 'mission' );

	public function get_id() { return 'mehdyar'; }
	public function get_title() { return __( 'قطب‌نمای مهدیار', 'poian-quiz' ); }
	public function get_field_types() { return array( 'radio', 'description', 'heading' ); }

	/**
	 * دریافت برچسب‌های فارسی مولفه‌ها (از engine_config فرم + fallback به پیش‌فرض).
	 *
	 * @param int $form_id
	 * @return array کلید => نام فارسی
	 */
	public static function get_dim_labels( $form_id = 0 ) {
		$labels = self::DEFAULT_DIM_LABELS;

		if ( $form_id && class_exists( 'Poian_Quiz_Forms' ) ) {
			$config = Poian_Quiz_Forms::get_engine_config( $form_id );
			if ( isset( $config['dimensions'] ) && is_array( $config['dimensions'] ) ) {
				foreach ( $config['dimensions'] as $key => $val ) {
					$key = sanitize_key( $key );
					if ( array_key_exists( $key, $labels ) && '' !== (string) $val ) {
						$labels[ $key ] = (string) $val;
					}
				}
			}
		}

		return apply_filters( 'poian_quiz_mehdyar_dim_labels', $labels, $form_id );
	}

	public function compute( array $answers, array $schema ) {
		$base   = parent::compute( $answers, $schema );
		$scores = array();
		foreach ( self::DIMS as $d ) {
			$scores[ $d ] = isset( $base['scores'][ $d ] ) ? (float) $base['scores'][ $d ] : 0;
		}

		// مرتب‌سازی: نمره نزولی؛ برابری با TIE_PRIORITY
		$dims = self::DIMS;
		usort( $dims, function ( $a, $b ) use ( $scores ) {
			if ( (float) $scores[ $a ] !== (float) $scores[ $b ] ) {
				return $scores[ $b ] <=> $scores[ $a ];
			}
			$pa = array_search( $a, self::TIE_PRIORITY, true );
			$pb = array_search( $b, self::TIE_PRIORITY, true );
			return $pa <=> $pb;
		} );

		$slug = $dims[0] . '_' . $dims[1];
		$def  = self::defaults();
		$label = isset( $def[ $slug ]['title'] ) ? $def[ $slug ]['title'] : $slug;

		return array(
			'scores'       => $scores,
			'result_slug'  => $slug,
			'result_label' => $label,
			'extra'        => array(
				'ordered_dims' => $dims,
				'max'          => self::MAX,
			),
		);
	}

	/**
	 * متن‌های پیش‌فرض (fallback کد) — فایل جدا در همین فولدر.
	 */
	public static function defaults() {
		static $cache = null;
		if ( null === $cache ) {
			$cache = include POIAN_QUIZ_PLUGIN_DIR . 'includes/engines/mehdyar/defaults-personalities.php';
		}
		return is_array( $cache ) ? $cache : array();
	}

	public static function get_personality( $slug, $form_id = 0 ) {
		$defs = self::defaults();
		$p    = isset( $defs[ $slug ] ) ? $defs[ $slug ] : null;

		if ( $form_id && $p && class_exists( 'Poian_Quiz_Forms' ) ) {
			$cfg = Poian_Quiz_Forms::get_engine_config( $form_id );
			if ( isset( $cfg['personalities'][ $slug ] ) && is_array( $cfg['personalities'][ $slug ] ) ) {
				$over = $cfg['personalities'][ $slug ];

				// override ساده: emoji و title
				foreach ( array( 'emoji', 'title' ) as $k ) {
					if ( isset( $over[ $k ] ) && '' !== (string) $over[ $k ] ) { $p[ $k ] = $over[ $k ]; }
				}

				// 🆕 ساختار جدید: content (HTML)
				if ( isset( $over['content'] ) && '' !== (string) $over['content'] ) {
					$p['content'] = $over['content'];
				}

				// ساختار قدیمی: texts (backward compatibility)
				if ( isset( $over['texts'] ) && is_array( $over['texts'] ) && isset( $p['texts'] ) ) {
					$p['texts'] = array_merge( $p['texts'], array_filter( $over['texts'], 'strlen' ) );
				}
			}
		}
		return apply_filters( 'poian_quiz_mehdyar_personality', $p, $slug, $form_id );
	}

	public function render_result( array $result, array $attempt ) {
		$form_id = isset( $attempt['form_id'] ) ? (int) $attempt['form_id'] : 0;
		$scores  = isset( $result['scores'] ) && is_array( $result['scores'] ) ? $result['scores'] : array();
		$slug    = isset( $result['result_slug'] ) ? (string) $result['result_slug'] : '';
		$person  = self::get_personality( $slug, $form_id );
		$dim_labels   = self::get_dim_labels( $form_id );
		$ordered_dims = isset( $result['extra']['ordered_dims'] ) ? (array) $result['extra']['ordered_dims'] : self::DIMS;

		// 🆕 محاسبه جمع کل و ماکزیمم ممکن
		$pk_max         = isset( $result['extra']['max'] ) ? (int) $result['extra']['max'] : self::MAX;
		$total_score    = array_sum( array_map( 'floatval', array_values( $scores ) ) );
		$max_possible   = $pk_max * count( self::DIMS ); // مثلاً ۲۰ × ۴ = ۸۰

		// 🆕 تاریخچه نتایج کاربر
		$pk_history       = array();
		$pk_history_count = 0;
		if ( $form_id && class_exists( 'Poian_Quiz_Forms' ) ) {
			$settings = Poian_Quiz_Forms::effective_settings( $form_id );
			$pk_history_count = isset( $settings['history_count'] ) ? (int) $settings['history_count'] : 0;
			if ( 0 !== $pk_history_count && ! empty( $attempt['actor_key'] ) ) {
				$limit = ( -1 === $pk_history_count ) ? 50 : $pk_history_count;
				$pk_history = Poian_Quiz_Repository::get_user_history( $form_id, $attempt['actor_key'], $limit );
			}
		}

		ob_start();
		include POIAN_QUIZ_PLUGIN_DIR . 'includes/engines/mehdyar/templates/poian-quiz-result-mehdyar.php';
		return ob_get_clean();
	}

	/**
	 * لود استایل/اسکریپت اختصاصی موتور مهدیار.
	 * فقط زمانی فراخوانی می‌شود که این موتور در صفحه استفاده شده باشد.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'poian-quiz-engine-mehdyar',
			POIAN_QUIZ_PLUGIN_URL . 'includes/engines/mehdyar/assets/mehdyar.css',
			array( 'poian-quiz-front' ),
			POIAN_QUIZ_VERSION
		);
	}
}
