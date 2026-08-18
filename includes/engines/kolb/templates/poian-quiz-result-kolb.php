<?php
defined( 'ABSPATH' ) || exit;
/** vars: $result, $attempt */
$pk_x     = isset( $result['extra']['x'] ) ? (int) $result['extra']['x'] : 0;
$pk_y     = isset( $result['extra']['y'] ) ? (int) $result['extra']['y'] : 0;
$pk_max   = Poian_Quiz_Engine_Kolb::AXIS_MAX;
$pk_c     = 240;
$pk_scale = 200 / $pk_max;
$pk_px    = $pk_c + (int) round( $pk_x * $pk_scale );
$pk_py    = $pk_c - (int) round( $pk_y * $pk_scale );
$pk_right = $pk_px >= $pk_c;
$pk_above = $pk_py <= $pk_c;
$pk_sc    = isset( $result['scores'] ) ? (array) $result['scores'] : array();
?>
<div class="pqk-head">
	<div class="pqk-badge">
		<span><?php echo esc_html( isset( $result['result_label'] ) ? $result['result_label'] : '' ); ?></span>
	</div>
</div>

<div class="pqk-chart-wrap">
	<svg class="pqk-chart" viewBox="0 0 480 480" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'نمودار سبک یادگیری: %s', 'poian-quiz' ), isset( $result['result_label'] ) ? $result['result_label'] : '' ) ); ?>">
		<rect class="pqk-qt pqk-qt-tr" x="240" y="10" width="230" height="230"/>
		<rect class="pqk-qt pqk-qt-tl" x="10" y="10" width="230" height="230"/>
		<rect class="pqk-qt pqk-qt-br" x="240" y="240" width="230" height="230"/>
		<rect class="pqk-qt pqk-qt-bl" x="10" y="240" width="230" height="230"/>
		<line class="pqk-axis" x1="40" y1="240" x2="440" y2="240"/>
		<line class="pqk-axis" x1="240" y1="40" x2="240" y2="440"/>
		<text class="pqk-axis-label" x="460" y="240" transform="rotate(90, 460, 240)" text-anchor="middle"><?php esc_html_e( 'مشاهده تأملی', 'poian-quiz' ); ?></text>
		<text class="pqk-axis-label" x="20" y="240" transform="rotate(-90, 20, 240)" text-anchor="middle"><?php esc_html_e( 'آزمایشگری فعال', 'poian-quiz' ); ?></text>
		<text class="pqk-axis-label" x="240" y="30" text-anchor="middle"><?php esc_html_e( 'تجربه عینی', 'poian-quiz' ); ?></text>
		<text class="pqk-axis-label" x="240" y="460" text-anchor="middle"><?php esc_html_e( 'مفهوم‌سازی انتزاعی', 'poian-quiz' ); ?></text>
		<text class="pqk-q-label" x="360" y="80" text-anchor="middle"><?php esc_html_e( 'مبتکر', 'poian-quiz' ); ?></text>
		<text class="pqk-q-label" x="120" y="80" text-anchor="middle"><?php esc_html_e( 'عملگرا', 'poian-quiz' ); ?></text>
		<text class="pqk-q-label" x="360" y="410" text-anchor="middle"><?php esc_html_e( 'برنامه‌ریز', 'poian-quiz' ); ?></text>
		<text class="pqk-q-label" x="120" y="410" text-anchor="middle"><?php esc_html_e( 'تصمیم‌گیر', 'poian-quiz' ); ?></text>
		<?php if ( 0 !== $pk_y ) : ?>
			<line class="pqk-guide" x1="<?php echo (int) $pk_px; ?>" y1="<?php echo (int) $pk_py; ?>" x2="240" y2="<?php echo (int) $pk_py; ?>"/>
			<text class="pqk-val" direction="ltr" x="<?php echo $pk_right ? 222 : 258; ?>" y="<?php echo (int) $pk_py + 5; ?>" text-anchor="<?php echo $pk_right ? 'end' : 'start'; ?>"><?php echo esc_html( $pk_y > 0 ? '+' . $pk_y : (string) $pk_y ); ?></text>
		<?php endif; ?>
		<?php if ( 0 !== $pk_x ) : ?>
			<line class="pqk-guide" x1="<?php echo (int) $pk_px; ?>" y1="<?php echo (int) $pk_py; ?>" x2="<?php echo (int) $pk_px; ?>" y2="240"/>
			<text class="pqk-val" direction="ltr" x="<?php echo (int) $pk_px; ?>" y="<?php echo $pk_above ? 272 : 216; ?>" text-anchor="middle"><?php echo esc_html( $pk_x > 0 ? '+' . $pk_x : (string) $pk_x ); ?></text>
		<?php endif; ?>
		<circle class="pqk-point-halo" cx="<?php echo (int) $pk_px; ?>" cy="<?php echo (int) $pk_py; ?>" r="10"/>
		<circle class="pqk-point" cx="<?php echo (int) $pk_px; ?>" cy="<?php echo (int) $pk_py; ?>" r="9"/>
	</svg>
</div>

<div class="pqm-bars">
	<?php
	$pk_map = array( 'ce' => __( 'تجربه عینی', 'poian-quiz' ), 'ro' => __( 'مشاهده تأملی', 'poian-quiz' ), 'ac' => __( 'مفهوم‌سازی انتزاعی', 'poian-quiz' ), 'ae' => __( 'آزمایشگری فعال', 'poian-quiz' ) );
	foreach ( $pk_map as $pk_k => $pk_l ) :
		$pk_v = isset( $pk_sc[ $pk_k ] ) ? min( 48, max( 0, (int) $pk_sc[ $pk_k ] ) ) : 0;
	?>
		<div class="pqm-bar-row">
			<span class="pqm-bar-label"><?php echo esc_html( $pk_l ); ?></span>
			<div class="pqm-bar-track"><div class="pqm-bar-fill" style="width:<?php echo esc_attr( ( $pk_v / 48 ) * 100 ); ?>%"></div></div>
			<span class="pqm-bar-val"><?php echo esc_html( number_format_i18n( $pk_v ) ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
