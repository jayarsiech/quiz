<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Frontend {

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_shortcode( 'poian_quiz', array( $this, 'render' ) );
	}

	/**
	 * assetها فقط در صفحه‌ای که شورت‌کد وجود دارد.
	 */
	public function enqueue() {
		global $post;
		if ( ! is_singular() || ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'poian_quiz' ) ) {
			return;
		}

		// استایل و JS عمومی فرم‌ساز (همیشه لود می‌شود)
		wp_enqueue_style( 'poian-quiz-front', POIAN_QUIZ_PLUGIN_URL . 'assets/css/poian-quiz-front.css', array(), POIAN_QUIZ_VERSION );
		wp_enqueue_script( 'poian-quiz-front', POIAN_QUIZ_PLUGIN_URL . 'assets/js/poian-quiz-front.js', array(), POIAN_QUIZ_VERSION, true );

		// شناسایی موتورهای استفاده‌شده در این صفحه و لود دارایی‌های اختصاصی آنها
		$engine_ids = $this->detect_engines_in_content( $post->post_content );
		foreach ( $engine_ids as $engine_id ) {
			$engine = Poian_Quiz_Engine_Registry::get( $engine_id );
			if ( $engine ) {
				$engine->enqueue_assets();
			}
		}
	}

	/**
	 * شناسایی موتورهای استفاده‌شده در محتوای صفحه با parsing شورت‌کدها.
	 *
	 * @param string $content محتوای post
	 * @return array لیست engine_id های یکتا
	 */
	private function detect_engines_in_content( $content ) {
		$engine_ids = array();
		if ( ! preg_match_all( '/\[poian_quiz\s+([^\]]*)\]/', $content, $matches ) ) {
			return $engine_ids;
		}
		foreach ( $matches[1] as $attrs_str ) {
			if ( preg_match( '/id=["\']?(\d+)/', $attrs_str, $m ) ) {
				$form_id = (int) $m[1];
				if ( $form_id > 0 && Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
					$engine_id = Poian_Quiz_Forms::get_engine_id( $form_id );
					if ( $engine_id && 'none' !== $engine_id ) {
						$engine_ids[ $engine_id ] = true;
					}
				}
			}
		}
		return array_keys( $engine_ids );
	}
	public function render( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'poian_quiz' );
		$form_id = absint( $atts['id'] );
		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			return '';
		}

		$settings  = Poian_Quiz_Forms::effective_settings( $form_id );
		$engine_id = Poian_Quiz_Forms::get_engine_id( $form_id );
		$engine    = Poian_Quiz_Engine_Registry::get( $engine_id );
		$schema    = Poian_Quiz_Forms::get_schema( $form_id );
		$actor     = Poian_Quiz_Security::actor_key();

		if ( 1 === (int) $settings['require_login'] && ! is_user_logged_in() ) {
			ob_start();
			include POIAN_QUIZ_PLUGIN_DIR . 'templates/front/poian-quiz-gate.php';
			return ob_get_clean();
		}

		$retaking = (bool) get_transient( 'poian_quiz_retaking_' . $form_id . '_' . md5( $actor ) );
		$latest   = ( 'none' !== $engine_id && ! $retaking ) ? Poian_Quiz_Repository::get_latest( $form_id, $actor ) : null;

		// تبدیل ساختار قدیم pages به ساختار جدید fields (backward compatibility)
		// تبدیل ساختار قدیم pages به ساختار جدید fields (backward compatibility)
		$pk_pages_data = $this->normalize_schema_to_pages( $schema );

		// resolve progress setting برای استفاده در تمپلیت فرم
		$settings['_progress_resolved'] = $this->resolve_progress_setting( $settings, $pk_pages_data );

		ob_start();
		?>
		<div class="pq-app"
			data-form="<?php echo esc_attr( $form_id ); ?>"
			data-rest="<?php echo esc_url( rest_url( 'poian-quiz/v1/submit' ) ); ?>"
			data-retake="<?php echo esc_url( rest_url( 'poian-quiz/v1/retake' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( POIAN_QUIZ_NONCE_ACTION ) ); ?>"
			data-wpnonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-display="<?php echo esc_attr( $settings['display_mode'] ); ?>"
			data-progress="<?php echo esc_attr( $settings['_progress_resolved'] ); ?>"
			data-retake-mode="<?php echo esc_attr( isset( $settings['retake_mode'] ) ? $settings['retake_mode'] : 'cooldown' ); ?>">
			<?php
			if ( $latest ) {
				$pk_count      = Poian_Quiz_Repository::count_by_actor( $form_id, $actor );
				$pk_can_retake = $this->can_retake( $settings, $pk_count );
				include POIAN_QUIZ_PLUGIN_DIR . 'templates/front/poian-quiz-result.php';
			} else {
				include POIAN_QUIZ_PLUGIN_DIR . 'templates/front/poian-quiz-form.php';
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function normalize_schema_to_pages( array $schema ) {
		$pages = array();

		// ساختار جدید: fields
		if ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) {
			$current_page = array(
				'heading'     => '',
				'description' => '',
				'fields'      => array(),
				'next_label'  => '',
				'prev_label'  => '',
			);
			$is_first_pb = true;

			foreach ( $schema['fields'] as $f ) {
				if ( 'page_break' === $f['type'] ) {
					// ذخیره صفحه فعلی (فقط اگر محتوایی دارد)
					if ( ! empty( $current_page['fields'] ) || ! empty( $current_page['heading'] ) || ! empty( $current_page['description'] ) ) {
						$pages[] = $current_page;
					}
					// شروع صفحه جدید با تنظیمات این Page Break
					$current_page = array(
						'heading'       => isset( $f['heading'] ) ? $f['heading'] : '',
						'description'   => isset( $f['description'] ) ? $f['description'] : '',
						'fields'        => array(),
						'next_label'    => isset( $f['next_label'] ) ? $f['next_label'] : '',
						'prev_label'    => isset( $f['prev_label'] ) ? $f['prev_label'] : '',
						'show_progress' => isset( $f['show_progress'] ) ? $f['show_progress'] : 'inherit',
						'is_first'      => $is_first_pb,
					);
					$is_first_pb = false;
				} else {
					$current_page['fields'][] = $f;
				}
			}

			// ذخیره آخرین صفحه
			if ( ! empty( $current_page['fields'] ) || ! empty( $current_page['heading'] ) || ! empty( $current_page['description'] ) ) {
				$pages[] = $current_page;
			}

			return $pages;
		}

		return $pages;
	}

	/**
	 * بررسی امکان آزمون مجدد بر اساس سیاست.
	 */
	private function can_retake( array $settings, $count ) {
		$mode = isset( $settings['retake_mode'] ) ? $settings['retake_mode'] : 'cooldown';

		if ( 'once' === $mode ) {
			return false; // فقط یک‌بار
		}

		if ( 'unlimited' === $mode ) {
			// نامحدود، ولی سقف کل را بررسی کن
			if ( (int) $settings['max_total'] > 0 && $count >= (int) $settings['max_total'] ) {
				return false;
			}
			return true;
		}

		// cooldown: سقف کل را بررسی کن
		if ( (int) $settings['max_total'] > 0 && $count >= (int) $settings['max_total'] ) {
			return false;
		}
		return true;
	}
		/**
	 * تنظیم نوار پیشرفت بر اساس اولین Page Break یا ارث از settings.
	 */
	private function resolve_progress_setting( array $settings, array $pages ) {
		// بررسی اولین صفحه (که ممکن است از page_break اول آمده باشد)
		if ( ! empty( $pages ) && isset( $pages[0]['show_progress'] ) ) {
			$pb_val = $pages[0]['show_progress'];
			if ( '1' === $pb_val || 1 === $pb_val ) { return '1'; }
			if ( '0' === $pb_val || 0 === $pb_val ) { return '0'; }
		}
		// inherit: از تنظیمات کلی
		return 1 === (int) $settings['show_progress'] ? '1' : '0';
	}
    
}
