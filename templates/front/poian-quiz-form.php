<?php
defined( 'ABSPATH' ) || exit;
/** @var array $schema */
/** @var array $settings */
/** @var array $pk_pages_data */

$pk_single = ( 'single' === $settings['display_mode'] );
$pk_total_pages = count( $pk_pages_data );
$pk_has_pages = $pk_total_pages > 1;
?>
<section class="pq-form" id="pq-form" data-total-pages="<?php echo (int) $pk_total_pages; ?>">
	<?php
	// مقدار progress از data-attribute app می‌آید (resolve_progress_setting آن را محاسبه کرده)
	$pk_show_progress = isset( $settings['_progress_resolved'] ) ? $settings['_progress_resolved'] : ( 1 === (int) $settings['show_progress'] ? '1' : '0' );
	if ( '1' === $pk_show_progress ) :
	?>
		<div class="pq-progress-wrap">
			<div class="pq-progress-bar"><div class="pq-progress-fill" style="width:0%"></div></div>
			<p class="pq-progress-text"><span class="pq-progress-current">0</span> / <span class="pq-progress-total">0</span></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $pk_pages_data ) ) : ?>
		<div class="pq-empty-form">
			<p><?php esc_html_e( 'این فرم هنوز فیلدی ندارد.', 'poian-quiz' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $pk_pages_data as $pk_pi => $pk_page ) :
			$pk_is_first = ( 0 === $pk_pi );
			$pk_is_last  = ( $pk_pi === $pk_total_pages - 1 );
			$pk_hidden   = ( $pk_has_pages && ! $pk_is_first && ! $pk_single ) ? ' pq-hidden' : '';
		?>
			<section class="pq-page<?php echo $pk_hidden; ?>" data-page="<?php echo (int) $pk_pi; ?>"
				<?php echo ! empty( $pk_page['next_label'] ) ? 'data-next="' . esc_attr( $pk_page['next_label'] ) . '"' : ''; ?>
				<?php echo ! empty( $pk_page['prev_label'] ) ? 'data-prev="' . esc_attr( $pk_page['prev_label'] ) . '"' : ''; ?>>
				<?php if ( ! empty( $pk_page['heading'] ) ) : ?>
					<h3 class="pq-page-heading"><?php echo esc_html( $pk_page['heading'] ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $pk_page['description'] ) ) : ?>
					<div class="pq-page-desc"><?php echo nl2br( esc_html( $pk_page['description'] ) ); ?></div>
				<?php endif; ?>

				<?php if ( ! empty( $pk_page['fields'] ) ) : ?>
					<?php foreach ( $pk_page['fields'] as $pk_f ) :
						$pk_type = isset( $pk_f['type'] ) ? $pk_f['type'] : 'text';
						$pk_fid  = isset( $pk_f['id'] ) ? $pk_f['id'] : '';
						$pk_cond = '';
						if ( ! empty( $pk_f['conditions'] ) ) {
							$pk_cond = esc_attr( wp_json_encode( array(
								'conditions' => $pk_f['conditions'],
								'logic'      => isset( $pk_f['condition_logic'] ) ? $pk_f['condition_logic'] : 'all',
								'action'     => isset( $pk_f['condition_action'] ) ? $pk_f['condition_action'] : 'show',
							) ) );
						}
						$pk_qnum = isset( $pk_f['question_number'] ) ? (int) $pk_f['question_number'] : 0;
					?>
						<div class="pq-field pq-type-<?php echo esc_attr( $pk_type ); ?>"
							data-fid="<?php echo esc_attr( $pk_fid ); ?>"
							data-type="<?php echo esc_attr( $pk_type ); ?>"
							data-required="<?php echo ! empty( $pk_f['required'] ) ? '1' : '0'; ?>"
							<?php echo $pk_cond ? 'data-cond="' . $pk_cond . '"' : ''; ?>>

							<?php if ( 'description' === $pk_type ) : ?>
								<div class="pq-desc"><?php echo nl2br( esc_html( isset( $pk_f['description'] ) ? $pk_f['description'] : '' ) ); ?></div>

							<?php elseif ( 'heading' === $pk_type ) : ?>
								<h4 class="pq-fheading"><?php echo esc_html( isset( $pk_f['title'] ) ? $pk_f['title'] : '' ); ?></h4>

							<?php else : ?>
								<?php if ( ! empty( $pk_f['title'] ) ) : ?>
									<p class="pq-q-title">
										<?php if ( $pk_qnum > 0 ) : ?>
											<span class="pq-q-num"><?php echo (int) $pk_qnum; ?></span>
										<?php endif; ?>
										<?php echo esc_html( $pk_f['title'] ); ?>
										<?php echo ! empty( $pk_f['required'] ) ? ' <span class="pq-req">*</span>' : ''; ?>
									</p>
								<?php endif; ?>
								<?php if ( ! empty( $pk_f['description'] ) ) : ?>
									<div class="pq-fdesc"><?php echo nl2br( esc_html( $pk_f['description'] ) ); ?></div>
								<?php endif; ?>

								<?php if ( 'radio' === $pk_type || 'checkbox' === $pk_type ) : ?>
									<div class="pq-options">
										<?php foreach ( (array) $pk_f['options'] as $pk_o ) : ?>
											<label class="pq-option">
												<input type="<?php echo 'radio' === $pk_type ? 'radio' : 'checkbox'; ?>"
													name="pq_<?php echo esc_attr( $pk_fid ); ?><?php echo 'checkbox' === $pk_type ? '[]' : ''; ?>"
													value="<?php echo esc_attr( isset( $pk_o['key'] ) ? $pk_o['key'] : '' ); ?>" />
												<span class="pq-option-text"><?php echo esc_html( isset( $pk_o['label'] ) ? $pk_o['label'] : '' ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>

								<?php elseif ( 'rank' === $pk_type ) : ?>
									<ul class="pq-rank">
										<?php
										$pk_opts = (array) $pk_f['options'];
										shuffle( $pk_opts );
										foreach ( $pk_opts as $pk_o ) : ?>
											<li class="pq-option" data-opt="<?php echo esc_attr( isset( $pk_o['key'] ) ? $pk_o['key'] : '' ); ?>">
												<span class="pq-handle" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm9-14a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/></svg></span>
												<span class="pq-option-text"><?php echo esc_html( isset( $pk_o['label'] ) ? $pk_o['label'] : '' ); ?></span>
												<span class="pq-movers">
													<button type="button" class="pq-move pq-up" aria-label="<?php esc_attr_e( 'بالا', 'poian-quiz' ); ?>"><svg viewBox="0 0 24 24"><path d="m12 8 6 6H6z"/></svg></button>
													<button type="button" class="pq-move pq-down" aria-label="<?php esc_attr_e( 'پایین', 'poian-quiz' ); ?>"><svg viewBox="0 0 24 24"><path d="m12 16-6-6h12z"/></svg></button>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>

								<?php elseif ( 'text' === $pk_type ) : ?>
									<input type="text" class="pq-input" name="pq_<?php echo esc_attr( $pk_fid ); ?>" />

								<?php elseif ( 'textarea' === $pk_type ) : ?>
									<textarea class="pq-input" rows="4" name="pq_<?php echo esc_attr( $pk_fid ); ?>"></textarea>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>

	<div class="pq-nav">
		<?php if ( $pk_has_pages && ! $pk_single ) : ?>
			<button type="button" class="pq-btn pq-prev pq-hidden">
				<span><?php echo esc_html( ! empty( $pk_pages_data[1]['prev_label'] ) ? $pk_pages_data[1]['prev_label'] : __( 'قبلی', 'poian-quiz' ) ); ?></span>
			</button>
			<button type="button" class="pq-btn pq-next">
				<span><?php echo esc_html( ! empty( $pk_pages_data[0]['next_label'] ) ? $pk_pages_data[0]['next_label'] : __( 'بعدی', 'poian-quiz' ) ); ?></span>
			</button>
		<?php endif; ?>
		<button type="button" class="pq-btn pq-btn-primary pq-submit<?php echo ( $pk_has_pages && ! $pk_single ) ? ' pq-hidden' : ''; ?>">
			<span><?php esc_html_e( 'ثبت پاسخ‌ها', 'poian-quiz' ); ?></span>
		</button>
	</div>
</section>
