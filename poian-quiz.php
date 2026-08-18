<?php
/**
 * Plugin Name: Poian Quiz — فرم‌ساز و آزمون‌ساز پویان
 * Description: موتور فرم‌ساز/آزمون‌ساز اینترپرایز با موتورهای امتیازدهی تزریقی، نمودارها، اکشن‌ها و API سازمانی.
 * Version:     1.0.13
 * Author:      Poian
 * Text Domain: poian-quiz
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * License:     GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;

define( 'POIAN_QUIZ_VERSION', '1.0.13' );
define( 'POIAN_QUIZ_PLUGIN_FILE', __FILE__ );
define( 'POIAN_QUIZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POIAN_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'POIAN_QUIZ_OPTION_KEY', 'poian_quiz_settings' );
define( 'POIAN_QUIZ_CAP', 'poian_quiz_manage' );
define( 'POIAN_QUIZ_NONCE_ACTION', 'poian_quiz_submit' );
define( 'POIAN_QUIZ_CPT', 'poian_quiz' );

require_once POIAN_QUIZ_PLUGIN_DIR . 'includes/class-poian-quiz-autoloader.php';
Poian_Quiz_Autoloader::register();

register_activation_hook( __FILE__, array( 'Poian_Quiz_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Poian_Quiz_Deactivator', 'deactivate' ) );

Poian_Quiz_Plugin::instance()->boot();
