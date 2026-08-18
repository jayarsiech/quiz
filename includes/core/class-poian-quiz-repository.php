<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Repository {

	public static function table_attempts() {
		global $wpdb;
		return $wpdb->prefix . 'poian_quiz_attempts';
	}

	public static function table_scores() {
		global $wpdb;
		return $wpdb->prefix . 'poian_quiz_scores';
	}
	/**
	 * لیست ورودی‌های همه فرم‌ها (Global Inbox) با join به جدول posts برای عنوان فرم.
	 */
	public static function global_inbox_list( array $args ) {
		global $wpdb;
		$tbl_a = self::table_attempts();
		$tbl_p = $wpdb->posts;

		$where = array( '1=1' );
		$vals  = array();

		if ( ! empty( $args['form_id'] ) ) {
			$where[] = 'a.form_id = %d';
			$vals[]  = (int) $args['form_id'];
		}
		if ( null !== $args['status'] ) {
			$where[] = 'a.status = %d';
			$vals[]  = (int) $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = '(a.display_name LIKE %s OR a.mobile LIKE %s OR a.result_label LIKE %s OR p.post_title LIKE %s)';
			$vals    = array_merge( $vals, array( $like, $like, $like, $like ) );
		}
		$where_sql = implode( ' AND ', $where );

		$allowed_orderby = array(
			'id'           => 'a.id',
			'form_title'   => 'p.post_title',
			'display_name' => 'a.display_name',
			'created_at'   => 'a.created_at',
		);
		$orderby = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 'a.id';
		$order   = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$tbl_a} AS a LEFT JOIN {$tbl_p} AS p ON a.form_id = p.ID WHERE {$where_sql}",
			$vals
		) );

		$vals[] = (int) $args['per_page'];
		$vals[] = (int) $args['offset'];
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.id, a.form_id, a.user_id, a.actor_key, a.mobile, a.display_name, a.result_slug, a.result_label, a.status, a.created_at, p.post_title AS form_title FROM {$tbl_a} AS a LEFT JOIN {$tbl_p} AS p ON a.form_id = p.ID WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$vals
		), ARRAY_A );

		return array( 'rows' => $rows ? $rows : array(), 'total' => $total );
	}
	/**
	 * درج تراکنشی: attempt + scores + متاهای وایت‌لیست‌شده = «یا کامل یا هیچی».
	 *
	 * @return int|WP_Error
	 */
	public static function submit_transactional( array $attempt, array $scores, array $meta_writes ) {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		$ok = $wpdb->insert( self::table_attempts(), array(
			'form_id'      => (int) $attempt['form_id'],
			'form_version' => (int) $attempt['form_version'],
			'user_id'      => (int) $attempt['user_id'],
			'actor_key'    => $attempt['actor_key'],
			'mobile'       => $attempt['mobile'],
			'display_name' => $attempt['display_name'],
			'answers_json' => wp_json_encode( $attempt['answers'] ),
			'result_json'  => wp_json_encode( $attempt['result'] ),
			'result_slug'  => (string) $attempt['result_slug'],
			'result_label' => (string) $attempt['result_label'],
			'status'       => 1,
			'created_at'   => current_time( 'mysql', true ),
		), array( '%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s' ) );

		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db', __( 'خطا در ذخیره‌سازی.', 'poian-quiz' ), array( 'status' => 500 ) );
		}
		$attempt_id = (int) $wpdb->insert_id;

		foreach ( $scores as $dim => $value ) {
			$ok = $wpdb->insert( self::table_scores(), array(
				'attempt_id' => $attempt_id,
				'form_id'    => (int) $attempt['form_id'],
				'user_id'    => (int) $attempt['user_id'],
				'actor_key'  => $attempt['actor_key'],
				'dim_key'    => (string) $dim,
				'dim_value'  => (float) $value,
			), array( '%d','%d','%d','%s','%s','%f' ) );
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'db', __( 'خطا در ذخیره‌سازی.', 'poian-quiz' ), array( 'status' => 500 ) );
			}
		}

		// متاهای کاربر (فقط لاگین) — داخل همان تراکنش
		foreach ( $meta_writes as $key => $val ) {
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => (int) $attempt['user_id'], 'meta_key' => $key ), array( '%d','%s' ) );
			$ok = $wpdb->insert( $wpdb->usermeta, array(
				'user_id'    => (int) $attempt['user_id'],
				'meta_key'   => $key,
				'meta_value' => (string) $val,
			), array( '%d','%s','%s' ) );
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'meta_failed', __( 'ذخیره متا ناموفق بود؛ کل ثبت لغو شد.', 'poian-quiz' ), array( 'status' => 500 ) );
			}
		}

		$wpdb->query( 'COMMIT' );

		if ( (int) $attempt['user_id'] > 0 ) {
			clean_user_cache( (int) $attempt['user_id'] );
			wp_cache_delete( (int) $attempt['user_id'], 'user_meta' );
		}
		return $attempt_id;
	}

	public static function get_latest( $form_id, $actor_key ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . self::table_attempts() . " WHERE form_id = %d AND actor_key = %s AND status = 1 ORDER BY created_at DESC, id DESC LIMIT 1",
			(int) $form_id, $actor_key
		), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function count_by_actor( $form_id, $actor_key ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table_attempts() . " WHERE form_id = %d AND actor_key = %s AND status = 1",
			(int) $form_id, $actor_key
		) );
	}

	public static function get_attempt( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table_attempts() . " WHERE id = %d", (int) $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}
		/**
	 * دریافت تاریخچه تلاش‌های یک کاربر در یک فرم.
	 *
	 * @param int    $form_id
	 * @param string $actor_key
	 * @param int    $limit
	 * @return array
	 */
	public static function get_user_history( $form_id, $actor_key, $limit = 5 ) {
		global $wpdb;

		$limit = max( 1, min( 100, (int) $limit ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, result_slug, result_label, result_json, created_at 
			 FROM " . self::table_attempts() . "
			 WHERE form_id = %d AND actor_key = %s
			 ORDER BY created_at DESC, id DESC
			 LIMIT %d",
			(int) $form_id,
			(string) $actor_key,
			$limit
		), ARRAY_A );

		if ( empty( $rows ) ) { return array(); }

		$out = array();
		foreach ( $rows as $r ) {
			$result = json_decode( (string) $r['result_json'], true );
			$out[] = array(
				'id'         => (int) $r['id'],
				'slug'       => (string) $r['result_slug'],
				'label'      => (string) $r['result_label'],
				'scores'     => isset( $result['scores'] ) && is_array( $result['scores'] ) ? $result['scores'] : array(),
				'created_at' => (string) $r['created_at'],
			);
		}
		return $out;
	}
	/**
	 * لیست اینباکس یک فرم با جستجو/فیلتر/مرتب‌سازی.
	 *
	 * @return array{rows:array, total:int}
	 */
	public static function inbox_list( array $args ) {
		global $wpdb;
		$where = array( 'form_id = %d' );
		$vals  = array( (int) $args['form_id'] );

		if ( null !== $args['status'] ) {
			$where[] = 'status = %d';
			$vals[]  = (int) $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = '(display_name LIKE %s OR mobile LIKE %s OR result_label LIKE %s OR actor_key LIKE %s)';
			$vals    = array_merge( $vals, array( $like, $like, $like, $like ) );
		}
		$where_sql = implode( ' AND ', $where );

		$allowed_orderby = array( 'id' => 'id', 'display_name' => 'display_name', 'created_at' => 'created_at' );
		$orderby         = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : 'id';
		$order           = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::table_attempts() . " WHERE {$where_sql}", $vals ) );

		$vals[] = (int) $args['per_page'];
		$vals[] = (int) $args['offset'];
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, form_id, user_id, actor_key, mobile, display_name, result_slug, result_label, status, created_at FROM " . self::table_attempts() . " WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$vals
		), ARRAY_A );

		return array( 'rows' => $rows ? $rows : array(), 'total' => $total );
	}

	/**
	 * آمار سه‌تایی برای اینباکس (یک کوئری، بدون N+1).
	 */
	public static function inbox_stats( $form_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS all_n, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS active_n, SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) AS archived_n FROM " . self::table_attempts() . " WHERE form_id = %d",
			(int) $form_id
		), ARRAY_A );
		return array(
			'all'      => isset( $row['all_n'] ) ? (int) $row['all_n'] : 0,
			'active'   => isset( $row['active_n'] ) ? (int) $row['active_n'] : 0,
			'archived' => isset( $row['archived_n'] ) ? (int) $row['archived_n'] : 0,
		);
	}

	/**
	 * تغییر وضعیت یک ورودی (فقط attempts، scores دست‌نخورده).
	 */
	public static function update_attempt_status( $attempt_id, $status ) {
		global $wpdb;
		$wpdb->update(
			self::table_attempts(),
			array( 'status' => (int) $status ),
			array( 'id' => (int) $attempt_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * حذف یک ورودی + همه score هایش در یک تراکنش.
	 */
	public static function delete_attempt( $attempt_id ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->delete( self::table_scores(), array( 'attempt_id' => (int) $attempt_id ), array( '%d' ) );
		$wpdb->delete( self::table_attempts(), array( 'id' => (int) $attempt_id ), array( '%d' ) );
		$wpdb->query( 'COMMIT' );
	}

	/**
	 * ویرایش ورودی (پاسخ + متا) در تراکنش؛ scores از موتور فعلی بازسازی می‌شوند.
	 */
	public static function update_attempt( $attempt_id, array $clean_answers, array $result, $user_id, array $meta_writes ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		$ok = $wpdb->update(
			self::table_attempts(),
			array(
				'answers_json' => wp_json_encode( $clean_answers ),
				'result_json'  => wp_json_encode( $result ),
				'result_slug'  => isset( $result['result_slug'] ) ? $result['result_slug'] : '',
				'result_label' => isset( $result['result_label'] ) ? $result['result_label'] : '',
			),
			array( 'id' => (int) $attempt_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db', __( 'خطا در به‌روزرسانی.', 'poian-quiz' ), array( 'status' => 500 ) );
		}

		// scores: حذف قدیمی‌ها + درج جدید
		$wpdb->delete( self::table_scores(), array( 'attempt_id' => (int) $attempt_id ), array( '%d' ) );
		$form_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM " . self::table_attempts() . " WHERE id = %d", (int) $attempt_id ) );
		$scores  = isset( $result['scores'] ) && is_array( $result['scores'] ) ? $result['scores'] : array();
		foreach ( $scores as $dim => $val ) {
			$wpdb->insert( self::table_scores(), array(
				'attempt_id' => (int) $attempt_id,
				'form_id'    => $form_id,
				'user_id'    => $user_id,
				'actor_key'  => 'u:' . $user_id,
				'dim_key'    => (string) $dim,
				'dim_value'  => (float) $val,
			), array( '%d','%d','%d','%s','%s','%f' ) );
		}

		// متاها (فقط کاربر لاگین)
		foreach ( $meta_writes as $key => $val ) {
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => $key ), array( '%d','%s' ) );
			$wpdb->insert( $wpdb->usermeta, array(
				'user_id'    => $user_id,
				'meta_key'   => $key,
				'meta_value' => is_array( $val ) ? wp_json_encode( $val ) : (string) $val,
			), array( '%d','%s','%s' ) );
		}

		$wpdb->query( 'COMMIT' );
		if ( $user_id > 0 ) {
			clean_user_cache( $user_id );
			wp_cache_delete( $user_id, 'user_meta' );
		}
		return true;
	}

	private static function hydrate( array $row ) {
		foreach ( array( 'id','form_id','form_version','user_id','status' ) as $k ) {
			$row[ $k ] = (int) $row[ $k ];
		}
		$row['answers'] = json_decode( (string) $row['answers_json'], true );
		$row['result']  = json_decode( (string) $row['result_json'], true );
		return $row;
	}
	
}
