<?php
defined( 'ABSPATH' ) || exit;
/** @var WP_Post $pk_form */
/** @var array $attempt */
/** @var array $pk_schema */
/** @var array $pk_actions */
/** @var array $pk_usermeta */
/** @var string $pk_notice */
$pk_answers = is_array( $attempt['answers'] ) ? $attempt['answers'] : array();
$pk_result  = is_array( $attempt['result'] ) ? $attempt['result'] : array();
$pk_fields  = Poian_Quiz_Schema::all_fields( $pk_schema );
?>
<div class="wrap pq-admin">
	<h1 class="pq-title">
		<span><?php echo esc_html( sprintf( __( 'ورودی #%d — %s', 'poian-quiz' ), (int) $attempt['id'], get_the_title( $pk_form ) ) ); ?></span>
		<a class="page-title-action" href="<?php echo esc_url( add_query_arg( array( 'page' => Poian_Quiz_Admin::MAIN_SLUG, 'view' => 'inbox', 'form_id' => $pk_form->ID ), admin_url( 'admin.php' ) ) ); ?>">
			<?php esc_html_e( '← بازگشت به صندوق', 'poian-quiz' ); ?>
		</a>
	</h1>

	<?php if ( 'saved' === $pk_notice ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'ورودی ذخیره شد.', 'poian-quiz' ); ?></p></div>
	<?php endif; ?>

	<div class="pq-inbox-meta-card">
		<div class="pq-meta-row"><span><?php esc_html_e( 'کاربر:', 'poian-quiz' ); ?></span><strong><?php
			if ( (int) $attempt['user_id'] > 0 ) {
				$u = get_userdata( (int) $attempt['user_id'] );
				echo esc_html( $u ? $u->display_name . ' (ID: ' . $u->ID . ')' : '—' );
			} else {
				echo '<em>' . esc_html__( 'مهمان', 'poian-quiz' ) . '</em> (' . esc_html( $attempt['actor_key'] ) . ')';
			}
		?></strong></div>
		<div class="pq-meta-row"><span><?php esc_html_e( 'موبایل:', 'poian-quiz' ); ?></span><strong><?php echo esc_html( $attempt['mobile'] ?: '—' ); ?></strong></div>
		<div class="pq-meta-row"><span><?php esc_html_e( 'تاریخ ثبت:', 'poian-quiz' ); ?></span><strong><?php echo esc_html( wp_date( 'Y/m/d - H:i:s', strtotime( $attempt['created_at'] ) ) ); ?></strong></div>
		<div class="pq-meta-row"><span><?php esc_html_e( 'نتیجه:', 'poian-quiz' ); ?></span><strong><?php echo esc_html( $attempt['result_label'] ?: '—' ); ?></strong></div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pq-card">
		<input type="hidden" name="action" value="poian_quiz_inbox_save" />
		<input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>" />
		<input type="hidden" name="form_id" value="<?php echo (int) $pk_form->ID; ?>" />
		<?php wp_nonce_field( 'pq_inbox_save_' . (int) $attempt['id'] ); ?>

		<h2 class="pq-card-title"><?php esc_html_e( 'پاسخ‌ها', 'poian-quiz' ); ?></h2>
		<?php foreach ( $pk_fields as $fid => $f ) :
			if ( in_array( $f['type'], array( 'description', 'heading' ), true ) ) { continue; }
			$val = isset( $pk_answers[ $fid ] ) ? $pk_answers[ $fid ] : '';
		?>
			<div class="pq-inbox-field">
				<label class="pq-lbl"><?php echo esc_html( $f['title'] ); ?> <small class="pq-fid">(<?php echo esc_html( $fid ); ?>)</small></label>
				<?php if ( 'radio' === $f['type'] ) : ?>
					<div class="pq-inbox-options">
						<?php foreach ( (array) $f['options'] as $o ) : ?>
							<label class="pq-inbox-option">
								<input type="radio" name="pq_answer[<?php echo esc_attr( $fid ); ?>]" value="<?php echo esc_attr( $o['key'] ); ?>" <?php checked( (string) $val, (string) $o['key'] ); ?> />
								<span><?php echo esc_html( $o['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php elseif ( 'checkbox' === $f['type'] ) : ?>
					<div class="pq-inbox-options">
						<?php $vals = is_array( $val ) ? $val : array(); foreach ( (array) $f['options'] as $o ) : ?>
							<label class="pq-inbox-option">
								<input type="checkbox" name="pq_answer[<?php echo esc_attr( $fid ); ?>][]" value="<?php echo esc_attr( $o['key'] ); ?>" <?php checked( in_array( (string) $o['key'], array_map( 'strval', $vals ), true ), true ); ?> />
								<span><?php echo esc_html( $o['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php elseif ( 'rank' === $f['type'] ) : ?>
					<div class="pq-inbox-rank" data-fid="<?php echo esc_attr( $fid ); ?>">
						<?php
						$vals = is_array( $val ) ? $val : array();
						$key_to_label = array();
						foreach ( (array) $f['options'] as $o ) { $key_to_label[ $o['key'] ] = $o['label']; }
						foreach ( $vals as $i => $k ) :
							if ( ! isset( $key_to_label[ $k ] ) ) { continue; }
						?>
							<div class="pq-inbox-rank-item">
								<input type="hidden" name="pq_rank[<?php echo esc_attr( $fid ); ?>][]" value="<?php echo esc_attr( $k ); ?>" />
								<span class="pq-inbox-rank-label"><?php echo esc_html( $key_to_label[ $k ] ); ?></span>
								<button type="button" class="button pq-rank-up">↑</button>
								<button type="button" class="button pq-rank-down">↓</button>
							</div>
						<?php endforeach; ?>
					</div>
				<?php elseif ( 'textarea' === $f['type'] ) : ?>
					<textarea name="pq_answer[<?php echo esc_attr( $fid ); ?>]" rows="4" class="pq-inbox-ta"><?php echo esc_textarea( (string) $val ); ?></textarea>
				<?php else : ?>
					<input type="text" name="pq_answer[<?php echo esc_attr( $fid ); ?>]" value="<?php echo esc_attr( (string) $val ); ?>" class="pq-inbox-in" />
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( (int) $attempt['user_id'] > 0 && ! empty( $pk_actions['meta'] ) && is_array( $pk_actions['meta'] ) ) : ?>
			<h2 class="pq-card-title"><?php esc_html_e( 'متاهای کاربر (وایت‌لیست)', 'poian-quiz' ); ?></h2>
			<?php foreach ( $pk_actions['meta'] as $m ) :
				if ( empty( $m['key'] ) ) { continue; }
				$mk = $m['key'];
				$mv = isset( $pk_usermeta[ $mk ] ) ? $pk_usermeta[ $mk ] : '';
			?>
				<div class="pq-inbox-field">
					<label class="pq-lbl"><?php echo esc_html( $mk ); ?> <small class="pq-fid">(<?php echo esc_html( isset( $m['source'] ) ? $m['source'] : '' ); ?>)</small></label>
					<input type="text" name="pq_meta[<?php echo esc_attr( $mk ); ?>]" value="<?php echo esc_attr( is_array( $mv ) ? implode( ',', $mv ) : (string) $mv ); ?>" class="pq-inbox-in" />
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<p class="submit pq-inbox-submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره تغییرات', 'poian-quiz' ); ?></button>

			<?php
			$new_status = 1 === (int) $attempt['status'] ? 0 : 1;
			$status_url = wp_nonce_url( add_query_arg( array(
				'action'     => 'poian_quiz_inbox_status',
				'form_id'    => $pk_form->ID,
				'attempt_id' => (int) $attempt['id'],
				'status'     => $new_status,
			), admin_url( 'admin-post.php' ) ), 'pq_inbox_status_' . (int) $attempt['id'] );
			?>
			<a class="button" href="<?php echo esc_url( $status_url ); ?>">
				<?php echo 1 === (int) $attempt['status'] ? esc_html__( 'بایگانی', 'poian-quiz' ) : esc_html__( 'فعال کردن', 'poian-quiz' ); ?>
			</a>

			<?php
			$del_url = wp_nonce_url( add_query_arg( array(
				'action'     => 'poian_quiz_inbox_delete',
				'form_id'    => $pk_form->ID,
				'attempt_id' => (int) $attempt['id'],
			), admin_url( 'admin-post.php' ) ), 'pq_inbox_delete_' . (int) $attempt['id'] );
			?>
			<a class="button pq-danger" data-pq-confirm="<?php esc_attr_e( 'این ورودی حذف شود؟', 'poian-quiz' ); ?>" href="<?php echo esc_url( $del_url ); ?>"><?php esc_html_e( 'حذف', 'poian-quiz' ); ?></a>
		</p>
	</form>
</div>

<script>
(function(){
	document.querySelectorAll('.pq-inbox-rank').forEach(function(list){
		list.addEventListener('click', function(e){
			var btn = e.target.closest('.pq-rank-up,.pq-rank-down');
			if (!btn) return;
			var item = btn.closest('.pq-inbox-rank-item');
			if (btn.classList.contains('pq-rank-up') && item.previousElementSibling) {
				list.insertBefore(item, item.previousElementSibling);
			} else if (btn.classList.contains('pq-rank-down') && item.nextElementSibling) {
				list.insertBefore(item.nextElementSibling, item);
			}
		});
	});
})();
</script>
