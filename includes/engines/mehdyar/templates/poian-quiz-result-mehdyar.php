<?php
defined( 'ABSPATH' ) || exit;
/**
 * Template: نتیجه قطب‌نمای مهدیار
 *
 * @var array  $scores
 * @var string $slug
 * @var array  $person
 * @var int    $form_id
 * @var array  $dim_labels
 * @var array  $ordered_dims
 * @var array  $result
 * @var int    $pk_max
 * @var float  $total_score
 * @var int    $max_possible
 * @var array  $pk_history
 * @var int    $pk_history_count
 */

$pk_max         = isset( $pk_max ) ? (int) $pk_max : ( isset( $result['extra']['max'] ) ? (int) $result['extra']['max'] : 20 );
$pk_labels      = ( isset( $dim_labels ) && is_array( $dim_labels ) ) ? $dim_labels : Poian_Quiz_Engine_Mehdyar::DEFAULT_DIM_LABELS;
$pk_order       = ( isset( $ordered_dims ) && is_array( $ordered_dims ) ) ? $ordered_dims : Poian_Quiz_Engine_Mehdyar::DIMS;
$pk_total       = isset( $total_score ) ? (float) $total_score : array_sum( array_map( 'floatval', array_values( $scores ) ) );
$pk_max_total   = isset( $max_possible ) ? (int) $max_possible : ( $pk_max * count( Poian_Quiz_Engine_Mehdyar::DIMS ) );
$pk_percentage  = ( $pk_max_total > 0 ) ? ( $pk_total / $pk_max_total ) * 100 : 0;
$pk_history     = isset( $pk_history ) ? (array) $pk_history : array();
$pk_history_cnt = isset( $pk_history_count ) ? (int) $pk_history_count : 0;

/* ---------- کیلومتر (Gauge) ---------- */
$pk_cx = 120;
$pk_cy = 110;
$pk_r  = 85;

// زاویه: از 180 (چپ) تا 0 (راست)
$pk_end_angle = 180 - ( $pk_percentage / 100 * 180 );
$pk_start_rad = deg2rad( 180 );
$pk_end_rad   = deg2rad( $pk_end_angle );

$pk_x1 = $pk_cx + $pk_r * cos( $pk_start_rad );
$pk_y1 = $pk_cy - $pk_r * sin( $pk_start_rad );
$pk_x2 = $pk_cx + $pk_r * cos( $pk_end_rad );
$pk_y2 = $pk_cy - $pk_r * sin( $pk_end_rad );
$pk_large_arc = ( $pk_percentage > 50 ) ? 1 : 0;
?>

