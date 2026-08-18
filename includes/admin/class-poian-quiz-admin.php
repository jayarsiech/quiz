<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Admin {

	const MAIN_SLUG = 'poian-quiz-forms';
	private $hook_suffix = '';
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		$editor = new Poian_Quiz_Admin_Form_Editor();
		$editor->register();

		$settings = new Poian_Quiz_Admin_Settings();
		$settings->register();

		$form_settings = new Poian_Quiz_Admin_Form_Settings();
		$form_settings->register();

		$inbox = new Poian_Quiz_Admin_Inbox();
		$inbox->register();

		if ( class_exists( 'Poian_Quiz_Mehdyar_Importer' ) ) {
			Poian_Quiz_Mehdyar_Importer::register_button();
		}
		if ( class_exists( 'Poian_Quiz_Kolb_Importer' ) ) {
			Poian_Quiz_Kolb_Importer::register_button();
		}
	}
	public function menu() {
		// منوی اصلی: همه صفحات زیر این slug
		$this->hook_suffix = add_menu_page(
			__( 'Poian Quiz', 'poian-quiz' ),
			__( 'Poian Quiz', 'poian-quiz' ),
			POIAN_QUIZ_CAP,
			self::MAIN_SLUG,
			array( $this, 'dispatch' ),
			'dashicons-clipboard',
			26
		);

		// زیرمنوهای قابل نمایش
		add_submenu_page( self::MAIN_SLUG, __( 'فرم‌ها', 'poian-quiz' ), __( 'فرم‌ها', 'poian-quiz' ), POIAN_QUIZ_CAP, self::MAIN_SLUG, array( $this, 'dispatch' ) );
		add_submenu_page( self::MAIN_SLUG, __( 'صندوق ورودی‌ها', 'poian-quiz' ), __( 'صندوق ورودی‌ها', 'poian-quiz' ), POIAN_QUIZ_CAP, self::MAIN_SLUG . '-inbox', array( $this, 'dispatch' ) );
		add_submenu_page( self::MAIN_SLUG, __( 'تنظیمات کلی', 'poian-quiz' ), __( 'تنظیمات', 'poian-quiz' ), POIAN_QUIZ_CAP, self::MAIN_SLUG . '-global-settings', array( $this, 'dispatch' ) );

		// صفحات مخفی (URL-only) برای editor، inbox per-form، inbox-view، settings per-form
		add_submenu_page( null, __( 'ویرایش فرم', 'poian-quiz' ), '', POIAN_QUIZ_CAP, self::MAIN_SLUG . '-editor', array( $this, 'dispatch' ) );
		add_submenu_page( null, __( 'صندوق فرم', 'poian-quiz' ), '', POIAN_QUIZ_CAP, self::MAIN_SLUG . '-form-inbox', array( $this, 'dispatch' ) );
		add_submenu_page( null, __( 'مشاهده ورودی', 'poian-quiz' ), '', POIAN_QUIZ_CAP, self::MAIN_SLUG . '-entry', array( $this, 'dispatch' ) );
		add_submenu_page( null, __( 'تنظیمات فرم', 'poian-quiz' ), '', POIAN_QUIZ_CAP, self::MAIN_SLUG . '-settings', array( $this, 'dispatch' ) );
	}

	/**
	 * Router: بر اساس page و view پارامتر، صفحه مناسب را render می‌کند.
	 * همچنین منوی اصلی را در همه صفحات فرعی highlighted نگه می‌دارد.
	 */
	public function dispatch() {
		global $submenu_file, $parent_file;

		// Highlight منوی اصلی در همه صفحات فرعی
		$parent_file  = self::MAIN_SLUG;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';

		// تشخیص صفحه فعلی و تنظیم submenu_file برای highlight
		if ( self::MAIN_SLUG === $page ) {
			if ( 'editor' === $view ) {
				$submenu_file = self::MAIN_SLUG . '-editor';
			} elseif ( 'settings' === $view ) {
				$submenu_file = self::MAIN_SLUG . '-settings';
			} elseif ( 'inbox' === $view ) {
				$submenu_file = self::MAIN_SLUG . '-form-inbox';
			} elseif ( 'entry' === $view ) {
				$submenu_file = self::MAIN_SLUG . '-entry';
			} else {
				$submenu_file = self::MAIN_SLUG;
			}
		} elseif ( self::MAIN_SLUG . '-inbox' === $page ) {
			$submenu_file = self::MAIN_SLUG . '-inbox';
			if ( 'entry' === $view ) {
				$submenu_file = self::MAIN_SLUG . '-entry';
			}
		} elseif ( self::MAIN_SLUG . '-global-settings' === $page ) {
			$submenu_file = self::MAIN_SLUG . '-global-settings';
		}

		// Route به render method مناسب
		switch ( $page ) {
			case self::MAIN_SLUG:
				switch ( $view ) {
					case 'editor':
						$this->render_editor();
						return;
					case 'settings':
						$this->render_form_settings();
						return;
					case 'inbox':
						$this->render_inbox_list();
						return;
					case 'entry':
						$this->render_inbox_view();
						return;
					default:
						$this->render_list();
						return;
				}
				break;

			case self::MAIN_SLUG . '-editor':
				$this->render_editor();
				return;

			case self::MAIN_SLUG . '-inbox':
				if ( 'entry' === $view ) {
					$this->render_inbox_view();
				} else {
					$this->render_global_inbox();
				}
				return;

			case self::MAIN_SLUG . '-form-inbox':
				$this->render_inbox_list();
				return;

			case self::MAIN_SLUG . '-entry':
				$this->render_inbox_view();
				return;

			case self::MAIN_SLUG . '-settings':
				$this->render_form_settings();
				return;

			case self::MAIN_SLUG . '-global-settings':
				$this->render_global_settings();
				return;
		}

		wp_die( esc_html__( 'صفحه یافت نشد.', 'poian-quiz' ), 404 );
	}

	public function enqueue( $hook ) {
		// لود دارایی‌ها فقط در صفحات افزونه
		$my_hooks = array( $this->hook_suffix );
		// همه submenu pages که با slug ما شروع می‌شوند
		if ( false === strpos( $hook, self::MAIN_SLUG ) && ! in_array( $hook, $my_hooks, true ) ) {
			// بررسی بر اساس page parameter برای صفحات مخفی
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( 0 !== strpos( $page, self::MAIN_SLUG ) ) {
				return;
			}
		}

		wp_enqueue_style( 'poian-quiz-admin', POIAN_QUIZ_PLUGIN_URL . 'assets/css/poian-quiz-admin.css', array(), POIAN_QUIZ_VERSION );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// فقط در ادیتور فرم: SortableJS + JS بیلدر + TinyMCE
		if ( 'editor' === $view || self::MAIN_SLUG . '-editor' === $page ) {
			// TinyMCE (WordPress built-in) برای ویرایشگر Rich Text شخصیت‌ها
			wp_enqueue_editor();

			// SortableJS (محلی، نه CDN)
			wp_enqueue_script(
				'sortablejs',
				POIAN_QUIZ_PLUGIN_URL . 'assets/vendor/sortable.min.js',
				array(),
				'1.15.2',
				true
			);

			// JS بیلدر (وابسته به SortableJS)
			wp_enqueue_script(
				'poian-quiz-admin-builder',
				POIAN_QUIZ_PLUGIN_URL . 'assets/js/poian-quiz-admin-builder.js',
				array( 'sortablejs' ),
				POIAN_QUIZ_VERSION,
				true
			);
		}

		// فقط در صفحه تنظیمات فرم: JS نمایش شرطی
		if ( 'settings' === $view ) {
			wp_enqueue_script(
				'poian-quiz-admin-settings',
				POIAN_QUIZ_PLUGIN_URL . 'assets/js/poian-quiz-admin-settings.js',
				array(),
				POIAN_QUIZ_VERSION,
				true
			);
		}
	}
	/* ---------------- Render Methods ---------------- */

	public function render_list() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-forms-list.php';
	}

	public function render_editor() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$editor = new Poian_Quiz_Admin_Form_Editor();
		$editor->render_page();
	}

	public function render_inbox_list() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$inbox = new Poian_Quiz_Admin_Inbox();
		$inbox->render_list_page();
	}

	public function render_inbox_view() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$inbox = new Poian_Quiz_Admin_Inbox();
		$inbox->render_view_page();
	}

	/**
	 * صندوق ورودی‌های همه فرم‌ها (Global Inbox).
	 * پیاده‌سازی در فاز بعدی تکمیل می‌شود؛ فعلاً placeholder.
	 */
	public function render_global_inbox() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$pk_notice = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
		$pk_list   = new Poian_Quiz_Admin_Global_Inbox_List_Table();
		$pk_list->prepare_items();
		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-global-inbox.php';
	}

	public function render_global_settings() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$s = new Poian_Quiz_Admin_Settings();
		$s->render_page();
	}

	/**
	 * صفحه تنظیمات per-فرم.
	 */
	public function render_form_settings() {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$fs = new Poian_Quiz_Admin_Form_Settings();
		$fs->render_page( $form_id );
	}
	
}
