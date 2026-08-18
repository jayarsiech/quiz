<?php
defined( 'ABSPATH' ) || exit;
/** @var int $pk_form_id */
/** @var array $pk_schema */
/** @var array $pk_actions */
/** @var array $pk_settings */
/** @var string $pk_engine */
/** @var array $pk_engines */
/** @var array $pk_engine_config */
$pk_notice = isset( $_GET['pq_notice'] ) ? sanitize_key( wp_unslash( $_GET['pq_notice'] ) ) : '';
?>
<div class="wrap pq-admin pq-editor-wrap">
	<h1 class="pq-title">
		<span><?php echo $pk_form_id ? esc_html__( 'ویرایش فرم', 'poian-quiz' ) : esc_html__( 'فرم جدید', 'poian-quiz' ); ?></span>
		<?php if ( $pk_form_id ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( add_query_arg( array( 'page' => Poian_Quiz_Admin::MAIN_SLUG, 'view' => 'settings', 'form_id' => $pk_form_id ), admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'تنظیمات فرم', 'poian-quiz' ); ?>
			</a>
			<a class="page-title-action" href="<?php echo esc_url( add_query_arg( array( 'page' => Poian_Quiz_Admin::MAIN_SLUG, 'view' => 'inbox', 'form_id' => $pk_form_id ), admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'صندوق ورودی‌ها', 'poian-quiz' ); ?>
			</a>
		<?php endif; ?>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Poian_Quiz_Admin::MAIN_SLUG ) ); ?>">
			<?php esc_html_e( 'بازگشت به فرم‌ها', 'poian-quiz' ); ?>
		</a>
	</h1>

	<?php if ( 'saved' === $pk_notice && $pk_form_id ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php esc_html_e( 'ذخیره شد. شورت‌کد:', 'poian-quiz' ); ?>
			<code class="pq-shortcode">[poian_quiz id="<?php echo (int) $pk_form_id; ?>"]</code>
			<button type="button" class="button pq-copy" data-copy="[poian_quiz id=&quot;<?php echo (int) $pk_form_id; ?>&quot;]"><?php esc_html_e( 'کپی', 'poian-quiz' ); ?></button>
		</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pq-builder-form">
		<input type="hidden" name="action" value="poian_quiz_save_form" />
		<input type="hidden" name="form_id" value="<?php echo (int) $pk_form_id; ?>" />
		<?php wp_nonce_field( 'poian_quiz_save_form' ); ?>
		<input type="hidden" name="pq_schema_json" id="pq-schema-json" value="" />
		<input type="hidden" name="pq_actions_json" id="pq-actions-json" value="" />
		<input type="hidden" name="pq_engine_config_json" id="pq-engine-config-json" value="" />
		<input type="hidden" name="pq_engine" id="pq-engine" value="<?php echo esc_attr( $pk_engine ); ?>" />
		<!-- Layout دو ستونی: Toolbox چپ + Canvas راست -->
		<div class="pq-editor-layout">
			<!-- Sidebar: Toolbox -->
			<aside class="pq-toolbox" id="pq-toolbox">
				<div id="pq-builder-toolbox"
					data-engines="<?php echo esc_attr( wp_json_encode( $pk_engines ) ); ?>"
					data-engine="<?php echo esc_attr( $pk_engine ); ?>"></div>
			</aside>

			<!-- Main: Canvas -->
			<main class="pq-canvas" id="pq-canvas">
				<div id="pq-builder"
					data-schema="<?php echo esc_attr( wp_json_encode( $pk_schema ) ); ?>"
					data-actions="<?php echo esc_attr( wp_json_encode( $pk_actions ) ); ?>"
					data-settings="<?php echo esc_attr( wp_json_encode( is_array( $pk_settings ) ? $pk_settings : array() ) ); ?>"
					data-engine="<?php echo esc_attr( $pk_engine ); ?>"
					data-engines="<?php echo esc_attr( wp_json_encode( $pk_engines ) ); ?>"
					data-engine-config="<?php echo esc_attr( wp_json_encode( is_array( $pk_engine_config ) ? $pk_engine_config : array() ) ); ?>"></div>

				<p class="submit pq-editor-submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره فرم', 'poian-quiz' ); ?></button>
				</p>
			</main>
		</div>
	</form>
</div>
