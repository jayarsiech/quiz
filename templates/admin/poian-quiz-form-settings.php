<?php
defined( 'ABSPATH' ) || exit;
/** @var WP_Post $pk_form */
/** @var string $pk_notice */
/** @var array $pk_raw */
/** @var array $pk_effective */
/** @var array $pk_global */
/** @var string $pk_active_tab */

$pk_tabs = array(
	'general'  => __( 'عمومی', 'poian-quiz' ),
	'sms'      => __( 'پیامک', 'poian-quiz' ),
	'actions'  => __( 'اکشن‌ها', 'poian-quiz' ),
	'advanced' => __( 'پیشرفته', 'poian-quiz' ),
);

if ( ! array_key_exists( $pk_active_tab, $pk_tabs ) ) {
	$pk_active_tab = 'general';
}

$base_url = add_query_arg( array(
	'page'    => Poian_Quiz_Admin::MAIN_SLUG,
	'view'    => 'settings',
	'form_id' => $pk_form->ID,
), admin_url( 'admin.php' ) );

/**
 * Helper برای ساخت select با گزینه inherit.
 */
function pq_render_select( $name, $options, $current, $global_val ) {
	$cur = ( isset( $current ) && 'inherit' !== $current ) ? $current : 'inherit';
	echo '<select name="' . esc_attr( $name ) . '" class="pq-in">';
	echo '<option value="inherit"' . selected( $cur, 'inherit', false ) . '>' . esc_html__( 'ارث از کلی', 'poian-quiz' ) . ' (' . esc_html( $global_val ) . ')</option>';
	foreach ( $options as $val => $label ) {
		echo '<option value="' . esc_attr( $val ) . '"' . selected( $cur, $val, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
}

/**
 * Helper برای input عددی با placeholder inherit.
 */
function pq_render_number( $name, $current, $global_val ) {
	$cur = ( isset( $current ) && 'inherit' !== $current ) ? $current : '';
	$ph  = esc_attr__( 'ارث از کلی', 'poian-quiz' ) . ': ' . esc_html( $global_val );
	echo '<input type="number" name="' . esc_attr( $name ) . '" value="' . esc_attr( $cur ) . '" min="0" class="small-text" placeholder="' . $ph . '" />';
}
?>

<div class="wrap pq-admin pq-settings-wrap">
	<h1 class="pq-title">
		<span><?php echo esc_html( sprintf( __( 'تنظیمات — %s', 'poian-quiz' ), get_the_title( $pk_form ) ) ); ?></span>
		<a class="page-title-action" href="<?php echo esc_url( add_query_arg( array( 'view' => 'editor' ), $base_url ) ); ?>">
			<?php esc_html_e( 'ویرایش فرم', 'poian-quiz' ); ?>
		</a>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Poian_Quiz_Admin::MAIN_SLUG ) ); ?>">
			<?php esc_html_e( 'بازگشت به فرم‌ها', 'poian-quiz' ); ?>
		</a>
	</h1>

	<?php if ( 'saved' === $pk_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات ذخیره شد.', 'poian-quiz' ); ?></p></div>
	<?php endif; ?>

	<div class="pq-settings-layout">
		<!-- Sidebar: لیست تب‌ها -->
		<nav class="pq-settings-tabs">
			<?php foreach ( $pk_tabs as $key => $label ) :
				$url = add_query_arg( 'tab', $key, $base_url );
				$active = ( $pk_active_tab === $key ) ? ' pq-tab-active' : '';
			?>
				<a href="<?php echo esc_url( $url ); ?>" class="pq-tab<?php echo $active; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<!-- Main: محتوای تب -->
		<div class="pq-settings-content">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pq-card">
				<input type="hidden" name="action" value="poian_quiz_save_form_settings" />
				<input type="hidden" name="form_id" value="<?php echo (int) $pk_form->ID; ?>" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $pk_active_tab ); ?>" />
				<?php wp_nonce_field( 'poian_quiz_save_form_settings_' . $pk_form->ID ); ?>
				<?php if ( 'general' === $pk_active_tab ) : ?>
					<h2 class="pq-card-title"><?php esc_html_e( 'تنظیمات عمومی', 'poian-quiz' ); ?></h2>
					<p class="pq-card-desc"><?php esc_html_e( 'مقادیر خالی یا "ارث از کلی" از تنظیمات کلی افزونه استفاده می‌کنند.', 'poian-quiz' ); ?></p>

					<table class="form-table pq-form-table" id="pq-form-settings-table">
						<tr>
							<th><label><?php esc_html_e( 'نیاز به ورود', 'poian-quiz' ); ?></label></th>
							<td>
								<?php pq_render_select( 'fs_require_login', array(
									'1' => __( 'بله', 'poian-quiz' ),
									'0' => __( 'خیر', 'poian-quiz' ),
								), isset( $pk_raw['require_login'] ) ? $pk_raw['require_login'] : null, $pk_global['require_login'] ? __( 'بله', 'poian-quiz' ) : __( 'خیر', 'poian-quiz' ) ); ?>
								<p class="description"><?php esc_html_e( 'آیا کاربر باید برای شرکت در آزمون وارد شده باشد؟', 'poian-quiz' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'سیاست آزمون مجدد', 'poian-quiz' ); ?></label></th>
							<td>
								<?php pq_render_select( 'fs_retake_mode', array(
									'cooldown'  => __( 'با فاصله زمانی (Cooldown)', 'poian-quiz' ),
									'unlimited' => __( 'نامحدود بدون فاصله', 'poian-quiz' ),
									'once'      => __( 'فقط یک‌بار', 'poian-quiz' ),
								), isset( $pk_raw['retake_mode'] ) ? $pk_raw['retake_mode'] : null, __( 'Cooldown', 'poian-quiz' ) ); ?>
								<p class="description"><?php esc_html_e( 'نحوه اجازه آزمون مجدد به کاربر.', 'poian-quiz' ); ?></p>
							</td>
						</tr>
						<tr class="pq-row-cooldown pq-row-perday">
							<th><label><?php esc_html_e( 'فاصله بین ارسال (دقیقه)', 'poian-quiz' ); ?></label></th>
							<td>
								<?php pq_render_number( 'fs_cooldown_minutes', isset( $pk_raw['cooldown_minutes'] ) ? $pk_raw['cooldown_minutes'] : null, $pk_global['cooldown_minutes'] ); ?>
								<p class="description"><?php esc_html_e( 'حداقل فاصله زمانی بین دو ارسال متوالی توسط یک کاربر.', 'poian-quiz' ); ?></p>
							</td>
						</tr>
						<tr class="pq-row-perday">
							<th><label><?php esc_html_e( 'سقف روزانه', 'poian-quiz' ); ?></label></th>
							<td>
								<?php pq_render_number( 'fs_max_per_day', isset( $pk_raw['max_per_day'] ) ? $pk_raw['max_per_day'] : null, $pk_global['max_per_day'] ); ?>
								<p class="description"><?php esc_html_e( 'حداکثر تعداد ارسال در روز (0 = نامحدود).', 'poian-quiz' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'سقف کل', 'poian-quiz' ); ?></label></th>
							<td>
								<?php pq_render_number( 'fs_max_total', isset( $pk_raw['max_total'] ) ? $pk_raw['max_total'] : null, $pk_global['max_total'] ); ?>
								<p class="description"><?php esc_html_e( 'حداکثر تعداد کل ارسال برای هر کاربر (0 = نامحدود).', 'poian-quiz' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'نمایش تاریخچه به کاربر', 'poian-quiz' ); ?></label></th>
							<td>
								<?php pq_render_select( 'fs_history_count', array(
									'0'  => __( 'خاموش', 'poian-quiz' ),
									'5'  => __( '۵ تای آخر', 'poian-quiz' ),
									'10' => __( '۱۰ تای آخر', 'poian-quiz' ),
									'-1' => __( 'همه', 'poian-quiz' ),
								), isset( $pk_raw['history_count'] ) ? $pk_raw['history_count'] : null, __( 'خاموش', 'poian-quiz' ) ); ?>
								<p class="description"><?php esc_html_e( 'تعداد آخرین نتایج کاربر در صفحه آزمون نمایش داده شود.', 'poian-quiz' ); ?></p>
							</td>
						</tr>
					</table>

				<?php elseif ( 'sms' === $pk_active_tab ) : ?>
					<h2 class="pq-card-title"><?php esc_html_e( 'تنظیمات پیامک', 'poian-quiz' ); ?></h2>
					<div class="pq-placeholder-notice">
						<p>🚧 <?php esc_html_e( 'این بخش در فازهای بعدی پیاده‌سازی می‌شود.', 'poian-quiz' ); ?></p>
						<p class="description"><?php esc_html_e( 'ارسال پیامک پس از ثبت پاسخ، ارسال نتیجه به کاربر، اطلاع‌رسانی به ادمین.', 'poian-quiz' ); ?></p>
					</div>

				<?php elseif ( 'actions' === $pk_active_tab ) : ?>
					<h2 class="pq-card-title"><?php esc_html_e( 'اکشن‌ها و متاها', 'poian-quiz' ); ?></h2>
					<div class="pq-placeholder-notice">
						<p>🚧 <?php esc_html_e( 'این بخش در فازهای بعدی پیاده‌سازی می‌شود.', 'poian-quiz' ); ?></p>
						<p class="description"><?php esc_html_e( 'ذخیره متاهای کاربر، ریدایرکت پس از ثبت، webhooks.', 'poian-quiz' ); ?></p>
					</div>

				<?php elseif ( 'advanced' === $pk_active_tab ) : ?>
					<h2 class="pq-card-title"><?php esc_html_e( 'تنظیمات پیشرفته', 'poian-quiz' ); ?></h2>
					<div class="pq-placeholder-notice">
						<p>🚧 <?php esc_html_e( 'این بخش در فازهای بعدی پیاده‌سازی می‌شود.', 'poian-quiz' ); ?></p>
						<p class="description"><?php esc_html_e( 'تنظیمات خاص موتور آزمون، cache، debug mode.', 'poian-quiz' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( 'general' === $pk_active_tab ) : ?>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره تنظیمات', 'poian-quiz' ); ?></button>
					</p>
				<?php endif; ?>
			</form>
		</div>
	</div>
</div>
