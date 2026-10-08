<?php
/**
 * Plugin Name: メール侍 (Mail Samurai)
 * Plugin URI: https://github.com/ecsamurai/mail-samurai
 * Description: 自前インフラで動くステップメール/メルマガ配信プラグイン。登録日起点の個別タイムライン配信・開封/クリックトラッキング・ファネルレポート搭載。送信経路はWP Mail SMTP等のSMTPプラグインに委譲します。
 * Version: 1.4.0
 * Author: ecさむらい
 * Author URI: https://ecsamurai.com
 * Text Domain: mail-samurai
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MS_VERSION', '1.4.0' );
define( 'MS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MS_PLUGIN_DIR . 'includes/class-db.php';
require_once MS_PLUGIN_DIR . 'includes/class-activator.php';
require_once MS_PLUGIN_DIR . 'includes/class-subscriber.php';
require_once MS_PLUGIN_DIR . 'includes/class-sequence.php';
require_once MS_PLUGIN_DIR . 'includes/class-broadcast.php';
require_once MS_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once MS_PLUGIN_DIR . 'includes/class-sender.php';
require_once MS_PLUGIN_DIR . 'includes/class-tracking.php';
require_once MS_PLUGIN_DIR . 'public/class-form.php';
require_once MS_PLUGIN_DIR . 'public/class-endpoints.php';

if ( is_admin() ) {
	require_once MS_PLUGIN_DIR . 'admin/class-admin.php';
	new MS_Admin();
}

register_activation_hook( __FILE__, array( 'MS_Activator', 'activate' ) );

// プラグイン更新時のDBマイグレーション(ZIP上書き更新では activation フックが
// 走らないため、バージョン差分を検知して dbDelta を再実行する)
add_action( 'plugins_loaded', function () {
	if ( get_option( 'ms_db_version' ) !== MS_VERSION ) {
		MS_Activator::activate();
	}
} );
register_deactivation_hook( __FILE__, array( 'MS_Activator', 'deactivate' ) );

new MS_Scheduler();
new MS_Form();
new MS_Endpoints();
