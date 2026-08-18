<?php
defined( 'ABSPATH' ) || exit;
/** @var string $pk_notice */
/** @var Poian_Quiz_Admin_Global_Inbox_List_Table $pk_list */
?>
<div class="wrap pq-admin">
	<h1 class="pq-title">
		<span><?php esc_html_e( 'صندوق ورودی‌ها — همه فرم‌ها', 'poian-quiz' ); ?></span>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Poian_Quiz_Admin::MAIN_SLUG ) ); ?>">
			<?php esc_html_e( 'بازگشت به فرم‌ها', 'poian-quiz' ); ?>
		</a>
	</h1>

	<?php if ( 'deleted' === $pk_notice ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'ورودی حذف شد.', 'poian-quiz' ); ?></p></div>
	<?php endif; ?>
	<?php if ( 'bulk_done' === $pk_notice ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( '%d ورودی پردازش شد.', 'poian-quiz' ), absint( $_GET['pq_count'] ?? 0 ) ) ); ?></p></div>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Poian_Quiz_Admin::MAIN_SLUG . '-inbox' ); ?>" />
		<?php $pk_list->search_box( __( 'جستجو', 'poian-quiz' ), 'pq-inbox-s' ); ?>
		<?php $pk_list->display(); ?>
	</form> 
</div>
