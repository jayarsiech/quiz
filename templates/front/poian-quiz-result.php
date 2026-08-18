<?php defined( 'ABSPATH' ) || exit; ?>
<section class="pq-result">
	<?php echo $engine ? $engine->render_result( is_array( $latest['result'] ) ? $latest['result'] : array(), $latest ) : ''; ?>
	<?php if ( ! empty( $pk_can_retake ) ) : ?>
		<div class="pq-actions">
			<button type="button" class="pq-btn pq-retake"><span><?php esc_html_e( 'آزمون مجدد', 'poian-quiz' ); ?></span></button>
		</div>
	<?php endif; ?>
</section>
