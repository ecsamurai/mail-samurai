<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Activator {

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		// ① 購読者
		$sql1 = 'CREATE TABLE ' . MS_DB::subscribers() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(191) NOT NULL,
			name VARCHAR(100) DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'subscribed',
			sequence_id BIGINT UNSIGNED NOT NULL,
			token CHAR(32) NOT NULL,
			source VARCHAR(50) DEFAULT '',
			created_at DATETIME NOT NULL,
			unsubscribed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_seq (email, sequence_id),
			KEY status (status)
		) $charset;";

		// ② シーケンス
		$sql2 = 'CREATE TABLE ' . MS_DB::sequences() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) $charset;";

		// ③ ステップ
		$sql3 = 'CREATE TABLE ' . MS_DB::steps() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sequence_id BIGINT UNSIGNED NOT NULL,
			step_no SMALLINT UNSIGNED NOT NULL,
			send_offset INT UNSIGNED NOT NULL DEFAULT 0,
			send_hour TINYINT DEFAULT NULL,
			subject VARCHAR(255) NOT NULL,
			body_html LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			PRIMARY KEY  (id),
			UNIQUE KEY seq_step (sequence_id, step_no)
		) $charset;";

		// ④ 配信記録(キュー兼ログ)
		$sql4 = 'CREATE TABLE ' . MS_DB::deliveries() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			step_id BIGINT UNSIGNED NOT NULL,
			track_token CHAR(32) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			scheduled_at DATETIME NOT NULL,
			sent_at DATETIME DEFAULT NULL,
			opened_at DATETIME DEFAULT NULL,
			clicked_at DATETIME DEFAULT NULL,
			clicked_url VARCHAR(500) DEFAULT NULL,
			bounced_at DATETIME DEFAULT NULL,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			claimed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY sub_step (subscriber_id, step_id),
			KEY worker (status, scheduled_at),
			KEY track (track_token)
		) $charset;";

		// ⑤ 一斉メール本体
		$sql5 = 'CREATE TABLE ' . MS_DB::broadcasts() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sequence_id BIGINT UNSIGNED NOT NULL,
			subject VARCHAR(255) NOT NULL,
			body_html LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			created_at DATETIME NOT NULL,
			completed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset;";

		// ⑥ 一斉メールの個別送信キュー
		$sql6 = 'CREATE TABLE ' . MS_DB::broadcast_recipients() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			broadcast_id BIGINT UNSIGNED NOT NULL,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			track_token CHAR(32) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			sent_at DATETIME DEFAULT NULL,
			opened_at DATETIME DEFAULT NULL,
			clicked_at DATETIME DEFAULT NULL,
			clicked_url VARCHAR(500) DEFAULT NULL,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			claimed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY broadcast_subscriber (broadcast_id, subscriber_id),
			KEY worker (status),
			KEY track (track_token),
			KEY broadcast (broadcast_id)
		) $charset;";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );
		dbDelta( $sql5 );
		dbDelta( $sql6 );

		// ⑦ 個別行動履歴。既存の配信・初回反応の記録は変更しない。
		dbDelta( 'CREATE TABLE ' . MS_DB::activities() . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(24) NOT NULL,
			mail_type VARCHAR(12) NOT NULL DEFAULT '',
			mail_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			subject VARCHAR(255) NOT NULL DEFAULT '',
			url TEXT NOT NULL,
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY subscriber_time (subscriber_id, created_at, id),
			KEY subscriber_event (subscriber_id, event_type, created_at, id),
			KEY created_at (created_at)
		) $charset;" );

		// 既定オプション
		add_option( 'ms_from_name', get_bloginfo( 'name' ) );
		add_option( 'ms_from_email', get_option( 'admin_email' ) );
		add_option( 'ms_batch_size', 50 );
		add_option( 'ms_track_open', 1 );
		add_option( 'ms_track_click', 1 );
		add_option( 'ms_last_cron', 0 );
		add_option( 'ms_activity_retention_days', 365 );

		// 初期シーケンス(空)を1本作成
		global $wpdb;
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MS_DB::sequences() );
		if ( 0 === $count ) {
			$wpdb->insert( MS_DB::sequences(), array(
				'name'       => 'メイン配信',
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			) );
		}

		// DBスキーマのバージョン記録(mail-samurai.php の起動時チェックが参照)
		update_option( 'ms_db_version', MS_VERSION );

		// cron登録(5分間隔)
		if ( ! wp_next_scheduled( 'ms_process_queue' ) ) {
			wp_schedule_event( time(), 'ms_five_minutes', 'ms_process_queue' );
		}
		if ( ! wp_next_scheduled( 'ms_cleanup_activities' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ms_cleanup_activities' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'ms_process_queue' );
		wp_clear_scheduled_hook( 'ms_cleanup_activities' );
	}
}
