<?php
defined( 'ABSPATH' ) || exit;

final class Poian_Quiz_Schema {

	/**
	 * اعتبارسنجی payload بر اساس اسکیما + بازتولید سروری شرط‌ها.
	 * @return array|WP_Error
	 */
	public static function validate_submission( array $schema, array $raw ) {
		$clean  = array();
		$fields = self::all_fields( $schema );

		foreach ( $fields as $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : '';
			if ( in_array( $type, array( 'description', 'heading' ), true ) ) {
				continue;
			}
			// فیلد پنهان (شرط برقرار نیست) → کاملاً نادیده (اجباری هم نمی‌شکند)
			if ( ! self::conditions_met( $field, $raw ) ) {
				continue;
			}

			$fid   = (string) $field['id'];
			$value = isset( $raw[ $fid ] ) ? $raw[ $fid ] : null;
			$empty = ( null === $value || '' === $value || array() === $value );

			if ( ! empty( $field['required'] ) && $empty ) {
				return new WP_Error( 'required', __( 'لطفاً همه فیلدهای الزامی را تکمیل کنید.', 'poian-quiz' ), array( 'status' => 422 ) );
			}
			if ( $empty ) {
				continue;
			}

			$option_keys = array();
			if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $o ) {
					if ( isset( $o['key'] ) ) { $option_keys[] = (string) $o['key']; }
				}
			}