<div class="pqm-gauge-wrap">
	<svg class="pqm-gauge" viewBox="0 0 240 140" role="img" aria-label="<?php esc_attr_e( 'کیلومتر امتیاز', 'poian-quiz' ); ?>" preserveAspectRatio="xMidYMid meet">
		<defs>
			<linearGradient id="pqm-gauge-grad-<?php echo esc_attr( $form_id ); ?>" x1="0%" y1="0%" x2="100%" y2="0%">
				<stop offset="0%" stop-color="#ef4444"/>
				<stop offset="40%" stop-color="#f59e0b"/>
				<stop offset="100%" stop-color="#10b981"/>
			</linearGradient>
		</defs>

		<!-- پس‌زمینه نیم‌دایره -->
		<path d="M <?php echo ( $pk_cx - $pk_r ); ?> <?php echo $pk_cy; ?> A <?php echo $pk_r; ?> <?php echo $pk_r; ?> 0 0 1 <?php echo ( $pk_cx + $pk_r ); ?> <?php echo $pk_cy; ?>"
			fill="none" stroke="#e2e8f0" stroke-width="22" stroke-linecap="round"/>

		<!-- arc پر شده -->
		<?php if ( $pk_percentage > 0.5 ) : ?>
		<path d="M <?php echo round( $pk_x1, 1 ); ?> <?php echo round( $pk_y1, 1 ); ?> A <?php echo $pk_r; ?> <?php echo $pk_r; ?> 0 <?php echo $pk_large_arc; ?> 1 <?php echo round( $pk_x2, 1 ); ?> <?php echo round( $pk_y2, 1 ); ?>"
			fill="none" stroke="url(#pqm-gauge-grad-<?php echo esc_attr( $form_id ); ?>)" stroke-width="22" stroke-linecap="round"
			class="pqm-gauge-fill"/>
		<?php endif; ?>

		<!-- گرید‌بندی (۵ نشانه: 0، 25، 50، 75، 100) -->
		<?php for ( $pk_i = 0; $pk_i <= 4; $pk_i++ ) :
			$pk_pct   = $pk_i * 25;
			$pk_angle = 180 - ( $pk_pct / 100 * 180 );
			$pk_rad   = deg2rad( $pk_angle );
			$pk_tick_x1 = $pk_cx + ( $pk_r - 14 ) * cos( $pk_rad );
			$pk_tick_y1 = $pk_cy - ( $pk_r - 14 ) * sin( $pk_rad );
			$pk_tick_x2 = $pk_cx + ( $pk_r + 14 ) * cos( $pk_rad );
			$pk_tick_y2 = $pk_cy - ( $pk_r + 14 ) * sin( $pk_rad );
			$pk_label_x = $pk_cx + ( $pk_r + 32 ) * cos( $pk_rad );
			$pk_label_y = $pk_cy - ( $pk_r + 32 ) * sin( $pk_rad );
			$pk_label_val = round( $pk_pct * $pk_max_total / 100 );
		?>
			<line x1="<?php echo round( $pk_tick_x1, 1 ); ?>" y1="<?php echo round( $pk_tick_y1, 1 ); ?>"
				  x2="<?php echo round( $pk_tick_x2, 1 ); ?>" y2="<?php echo round( $pk_tick_y2, 1 ); ?>"
				  stroke="#475569" stroke-width="2.5" stroke-linecap="round"/>
			<text x="<?php echo round( $pk_label_x, 1 ); ?>" y="<?php echo round( $pk_label_y + 4, 1 ); ?>"
				  text-anchor="middle" fill="#475569" font-size="11" font-weight="700"><?php echo $pk_label_val; ?></text>
		<?php endfor; ?>

		<!-- نقطه انتهای arc -->
		<circle cx="<?php echo round( $pk_x2, 1 ); ?>" cy="<?php echo round( $pk_y2, 1 ); ?>" r="7" fill="#fff" stroke="#6366f1" stroke-width="3"/>
	</svg>

	<div class="pqm-gauge-value">
		<span class="pqm-gauge-number"><?php echo esc_html( number_format_i18n( $pk_total ) ); ?></span>
		<span class="pqm-gauge-max"><?php echo esc_html( sprintf( __( 'از %s', 'poian-quiz' ), number_format_i18n( $pk_max_total ) ) ); ?></span>
		<span class="pqm-gauge-percent"><?php echo esc_html( round( $pk_percentage ) ); ?>٪</span>
	</div>
</div>

<?php
/* ---------- نمودار عنکبوتی (۴ محور) ---------- */
$pk_c = 160; $pk_R = 110;
$pk_radar_order = array( 'fiqh', 'belief', 'mission', 'growth' );
$pk_dx = array( 0, 1, 0, -1 ); $pk_dy = array( -1, 0, 1, 0 );
$pk_poly = array();
foreach ( $pk_radar_order as $pk_i => $pk_d ) {
	$pk_v = isset( $scores[ $pk_d ] ) ? min( $pk_max, max( 0, (float) $scores[ $pk_d ] ) ) : 0;
	$pk_r = ( $pk_v / $pk_max ) * $pk_R;
	$pk_poly[] = round( $pk_c + $pk_r * $pk_dx[ $pk_i ], 1 ) . ',' . round( $pk_c + $pk_r * $pk_dy[ $pk_i ], 1 );
}
?>
<div class="pqm-radar-wrap">
	<svg class="pqm-radar" viewBox="0 0 320 320" role="img" aria-label="<?php esc_attr_e( 'نمودار عنکبوتی چهار محور', 'poian-quiz' ); ?>">
		<?php foreach ( array( 0.25, 0.5, 0.75, 1 ) as $pk_f ) : ?>
			<?php $pk_ring = array();
			foreach ( $pk_radar_order as $pk_i => $pk_d ) {
				$pk_ring[] = round( $pk_c + $pk_R * $pk_f * $pk_dx[ $pk_i ], 1 ) . ',' . round( $pk_c + $pk_R * $pk_f * $pk_dy[ $pk_i ], 1 );
			} ?>
			<polygon class="pqm-ring" points="<?php echo esc_attr( implode( ' ', $pk_ring ) ); ?>"/>
		<?php endforeach; ?>
		<?php foreach ( $pk_radar_order as $pk_i => $pk_d ) : ?>
			<line class="pqm-axis" x1="<?php echo (int) $pk_c; ?>" y1="<?php echo (int) $pk_c; ?>" x2="<?php echo round( $pk_c + $pk_R * $pk_dx[ $pk_i ], 1 ); ?>" y2="<?php echo round( $pk_c + $pk_R * $pk_dy[ $pk_i ], 1 ); ?>"/>
			<text class="pqm-axis-label" x="<?php echo round( $pk_c + ( $pk_R + 26 ) * $pk_dx[ $pk_i ], 1 ); ?>" y="<?php echo round( $pk_c + ( $pk_R + 22 ) * $pk_dy[ $pk_i ] + 4, 1 ); ?>" text-anchor="middle"><?php echo esc_html( isset( $pk_labels[ $pk_d ] ) ? $pk_labels[ $pk_d ] : $pk_d ); ?></text>
		<?php endforeach; ?>
		<polygon class="pqm-shape" points="<?php echo esc_attr( implode( ' ', $pk_poly ) ); ?>"/>
		<?php foreach ( $pk_poly as $pk_pt ) : ?>
			<?php $pk_xy = explode( ',', $pk_pt ); ?>
			<circle class="pqm-dot" cx="<?php echo esc_attr( $pk_xy[0] ); ?>" cy="<?php echo esc_attr( $pk_xy[1] ); ?>" r="4"/>
		<?php endforeach; ?>
	</svg>
