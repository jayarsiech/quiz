<?php
defined( 'ABSPATH' ) || exit;
$pk_notice = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
?>
<div class="wrap pq-admin">
	<h1 class="pq-title"><?php esc_html_e( 'Poian Quiz — تنظیمات کلی', 'poian-quiz' ); ?></h1>
	<?php if ( 'saved' === $pk_notice ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'ذخیره شد.', 'poian-quiz' ); ?></p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pq-card">
		<input type="hidden" name="action" value="poian_quiz_save_settings" />
		<?php wp_nonce_field( 'poian_quiz_save_settings' ); ?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'نیاز به ورود (پیش‌فرض)', 'poian-quiz' ); ?></th><td><select name="require_login"><option value="1" <?php selected( 1, (int) $pk_s['require_login'] ); ?>><?php esc_html_e( 'بله', 'poian-quiz' ); ?></option><option value="0" <?php selected( 0, (int) $pk_s['require_login'] ); ?>><?php esc_html_e( 'خیر', 'poian-quiz' ); ?></option></select></td></tr>
			<tr><th><?php esc_html_e( 'فاصله بین ارسالها (دقیقه)', 'poian-quiz' ); ?></th><td><input type="number" min="0" name="cooldown_minutes" value="<?php echo esc_attr( $pk_s['cooldown_minutes'] ); ?>" class="small-text" /></td></tr>
			<tr><th><?php esc_html_e( 'سقف روزانه', 'poian-quiz' ); ?></th><td><input type="number" min="0" name="max_per_day" value="<?php echo esc_attr( $pk_s['max_per_day'] ); ?>" class="small-text" /></td></tr>
			<tr><th><?php esc_html_e( 'سقف کل', 'poian-quiz' ); ?></th><td><input type="number" min="0" name="max_total" value="<?php echo esc_attr( $pk_s['max_total'] ); ?>" class="small-text" /></td></tr>
			<tr><th><?php esc_html_e( 'حالت نمایش', 'poian-quiz' ); ?></th><td><select name="display_mode"><option value="all" <?php selected( $pk_s['display_mode'], 'all' ); ?>><?php esc_html_e( 'همه با هم', 'poian-quiz' ); ?></option><option value="single" <?php selected( $pk_s['display_mode'], 'single' ); ?>><?php esc_html_e( 'تک‌تک', 'poian-quiz' ); ?></option></select></td></tr>
			<tr><th><?php esc_html_e( 'نوار پیشرفت', 'poian-quiz' ); ?></th><td><label><input type="checkbox" name="show_progress" value="1" <?php checked( 1, (int) $pk_s['show_progress'] ); ?> /> <?php esc_html_e( 'نمایش', 'poian-quiz' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'حذف داده‌ها در uninstall', 'poian-quiz' ); ?></th><td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( 1, (int) $pk_s['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'بله', 'poian-quiz' ); ?></label></td></tr>
		</table>
		<p class="submit"><button class="button button-primary"><?php esc_html_e( 'ذخیره', 'poian-quiz' ); ?></button></p>
	</form>
</div>
