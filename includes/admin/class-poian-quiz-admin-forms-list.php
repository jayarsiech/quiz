<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class Poian_Quiz_Admin_Forms_List extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'form', 'plural' => 'forms', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'title'     => __( 'فرم', 'poian-quiz' ),
			'engine'    => __( 'موتور', 'poian-quiz' ),
			'shortcode' => __( 'شورت‌کد', 'poian-quiz' ),
			'count'     => __( 'ثبت‌ها', 'poian-quiz' ),
			'date'      => __( 'تاریخ', 'poian-quiz' ),
		);
	}

	public function prepare_items() {
		$per_page = 20;
		$current  = $this->get_pagenum();
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$q = new WP_Query( array(
			'post_type'      => POIAN_QUIZ_CPT,
			'post_status'    => 'publish',
			's'              => $search,
			'paged'          => $current,
			'posts_per_page' => $per_page,
			'no_found_rows'  => false,
		) );

		// کوئری تجمعی برای همه فرم‌ها (نه فقط صفحه فعلی)
		// کوئری تجمعی برای همه فرم‌ها (همه ورودی‌ها بدون فیلتر status)
		global $wpdb;
		$all_counts = array();
		$rows = $wpdb->get_results( "SELECT form_id, COUNT(*) AS c FROM {$wpdb->prefix}poian_quiz_attempts GROUP BY form_id", ARRAY_A );
		foreach ( $rows as $r ) { $all_counts[ (int) $r['form_id'] ] = (int) $r['c']; }
		$this->pq_counts = $all_counts;
		$this->items = $q->posts;
		$this->set_pagination_args( array( 'total_items' => (int) $q->found_posts, 'per_page' => $per_page ) );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}
	public function column_title( $item ) {
		$edit   = add_query_arg( array(
			'page'    => Poian_Quiz_Admin::MAIN_SLUG,
			'view'    => 'editor',
			'form_id' => $item->ID,
		), admin_url( 'admin.php' ) );
		$inbox  = add_query_arg( array(
			'page'    => Poian_Quiz_Admin::MAIN_SLUG,
			'view'    => 'inbox',
			'form_id' => $item->ID,
		), admin_url( 'admin.php' ) );
		$settings = add_query_arg( array(
			'page'    => Poian_Quiz_Admin::MAIN_SLUG,
			'view'    => 'settings',
			'form_id' => $item->ID,
		), admin_url( 'admin.php' ) );
		$del    = wp_nonce_url( add_query_arg( array( 'action' => 'poian_quiz_delete_form', 'form_id' => $item->ID ), admin_url( 'admin-post.php' ) ), 'poian_quiz_delete_' . $item->ID );

		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}poian_quiz_attempts WHERE form_id = %d",
			(int) $item->ID
		) );

		$actions = array(
			'edit'     => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'ویرایش', 'poian-quiz' ) . '</a>',
			'settings' => '<a href="' . esc_url( $settings ) . '">' . esc_html__( 'تنظیمات', 'poian-quiz' ) . '</a>',
			'inbox'    => '<a href="' . esc_url( $inbox ) . '">' . esc_html__( 'صندوق ورودی‌ها', 'poian-quiz' ) . ' <span class="pq-inbox-count">(' . number_format_i18n( $count ) . ')</span></a>',
			'delete'   => '<a class="pq-danger" data-pq-confirm="' . esc_attr__( 'این فرم حذف شود؟ ثبت‌ها حفظ می‌مانند.', 'poian-quiz' ) . '" href="' . esc_url( $del ) . '">' . esc_html__( 'حذف', 'poian-quiz' ) . '</a>',
		);
		return '<strong>' . esc_html( get_the_title( $item ) ) . '</strong>' . $this->row_actions( $actions );
	}
	public function column_engine( $item ) {
		$e = Poian_Quiz_Engine_Registry::get( Poian_Quiz_Forms::get_engine_id( $item->ID ) );
		return $e ? esc_html( $e->get_title() ) : '—';
	}

	public function column_shortcode( $item ) {
		return '<code class="pq-shortcode">[poian_quiz id="' . (int) $item->ID . '"]</code>';
	}

	public function column_count( $item ) {
		// خواندن مستقیم برای اطمینان از صحت (fallback اگر pq_counts پر نشده)
		if ( isset( $this->pq_counts[ $item->ID ] ) ) {
			return number_format_i18n( $this->pq_counts[ $item->ID ] );
		}
		global $wpdb;
		$c = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}poian_quiz_attempts WHERE form_id = %d",
			(int) $item->ID
		) );
		return number_format_i18n( $c );
	}

	public function column_date( $item ) {
		return esc_html( get_the_date( 'Y/m/d', $item ) );
	}

	public function no_items() {
		esc_html_e( 'فرمی یافت نشد.', 'poian-quiz' );
	}
}
