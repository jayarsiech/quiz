<?php
defined( 'ABSPATH' ) || exit;
$pk_notice = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
$pk_list   = new Poian_Quiz_Admin_Forms_List();
$pk_list->prepare_items();
?>
<div class="wrap pq-admin"> 
	<h1 class="pq-title"><?php esc_html_e( 'Poian Quiz — فرم‌ها', 'poian-quiz' ); ?>
<a class="page-title-action" href="<?php echo esc_url( add_query_arg( array( 'page' => Poian_Quiz_Admin::MAIN_SLUG, 'view' => 'editor' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'فرم جدید', 'poian-quiz' ); ?></a>
		<?php if ( class_exists( 'Poian_Quiz_Mehdyar_Importer' ) ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=poian_quiz_create_mehdyar_sample' ), 'poian_quiz_create_sample' ) ); ?>"><?php esc_html_e( '+ نمونه قطب‌نمای مهدیار', 'poian-quiz' ); ?></a>
		<?php endif; ?>
			<?php if ( class_exists( 'Poian_Quiz_Kolb_Importer' ) ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=poian_quiz_create_kolb_sample' ), 'poian_quiz_create_sample_kolb' ) ); ?>"><?php esc_html_e( '+ نمونه کلب', 'poian-quiz' ); ?></a>
		<?php endif; ?>

	</h1>
	<?php if ( 'deleted' === $pk_notice ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'فرم حذف شد.', 'poian-quiz' ); ?></p></div><?php endif; ?>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="poian-quiz-forms" />
		<?php $pk_list->search_box( __( 'جستجو', 'poian-quiz' ), 'pq-s' ); ?>
		<?php $pk_list->display(); ?>
	</form>
</div>
