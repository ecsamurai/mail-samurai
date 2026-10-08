<?php
/**
 * プラグイン「削除」時のクリーンアップ。
 * 注意: 停止(無効化)ではデータは消えません。削除時のみ全テーブル・全オプションを消します。
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'ms_deliveries',
	$wpdb->prefix . 'ms_steps',
	$wpdb->prefix . 'ms_subscribers',
	$wpdb->prefix . 'ms_sequences',
	$wpdb->prefix . 'ms_broadcast_recipients',
	$wpdb->prefix . 'ms_broadcasts',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$options = array( 'ms_from_name', 'ms_from_email', 'ms_batch_size', 'ms_track_open', 'ms_track_click', 'ms_last_cron', 'ms_db_version', 'ms_last_mail_error' );
foreach ( $options as $opt ) {
	delete_option( $opt );
}

wp_clear_scheduled_hook( 'ms_process_queue' );
