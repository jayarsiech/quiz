<?php
defined( 'ABSPATH' ) || exit;

abstract class Poian_Quiz_Engine {

	abstract public function get_id();
	abstract public function get_title();

	public function get_field_types() {
		return array( 'radio', 'checkbox', 'rank', 'text', 'textarea', 'description', 'heading' );
	}

	public function supports_option_weights() {
		return false;
	}
	/**
	 * لود دارایی‌های اختصاصی موتور. موتورهای فرزند می‌توانند override کنند.
	 */
	public function enqueue_assets() {
		// پیش‌فرض: هیچ دارایی اختصاصی ندارد
	}
	/**
	 * @param array $answers خروجی Schema::validate_submission
	 * @param array $schema  اسکیما فرم
	 * @return array {scores:array, result_slug:string, result_label:string, extra:array}
	 */
	abstract public function compute( array $answers, array $schema );

	public function render_result( array $result, array $attempt ) {
		return '';
	}
}