</div>

<?php
/* ---------- کارت شخصیت ---------- */
$pk_parts = explode( '_', $slug );
$pk_pair  = ( isset( $pk_labels[ $pk_parts[0] ] ) ? $pk_labels[ $pk_parts[0] ] : '' ) . ' + ' . ( isset( $pk_labels[ $pk_parts[1] ] ) ? $pk_labels[ $pk_parts[1] ] : '' );
?>
<div class="pqm-card">
	<div class="pqm-card-head">
		<span class="pqm-emoji"><?php echo esc_html( isset( $person['emoji'] ) ? $person['emoji'] : '🧭' ); ?></span>
		<div>
			<h3 class="pqm-title"><?php echo esc_html( isset( $person['title'] ) ? $person['title'] : $slug ); ?></h3>
			<p class="pqm-pair"><?php echo esc_html( $pk_pair ); ?></p>
		</div>
	</div>

	<?php
	if ( isset( $person['content'] ) && '' !== (string) $person['content'] ) :
		?>
		<div class="pqm-section pqm-content">
			<?php echo wp_kses_post( $person['content'] ); ?>
		</div>
		<?php
	elseif ( isset( $person['texts'] ) && is_array( $person['texts'] ) ) :
		$pk_heads = array(
			'character' => __( '🪞 کاراکتر تو:', 'poian-quiz' ),
			'treasure'  => __( '💎 گنجینه‌ی درون:', 'poian-quiz' ),
			'compass'   => __( '🧭 قطب‌نمای پنهان:', 'poian-quiz' ),
			'call'      => __( '🚀 دعوت به قهرمانی:', 'poian-quiz' ),
		);
		foreach ( $pk_heads as $pk_k => $pk_h ) :
			if ( isset( $person['texts'][ $pk_k ] ) && '' !== $person['texts'][ $pk_k ] ) :
				?>
				<div class="pqm-section">
					<h4><?php echo esc_html( $pk_h ); ?></h4>
					<p><?php echo wp_kses_post( $person['texts'][ $pk_k ] ); ?></p>
				</div>
				<?php
			endif;
		endforeach;
	endif;
	?>
</div>

<?php
/* ---------- تاریخچه نتایج کاربر ---------- */
if ( ! empty( $pk_history ) && 0 !== $pk_history_cnt ) :
	$pk_current_attempt_id = isset( $attempt['id'] ) ? (int) $attempt['id'] : 0;
	?>
	<div class="pqm-history">
		<h4 class="pqm-history-title">📊 <?php esc_html_e( 'تاریخچه نتایج شما', 'poian-quiz' ); ?></h4>
		<div class="pqm-history-list">
			<?php foreach ( $pk_history as $pk_h ) :
				$pk_is_current = ( $pk_current_attempt_id > 0 && (int) $pk_h['id'] === $pk_current_attempt_id );
				$pk_h_total    = array_sum( array_map( 'floatval', array_values( $pk_h['scores'] ) ) );
			?>
				<div class="pqm-history-item<?php echo $pk_is_current ? ' pqm-current' : ''; ?>">
					<div class="pqm-history-info">
						<span class="pqm-history-label">
							<?php echo esc_html( $pk_h['label'] ); ?>
							<?php if ( $pk_is_current ) : ?>
								<span class="pqm-current-badge"><?php esc_html_e( '(فعلی)', 'poian-quiz' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="pqm-history-date"><?php echo esc_html( wp_date( 'Y/m/d - H:i', strtotime( $pk_h['created_at'] ) ) ); ?></span>
					</div>
					<div class="pqm-history-score-wrap">
						<span class="pqm-history-score"><?php echo esc_html( number_format_i18n( $pk_h_total ) ); ?></span>
						<span class="pqm-history-max">/ <?php echo esc_html( number_format_i18n( $pk_max_total ) ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>