			switch ( $type ) {
				case 'radio':
					if ( ! is_string( $value ) && ! is_numeric( $value ) ) { return self::bad(); }
					if ( ! in_array( (string) $value, $option_keys, true ) ) { return self::bad(); }
					$clean[ $fid ] = (string) $value;
					break;
		        case 'select':
						if ( ! is_string( $value ) && ! is_numeric( $value ) ) { return self::bad(); }
						if ( ! in_array( (string) $value, $option_keys, true ) ) { return self::bad(); }
						$clean[ $fid ] = (string) $value;
						break;
				case 'checkbox':
					if ( ! is_array( $value ) ) { return self::bad(); }
					$vals = array_values( array_map( 'strval', $value ) );
					if ( array_diff( $vals, $option_keys ) ) { return self::bad(); }
					$clean[ $fid ] = $vals;
					break;

				case 'rank':
					if ( ! is_array( $value ) ) { return self::bad(); }
					$vals   = array_values( array_map( 'strval', $value ) );
					$sorted = $vals; sort( $sorted, SORT_STRING );
					$expect = $option_keys; sort( $expect, SORT_STRING );
					if ( $sorted !== $expect ) { return self::bad(); }
					$clean[ $fid ] = $vals;
					break;

				case 'text':
					$clean[ $fid ] = sanitize_text_field( (string) $value );
					break;

				case 'textarea':
					$clean[ $fid ] = sanitize_textarea_field( (string) $value );
					break;

				default:
					return self::bad();
			}
		}
		return $clean;
	}

	public static function all_fields( array $schema ) {
		$out = array();

		// ساختار جدید: آرایه fields در ریشه
		if ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) {
			foreach ( $schema['fields'] as $f ) {
				if ( isset( $f['id'] ) ) { $out[ (string) $f['id'] ] = $f; }
			}
			return $out;
		}

		// Backward compatibility: ساختار قدیم (pages)
		if ( isset( $schema['pages'] ) && is_array( $schema['pages'] ) ) {
			foreach ( $schema['pages'] as $page ) {
				if ( isset( $page['fields'] ) && is_array( $page['fields'] ) ) {
					foreach ( $page['fields'] as $f ) {
						if ( isset( $f['id'] ) ) { $out[ (string) $f['id'] ] = $f; }
					}
				}
			}
		}
		return $out;
	}

	/**
	 * ارزیابی شرط‌های نمایش یک فیلد.
	 * - منطق `all`: همه شرط‌ها باید برقرار باشند (AND)
	 * - منطق `any`: حداقل یک شرط باید برقرار باشد (OR)
	 */
	private static function conditions_met( array $field, array $raw ) {
		if ( empty( $field['conditions'] ) || ! is_array( $field['conditions'] ) ) {
			return true;
		}

		$logic  = isset( $field['condition_logic'] ) && 'any' === $field['condition_logic'] ? 'any' : 'all';
		$action = isset( $field['condition_action'] ) && 'hide' === $field['condition_action'] ? 'hide' : 'show';

		$results = array();
		foreach ( $field['conditions'] as $c ) {
			if ( ! isset( $c['field'], $c['op'] ) ) { continue; }
			$results[] = self::eval_condition( $c, $raw );
		}

		if ( empty( $results ) ) { return true; }

		$cond_ok = 'any' === $logic
			? in_array( true, $results, true )
			: ! in_array( false, $results, true );

		// show: اگر شرط برقرار بود → نمایش بده (true)
		// hide: اگر شرط برقرار بود → مخفی کن (false)
		return 'show' === $action ? $cond_ok : ! $cond_ok;
	}
	/**
	 * ارزیابی یک شرط واحد.
	 *
	 * @param array $c  شرط {field, op, values}
	 * @param array $raw پاسخ‌های کاربر
	 * @return bool
	 */
	private static function eval_condition( array $c, array $raw ) {
		$fid = (string) $c['field'];
		$op  = (string) $c['op'];
		$fv  = isset( $raw[ $fid ] ) ? $raw[ $fid ] : null;
		$values = isset( $c['values'] ) ? array_map( 'strval', (array) $c['values'] ) : array();

		// نرمال‌سازی مقدار فیلد
		if ( is_array( $fv ) ) {
			$fv_arr = array_map( 'strval', $fv );
		} else {
			$fv_arr = ( null === $fv || '' === $fv ) ? array() : array( (string) $fv );
		}
		$is_empty = empty( $fv_arr );

		switch ( $op ) {
			case 'is':
				return (bool) array_intersect( $fv_arr, $values );
			case 'not':
				return ! array_intersect( $fv_arr, $values );
			case 'empty':
				return $is_empty;
			case 'not_empty':
				return ! $is_empty;
			case 'contains':
				foreach ( $fv_arr as $v ) {
					foreach ( $values as $needle ) {
						if ( '' !== $needle && false !== mb_stripos( $v, $needle ) ) { return true; }
					}
				}
				return false;
			case 'not_contains':
				foreach ( $fv_arr as $v ) {
					foreach ( $values as $needle ) {
						if ( '' !== $needle && false !== mb_stripos( $v, $needle ) ) { return false; }
					}
				}
				return true;
			case 'starts_with':
				foreach ( $fv_arr as $v ) {
					foreach ( $values as $prefix ) {
						if ( '' !== $prefix && 0 === mb_stripos( $v, $prefix ) ) { return true; }
					}
				}
				return false;
			case 'ends_with':
				foreach ( $fv_arr as $v ) {
					foreach ( $values as $suffix ) {
						if ( '' !== $suffix && mb_strlen( $v ) >= mb_strlen( $suffix ) && mb_substr( $v, -mb_strlen( $suffix ) ) === $suffix ) { return true; }
					}
				}
				return false;
			case 'gt':
				$num = is_numeric( $fv ) ? (float) $fv : null;
				$cmp = isset( $values[0] ) && is_numeric( $values[0] ) ? (float) $values[0] : null;
				return null !== $num && null !== $cmp && $num > $cmp;
			case 'gte':
				$num = is_numeric( $fv ) ? (float) $fv : null;
				$cmp = isset( $values[0] ) && is_numeric( $values[0] ) ? (float) $values[0] : null;
				return null !== $num && null !== $cmp && $num >= $cmp;
			case 'lt':
				$num = is_numeric( $fv ) ? (float) $fv : null;
				$cmp = isset( $values[0] ) && is_numeric( $values[0] ) ? (float) $values[0] : null;
				return null !== $num && null !== $cmp && $num < $cmp;
			case 'lte':
				$num = is_numeric( $fv ) ? (float) $fv : null;
				$cmp = isset( $values[0] ) && is_numeric( $values[0] ) ? (float) $values[0] : null;
				return null !== $num && null !== $cmp && $num <= $cmp;
			default:
				return true;
		}
	}

	/**
	 * sanitize کامل اسکیما (فقط ادمین، با cap) — وایت‌لیست نوع فیلد/کلیدها.
	 * ساختار جدید: آرایه fields در ریشه (نه pages).
	 */
	public static function sanitize_schema( array $raw ) {
		$types = array( 'radio', 'checkbox', 'select', 'rank', 'text', 'textarea', 'description', 'heading', 'page_break' );
		$out   = array(
			'title'  => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
			'fields' => array(),
		);
		$fields = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array();

		foreach ( $fields as $f ) {
			$type = in_array( isset( $f['type'] ) ? $f['type'] : '', $types, true ) ? $f['type'] : 'text';

			$field = array(
				'id'          => ! empty( $f['id'] ) ? sanitize_key( $f['id'] ) : 'f' . wp_generate_password( 6, false ),
				'type'        => $type,
				'title'       => isset( $f['title'] ) ? sanitize_text_field( $f['title'] ) : '',
				'description' => isset( $f['description'] ) ? sanitize_textarea_field( $f['description'] ) : '',
				'required'    => ! empty( $f['required'] ) ? 1 : 0,
				'dim'         => isset( $f['dim'] ) ? sanitize_key( $f['dim'] ) : '',
				'options'     => array(),
				'conditions'  => array(),
			);

			// فیلدهای ویژه page_break
				// فیلدهای ویژه page_break
			if ( 'page_break' === $type ) {
				$field['heading']      = isset( $f['heading'] ) ? sanitize_text_field( $f['heading'] ) : '';
				$field['description']  = isset( $f['description'] ) ? sanitize_textarea_field( $f['description'] ) : '';
				$field['next_label']   = isset( $f['next_label'] ) ? sanitize_text_field( $f['next_label'] ) : '';
				$field['prev_label']   = isset( $f['prev_label'] ) ? sanitize_text_field( $f['prev_label'] ) : '';
				$field['show_progress'] = isset( $f['show_progress'] ) ? sanitize_text_field( $f['show_progress'] ) : 'inherit';
			}

			// شماره سوال (فقط برای فیلدهای سوال شماره‌دار)
			if ( isset( $f['question_number'] ) ) {
				$field['question_number'] = max( 0, absint( $f['question_number'] ) );
			}

			// گزینه‌ها
			if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
				foreach ( $f['options'] as $o ) {
					$field['options'][] = array(
						'key'    => ! empty( $o['key'] ) ? sanitize_key( $o['key'] ) : 'o' . wp_generate_password( 4, false ),
						'label'  => isset( $o['label'] ) ? sanitize_text_field( $o['label'] ) : '',
						'weight' => isset( $o['weight'] ) ? (float) $o['weight'] : 0,
					);
				}
			}

			// شرط‌های نمایش
			if ( isset( $f['conditions'] ) && is_array( $f['conditions'] ) ) {
				$field['condition_logic'] = ( isset( $f['condition_logic'] ) && 'any' === $f['condition_logic'] ) ? 'any' : 'all';
				$field['condition_action'] = ( isset( $f['condition_action'] ) && 'hide' === $f['condition_action'] ) ? 'hide' : 'show';
				foreach ( $f['conditions'] as $c ) {
					$allowed_ops = array( 'is', 'not', 'empty', 'not_empty', 'contains', 'not_contains', 'starts_with', 'ends_with', 'gt', 'gte', 'lt', 'lte' );
					$op = isset( $c['op'] ) ? sanitize_key( $c['op'] ) : 'is';
					if ( ! in_array( $op, $allowed_ops, true ) ) { $op = 'is'; }
					$field['conditions'][] = array(
						'field'  => isset( $c['field'] ) ? sanitize_key( $c['field'] ) : '',
						'op'     => $op,
						'values' => isset( $c['values'] ) ? array_map( 'sanitize_text_field', (array) $c['values'] ) : array(),
					);
				}
			}

			$out['fields'][] = $field;
		}
		return $out;
	}
	/**
	 * sanitize اکشن‌ها — کلیدهای متا با همان ۳ لایه امنیتی.
	 */
	public static function sanitize_actions( array $raw ) {
		$out = array( 'meta' => array(), 'sms' => 0, 'redirect' => '' );
		if ( ! empty( $raw['meta'] ) && is_array( $raw['meta'] ) ) {
			$declared = array();
			foreach ( $raw['meta'] as $m ) {
				if ( isset( $m['key'] ) ) { $declared[] = sanitize_key( $m['key'] ); }
			}
			foreach ( $raw['meta'] as $m ) {
				$key = isset( $m['key'] ) ? sanitize_key( $m['key'] ) : '';
				if ( '' === $key || ! Poian_Quiz_Security::is_meta_key_allowed( $key, $declared ) ) { continue; }
				$out['meta'][] = array( 'key' => $key, 'source' => isset( $m['source'] ) ? sanitize_text_field( $m['source'] ) : '' );
			}
		}
		$out['sms']      = ! empty( $raw['sms'] ) ? 1 : 0;
		$out['redirect'] = isset( $raw['redirect'] ) ? esc_url_raw( $raw['redirect'] ) : '';
		return $out;
	}

	private static function bad() {
		return new WP_Error( 'invalid_answers', __( 'ساختار پاسخ‌ها نامعتبر است.', 'poian-quiz' ), array( 'status' => 422 ) );
	}
}
