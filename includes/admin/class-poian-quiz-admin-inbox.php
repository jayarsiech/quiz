<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class Poian_Quiz_Admin_Inbox {

	public function register() {
		add_action( 'admin_post_poian_quiz_inbox_delete', array( $this, 'delete_entry' ) );
		add_action( 'admin_post_poian_quiz_inbox_save', array( $this, 'save_entry' ) );
		add_action( 'admin_post_poian_quiz_inbox_status', array( $this, 'change_status' ) );
		add_action( 'admin_post_poian_quiz_inbox_bulk', array( $this, 'bulk_action' ) );
		add_action( 'admin_post_poian_quiz_inbox_export', array( $this, 'export_csv' ) );
		// Dispatcher برای وقتی که action مستقیم از select WP_List_Table می‌آید
		add_action( 'admin_post_delete', array( $this, 'bulk_dispatch' ), 1 );
		add_action( 'admin_post_archive', array( $this, 'bulk_dispatch' ), 1 );
		add_action( 'admin_post_activate', array( $this, 'bulk_dispatch' ), 1 );
	}

	/**
	 * URL پایه برای بازگشت به صندوق ورودی یک فرم.
	 */
	private function inbox_url( $form_id, $extra = array() ) {
		return add_query_arg( array_merge( array(
			'page'    => Poian_Quiz_Admin::MAIN_SLUG,
			'view'    => 'inbox',
			'form_id' => (int) $form_id,
		), $extra ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL برای مشاهده یک ورودی.
	 */
	private function entry_url( $form_id, $attempt_id, $extra = array() ) {
		return add_query_arg( array_merge( array(
			'page'       => Poian_Quiz_Admin::MAIN_SLUG,
			'view'       => 'entry',
			'form_id'    => (int) $form_id,
			'attempt_id' => (int) $attempt_id,
		), $extra ), admin_url( 'admin.php' ) );
	}

	/**
	 * حذف یک ورودی (فقط attempt + scores، usermeta تغییر نمی‌کند).
	 */
	public function delete_entry() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$attempt_id = isset( $_REQUEST['attempt_id'] ) ? absint( $_REQUEST['attempt_id'] ) : 0;
		$form_id    = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
		check_admin_referer( 'pq_inbox_delete_' . $attempt_id );

		if ( $attempt_id ) {
			Poian_Quiz_Repository::delete_attempt( $attempt_id );
		}

		wp_safe_redirect( $this->inbox_url( $form_id, array( 'pq_notice' => 'deleted' ) ) );
		exit;
	}

	/**
	 * تغییر وضعیت ورودی (active/archived).
	 */
	public function change_status() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$attempt_id = isset( $_REQUEST['attempt_id'] ) ? absint( $_REQUEST['attempt_id'] ) : 0;
		$form_id    = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
		$status     = isset( $_REQUEST['status'] ) ? absint( $_REQUEST['status'] ) : 1;
		check_admin_referer( 'pq_inbox_status_' . $attempt_id );

		if ( $attempt_id && in_array( $status, array( 0, 1 ), true ) ) {
			Poian_Quiz_Repository::update_attempt_status( $attempt_id, $status );
		}

		wp_safe_redirect( $this->entry_url( $form_id, $attempt_id, array( 'pq_notice' => 'status_changed' ) ) );
		exit;
	}

	/**
	 * کارهای دسته‌جمعی (حذف/بایگانی/فعال‌سازی چند ورودی).
	 */
	public function bulk_action() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		check_admin_referer( 'bulk-entries' );

		$form_id     = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
		$action      = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		$attempt_ids = isset( $_REQUEST['entry'] ) ? array_map( 'absint', (array) $_REQUEST['entry'] ) : array();

		if ( empty( $attempt_ids ) ) {
			wp_safe_redirect( $this->inbox_url( $form_id ) );
			exit;
		}

		$affected = 0;
		foreach ( $attempt_ids as $aid ) {
			if ( 'delete' === $action ) {
				Poian_Quiz_Repository::delete_attempt( $aid );
				$affected++;
			} elseif ( 'archive' === $action ) {
				Poian_Quiz_Repository::update_attempt_status( $aid, 0 );
				$affected++;
			} elseif ( 'activate' === $action ) {
				Poian_Quiz_Repository::update_attempt_status( $aid, 1 );
				$affected++;
			}
		}

		wp_safe_redirect( $this->inbox_url( $form_id, array(
			'pq_notice' => 'bulk_done',
			'pq_count'  => $affected,
		) ) );
		exit;
	}

	/**
	 * Dispatcher برای درخواست‌های bulk که مستقیم از select WP_List_Table می‌آیند.
	 */
	public function bulk_dispatch() {
		if ( ! isset( $_REQUEST['form_id'] ) || ! isset( $_REQUEST['entry'] ) || ! is_array( $_REQUEST['entry'] ) ) {
			return;
		}
		$this->bulk_action();
	}

	/**
	 * خروجی CSV برای صندوق یک فرم.
	 */
	public function export_csv() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$form_id = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
		check_admin_referer( 'pq_inbox_export_' . $form_id );

		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			wp_die( esc_html__( 'فرم نامعتبر.', 'poian-quiz' ) );
		}

		$form   = get_post( $form_id );
		$schema = Poian_Quiz_Forms::get_schema( $form_id );
		$fields = Poian_Quiz_Schema::all_fields( $schema );

		$rows = Poian_Quiz_Repository::inbox_list( array(
			'form_id'  => $form_id,
			'search'   => '',
			'status'   => null,
			'orderby'  => 'id',
			'order'    => 'ASC',
			'per_page' => 99999,
			'offset'   => 0,
		) )['rows'];

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="poian-quiz-form-' . $form_id . '-' . date( 'Ymd-His' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );

		$fixed = array( '#', 'کاربر', 'موبایل', 'نتیجه', 'وضعیت', 'تاریخ' );
		$field_labels = array();
		foreach ( $fields as $fid => $f ) {
			if ( in_array( $f['type'], array( 'description', 'heading' ), true ) ) { continue; }
			$field_labels[ $fid ] = $f['title'] ?: $fid;
		}
		fputcsv( $out, array_merge( $fixed, array_values( $field_labels ) ) );

		foreach ( $rows as $r ) {
			$attempt_full = Poian_Quiz_Repository::get_attempt( (int) $r['id'] );
			$answers = is_array( $attempt_full['answers'] ) ? $attempt_full['answers'] : array();

			$line = array(
				(int) $r['id'],
				( (int) $r['user_id'] > 0 ) ? ( get_userdata( (int) $r['user_id'] )->display_name ?? '—' ) : 'مهمان',
				$r['mobile'] ?: '',
				$r['result_label'] ?: '',
				1 === (int) $r['status'] ? 'فعال' : 'بایگانی',
				date_i18n( 'Y-m-d H:i:s', strtotime( $r['created_at'] ), true ),
			);

			foreach ( array_keys( $field_labels ) as $fid ) {
				$val = isset( $answers[ $fid ] ) ? $answers[ $fid ] : '';
				if ( is_array( $val ) ) { $val = implode( '|', $val ); }
				$line[] = (string) $val;
			}
			fputcsv( $out, $line );
		}

		fclose( $out );
		exit;
	}

	/**
	 * ذخیره‌ی ویرایش ادمین روی answers و متاهای کاربر.
	 */
	public function save_entry() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;
		$form_id    = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		check_admin_referer( 'pq_inbox_save_' . $attempt_id );

		$attempt = Poian_Quiz_Repository::get_attempt( $attempt_id );
		if ( ! $attempt || (int) $attempt['form_id'] !== $form_id ) {
			wp_die( esc_html__( 'ورودی یافت نشد.', 'poian-quiz' ) );
		}

		$schema     = Poian_Quiz_Forms::get_schema( $form_id );
		$raw_answers = isset( $_POST['pq_answer'] ) ? wp_unslash( (array) $_POST['pq_answer'] ) : array();
		$normalized = array();
		foreach ( $raw_answers as $fid => $val ) {
			$normalized[ sanitize_key( $fid ) ] = $val;
		}
		if ( isset( $_POST['pq_rank'] ) && is_array( $_POST['pq_rank'] ) ) {
			foreach ( $_POST['pq_rank'] as $fid => $arr ) {
				$normalized[ sanitize_key( $fid ) ] = array_values( array_map( 'sanitize_text_field', (array) $arr ) );
			}
		}
		$clean = Poian_Quiz_Schema::validate_submission( $schema, $normalized );
		if ( is_wp_error( $clean ) ) {
			wp_die( esc_html( $clean->get_error_message() ) );
		}

		$engine = Poian_Quiz_Engine_Registry::get( Poian_Quiz_Forms::get_engine_id( $form_id ) );
		$result = $engine ? $engine->compute( $clean, $schema ) : array( 'scores' => array(), 'result_slug' => '', 'result_label' => '', 'extra' => array() );

		$meta_writes = array();
		if ( (int) $attempt['user_id'] > 0 ) {
			$actions  = Poian_Quiz_Forms::get_actions( $form_id );
			$declared = array();
			if ( ! empty( $actions['meta'] ) && is_array( $actions['meta'] ) ) {
				foreach ( $actions['meta'] as $m ) { if ( isset( $m['key'] ) ) { $declared[] = (string) $m['key']; } }
			}
			if ( isset( $_POST['pq_meta'] ) && is_array( $_POST['pq_meta'] ) ) {
				foreach ( $_POST['pq_meta'] as $k => $v ) {
					$key = sanitize_key( $k );
					if ( ! Poian_Quiz_Security::is_meta_key_allowed( $key, $declared ) ) { continue; }
					$meta_writes[ $key ] = is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_text_field( (string) $v );
				}
			}
		}

		Poian_Quiz_Repository::update_attempt(
			$attempt_id,
			$clean,
			$result,
			$attempt['user_id'],
			$meta_writes
		);

		wp_safe_redirect( $this->entry_url( $form_id, $attempt_id, array( 'pq_notice' => 'saved' ) ) );
		exit;
	}

	/* ---------------- صفحات ---------------- */

	public function render_list_page() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			wp_die( esc_html__( 'فرم نامعتبر.', 'poian-quiz' ) );
		}
		$pk_form    = get_post( $form_id );
		$pk_notice  = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
		$pk_list    = new Poian_Quiz_Admin_Inbox_List_Table( $form_id );
		$pk_list->prepare_items();
		$pk_stats   = Poian_Quiz_Repository::inbox_stats( $form_id );
		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-inbox-list.php';
	}

	public function render_view_page() {
		if ( ! Poian_Quiz_Security::user_can_manage() ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'poian-quiz' ), 403 ); }
		$form_id    = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$attempt_id = isset( $_GET['attempt_id'] ) ? absint( $_GET['attempt_id'] ) : 0;
		if ( ! $form_id || ! Poian_Quiz_Forms::is_valid_form( $form_id ) ) {
			wp_die( esc_html__( 'فرم نامعتبر.', 'poian-quiz' ) );
		}
		$attempt = $attempt_id ? Poian_Quiz_Repository::get_attempt( $attempt_id ) : null;
		if ( ! $attempt || (int) $attempt['form_id'] !== $form_id ) {
			wp_die( esc_html__( 'ورودی یافت نشد.', 'poian-quiz' ) );
		}
		$pk_form     = get_post( $form_id );
		$pk_schema   = Poian_Quiz_Forms::get_schema( $form_id );
		$pk_actions  = Poian_Quiz_Forms::get_actions( $form_id );
		$pk_notice   = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
		$pk_usermeta = array();
		if ( (int) $attempt['user_id'] > 0 && ! empty( $pk_actions['meta'] ) && is_array( $pk_actions['meta'] ) ) {
			foreach ( $pk_actions['meta'] as $m ) {
				if ( empty( $m['key'] ) ) { continue; }
				$pk_usermeta[ $m['key'] ] = get_user_meta( (int) $attempt['user_id'], $m['key'], true );
			}
		}
		include POIAN_QUIZ_PLUGIN_DIR . 'templates/admin/poian-quiz-inbox-view.php';
	}
}

