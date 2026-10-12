<?php
/**
 * Plugin Name: Yandex AI Studio Assistant
 * Plugin URI: https://ворд-пресс.рф/plugins/yandex-ai-studio-assistant/
 * Update URI: https://xn----ctbgf6biedka.xn--p1ai/plugins/yandex-ai-studio-assistant/
 * Description: ИИ-помощник для анализа контента WordPress и подготовки записей с моделями Yandex AI Studio. Изменения только после подтверждения администратора.
 * Version: 1.1.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Valery Molodtsov
 * Author URI: https://ворд-пресс.рф/
 * License: GPL-2.0-or-later
 * Text Domain: wpr-yandex-ai-studio
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WPR_YAI_VERSION', '1.1.2' );
define( 'WPR_YAI_ROOT', plugin_dir_path( __FILE__ ) );
define( 'WPR_YAI_URL', plugin_dir_url( __FILE__ ) );
define( 'WPR_YAI_DOCS', 'https://ворд-пресс.рф/solutions/yandex-ai-studio-wordpress-setup/' );
require_once WPR_YAI_ROOT . 'includes/class-models.php';
require_once WPR_YAI_ROOT . 'includes/class-contract.php';
require_once WPR_YAI_ROOT . 'includes/class-core.php';
require_once WPR_YAI_ROOT . 'includes/class-admin.php';
add_action( 'plugins_loaded', array( 'WPR_YAI_Core', 'register' ) );
add_action( 'plugins_loaded', array( 'WPR_YAI_Admin', 'register' ) );

add_action( 'wp_ajax_wpr_yai_models', array( 'WPR_YAI_Models', 'ajax_models' ) );
add_action( 'wp_ajax_wpr_yai_contract', array( 'WPR_YAI_Contract', 'ajax_contract' ) );

/** WPR Update Hub v1.1: opt-in install telemetry, no forced auto-updates. */
require_once __DIR__ . '/includes/wpr-updates-client.php';
WPR_Updates_Client_V2::register(__FILE__, array(
    'slug' => 'yandex-ai-studio-assistant',
    'server' => 'https://xn----ctbgf6biedka.xn--p1ai',
    'telemetry' => true,
));
