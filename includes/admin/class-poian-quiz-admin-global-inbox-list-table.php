<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * لیست ورودی‌های همه فرم‌ها (Global Inbox).
 */
final class Poian_Quiz_Admin_Global_Inbox_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'entry', 'plural' => 'entries', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'form'       => __( 'فرم', 'poian-quiz' ),
			'user'       => __( 'کاربر', 'poian-quiz' ),
			'mobile'     => __( 'موبایل', 'poian-quiz' ),
			'result'     => __( 'نتیجه', 'poian-quiz' ),
			'status'     => __( 'وضعیت', 'poian-quiz' ),
			'created_at' => __( 'تاریخ', 'poian-quiz' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'form'       => array( 'form_title', false ),
			'user'       => array( 'display_name', false ),
			'created_at' => array( 'created_at', false ),
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

	public function prepare_items() {
		$per_page = 20;
		$current  = $this->get_pagenum();
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status   = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'all';
		$form_id  = isset( $_REQUEST['filter_form'] ) ? absint( $_REQUEST['filter_form'] ) : 0;
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		$order    = ( isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';

		$allowed_orderby = array( 'id', 'form_title', 'display_name', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) { $orderby = 'id'; }

		$args = array(
			'form_id'  => $form_id > 0 ? $form_id : null,
			'search'   => $search,
			'status'   => ( 'active' === $status ? 1 : ( 'archived' === $status ? 0 : null ) ),
			'orderby'  => $orderby,
			'order'    => $order,
			'per_page' => $per_page,
			'offset'   => ( $current - 1 ) * $per_page,
		);

		$res = Poian_Quiz_Repository::global_inbox_list( $args );
		$this->items = $res['rows'];
		$total_items = $res['total'];
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) { return; }
		$forms = get_posts( array(
			'post_type'      => POIAN_QUIZ_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$current_form = isset( $_REQUEST['filter_form'] ) ? absint( $_REQUEST['filter_form'] ) : 0;
		echo '<div class="alignleft actions">';
		echo '<select name="filter_form">';
		echo '<option value="0">' . esc_html__( 'همه فرم‌ها', 'poian-quiz' ) . '</option>';
		foreach ( $forms as $f ) {
			printf( '<option value="%d" %s>%s</option>', (int) $f->ID, selected( $current_form, $f->ID, false ), esc_html( $f->post_title ) );
		}
		echo '</select>';
		submit_button( __( 'فیلتر', 'poian-quiz' ), 'action', '', 'button' );
		echo '</div>';
	}

	public function column_form( $item ) {
		$form = get_post( (int) $item['form_id'] );
		return $form ? esc_html( $form->post_title ) : '—';
	}

	public function column_user( $item ) {
		$view = add_query_arg( array(
			'page'       => Poian_Quiz_Admin::MAIN_SLUG,
			'view'       => 'entry',
			'form_id'    => (int) $item['form_id'],
			'attempt_id' => (int) $item['id'],
		), admin_url( 'admin.php' ) );
		$del  = wp_nonce_url( add_query_arg( array(
			'action'     => 'poian_quiz_inbox_delete',
			'form_id'    => (int) $item['form_id'],
			'attempt_id' => (int) $item['id'],
		), admin_url( 'admin-post.php' ) ), 'pq_inbox_delete_' . (int) $item['id'] );
		$actions = array(
			'view'   => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'مشاهده', 'poian-quiz' ) . '</a>',
			'delete' => '<a class="pq-danger" data-pq-confirm="' . esc_attr__( 'حذف شود؟', 'poian-quiz' ) . '" href="' . esc_url( $del ) . '">' . esc_html__( 'حذف', 'poian-quiz' ) . '</a>',
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
		esc_html_e( 'ورودی‌ای یافت نشد.', 'poian-quiz' );
	}
} 
