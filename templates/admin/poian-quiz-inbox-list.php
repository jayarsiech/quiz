<?php
defined( 'ABSPATH' ) || exit;
/** @var WP_Post $pk_form */
/** @var string $pk_notice */
/** @var Poian_Quiz_Admin_Inbox_List_Table $pk_list */
/** @var array $pk_stats */
?>
<div class="wrap pq-admin">
	<h1 class="pq-title">
		<span><?php echo esc_html( sprintf( __( 'صندوق ورودی‌ها — %s', 'poian-quiz' ), get_the_title( $pk_form ) ) ); ?></span>
		<a class="page-title-action" href="<?php echo esc_url( add_query_arg( array( 'page' => Poian_Quiz_Admin::MAIN_SLUG, 'view' => 'editor', 'form_id' => $pk_form->ID ), admin_url( 'admin.php' ) ) ); ?>">
			<?php esc_html_e( 'ویرایش فرم', 'poian-quiz' ); ?>
		</a>
		<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'poian_quiz_inbox_export', 'form_id' => $pk_form->ID ), admin_url( 'admin-post.php' ) ), 'pq_inbox_export_' . $pk_form->ID ) ); ?>">
			<?php esc_html_e( '📥 خروجی CSV', 'poian-quiz' ); ?>
		</a>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Poian_Quiz_Admin::MAIN_SLUG ) ); ?>">
			<?php esc_html_e( 'بازگشت به فرم‌ها', 'poian-quiz' ); ?>
		</a>
	</h1>

	<?php if ( 'deleted' === $pk_notice ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'ورودی حذف شد.', 'poian-quiz' ); ?></p></div>
	<?php endif; ?>
	<?php if ( 'status_changed' === $pk_notice ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'وضعیت تغییر کرد.', 'poian-quiz' ); ?></p></div>
	<?php endif; ?>
	<?php if ( 'bulk_done' === $pk_notice ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( '%d ورودی پردازش شد.', 'poian-quiz' ), absint( $_GET['pq_count'] ?? 0 ) ) ); ?></p></div>
	<?php endif; ?>

	<div class="pq-inbox-stats">
		<div class="pq-stat"><span><?php esc_html_e( 'کل ورودی‌ها', 'poian-quiz' ); ?></span><strong><?php echo (int) $pk_stats['all']; ?></strong></div>
		<div class="pq-stat"><span><?php esc_html_e( 'فعال', 'poian-quiz' ); ?></span><strong><?php echo (int) $pk_stats['active']; ?></strong></div>
		<div class="pq-stat"><span><?php esc_html_e( 'بایگانی', 'poian-quiz' ); ?></span><strong><?php echo (int) $pk_stats['archived']; ?></strong></div>
	</div>

	<?php $pk_list->views(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="form_id" value="<?php echo (int) $pk_form->ID; ?>" />
		<?php wp_nonce_field( 'bulk-entries' ); ?>
		<?php $pk_list->search_box( __( 'جستجو', 'poian-quiz' ), 'pq-inbox-s' ); ?>
		<?php $pk_list->display(); ?>
	</form>
</div>
