<?php defined( 'ABSPATH' ) || exit; ?>
<section class="pq-card pq-gate">
	<h3><?php esc_html_e( 'برای شرکت در این آزمون وارد شوید', 'poian-quiz' ); ?></h3>
	<a class="pq-btn pq-btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><span><?php esc_html_e( 'ورود / عضویت', 'poian-quiz' ); ?></span></a>
</section>