/* ---------------- لیست جدولی ورودی‌ها ---------------- */

final class Poian_Quiz_Admin_Inbox_List_Table extends WP_List_Table {

	private $form_id;

	public function __construct( $form_id ) {
		$this->form_id = (int) $form_id;
		parent::__construct( array( 'singular' => 'entry', 'plural' => 'entries', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'user'        => __( 'کاربر', 'poian-quiz' ),
			'mobile'      => __( 'موبایل', 'poian-quiz' ),
			'result'      => __( 'نتیجه', 'poian-quiz' ),
			'status'      => __( 'وضعیت', 'poian-quiz' ),
			'created_at'  => __( 'تاریخ', 'poian-quiz' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'user'       => array( 'display_name', false ),
			'created_at' => array( 'created_at', false ),
		);
	}

	public function prepare_items() {
		$per_page = 20;
		$current  = $this->get_pagenum();
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status   = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'all';
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		$order    = ( isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';

		$allowed_orderby = array( 'id', 'display_name', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) { $orderby = 'id'; }

		$args = array(
			'form_id'  => $this->form_id,
			'search'   => $search,
			'status'   => ( 'active' === $status ? 1 : ( 'archived' === $status ? 0 : null ) ),
			'orderby'  => $orderby,
			'order'    => $order,
			'per_page' => $per_page,
			'offset'   => ( $current - 1 ) * $per_page,
		);

		$res = Poian_Quiz_Repository::inbox_list( $args );
		$this->items = $res['rows'];
		$total_items = $res['total'];
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	public function get_views() {
		$base  = add_query_arg( array(
			'page'    => Poian_Quiz_Admin::MAIN_SLUG,
			'view'    => 'inbox',
			'form_id' => $this->form_id,
		), admin_url( 'admin.php' ) );
		$stats = Poian_Quiz_Repository::inbox_stats( $this->form_id );
		$cur   = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'all';
		$class = function( $k ) use ( $cur ) { return $cur === $k ? ' class="current"' : ''; };
		return array(
			'all'      => '<a href="' . esc_url( $base ) . '"' . $class( 'all' ) . '>' . __( 'همه', 'poian-quiz' ) . ' <span class="count">(' . (int) $stats['all'] . ')</span></a>',
			'active'   => '<a href="' . esc_url( add_query_arg( 'status', 'active', $base ) ) . '"' . $class( 'active' ) . '>' . __( 'فعال', 'poian-quiz' ) . ' <span class="count">(' . (int) $stats['active'] . ')</span></a>',
			'archived' => '<a href="' . esc_url( add_query_arg( 'status', 'archived', $base ) ) . '"' . $class( 'archived' ) . '>' . __( 'بایگانی', 'poian-quiz' ) . ' <span class="count">(' . (int) $stats['archived'] . ')</span></a>',
		);
	}

	public function get_bulk_actions() {
		return array(
			'delete'   => __( 'حذف', 'poian-quiz' ),
			'archive'  => __( 'بایگانی', 'poian-quiz' ),
			'activate' => __( 'فعال کردن', 'poian-quiz' ),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry[]" value="%d" />', (int) $item['id'] );
	}

	public function column_user( $item ) {
		$view = add_query_arg( array(
			'page'       => Poian_Quiz_Admin::MAIN_SLUG,
			'view'       => 'entry',
			'form_id'    => $this->form_id,
			'attempt_id' => (int) $item['id'],
		), admin_url( 'admin.php' ) );
		$del  = wp_nonce_url( add_query_arg( array(
			'action'     => 'poian_quiz_inbox_delete',
			'form_id'    => $this->form_id,
			'attempt_id' => (int) $item['id'],
		), admin_url( 'admin-post.php' ) ), 'pq_inbox_delete_' . (int) $item['id'] );

		$actions = array(
			'view'   => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'مشاهده', 'poian-quiz' ) . '</a>',
			'delete' => '<a class="pq-danger" data-pq-confirm="' . esc_attr__( 'این ورودی حذف شود؟', 'poian-quiz' ) . '" href="' . esc_url( $del ) . '">' . esc_html__( 'حذف', 'poian-quiz' ) . '</a>',
		);

		if ( (int) $item['user_id'] > 0 ) {
			$u    = get_userdata( (int) $item['user_id'] );
			$name = $u ? esc_html( $u->display_name ) : '—';
		} else {
			$name = '<em>' . esc_html__( 'مهمان', 'poian-quiz' ) . '</em>';
		}

		return '<strong>' . $name . '</strong>' . $this->row_actions( $actions );
	}

	public function column_mobile( $item ) {
		return esc_html( $item['mobile'] ?: '—' );
	}

	public function column_result( $item ) {
		return esc_html( $item['result_label'] ?: '—' );
	}

	public function column_status( $item ) {
		return 1 === (int) $item['status']
			? '<span class="pq-status pq-status-active">' . esc_html__( 'فعال', 'poian-quiz' ) . '</span>'
			: '<span class="pq-status pq-status-archived">' . esc_html__( 'بایگانی', 'poian-quiz' ) . '</span>';
	}

	public function column_created_at( $item ) {
		$tz = new DateTimeZone( 'Asia/Tehran' );
		return esc_html( wp_date( 'Y/m/d - H:i', strtotime( $item['created_at'] ), $tz ) );
	}

	public function no_items() {
		esc_html_e( 'ورودی‌ای ثبت نشده است.', 'poian-quiz' );
	}
}
