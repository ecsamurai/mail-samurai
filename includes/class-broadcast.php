<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** 一度だけ送るメルマガの作成・キュー管理。 */
class MS_Broadcast {

	/**
	 * 現在購読中の人へ一斉メールを予約する。
	 * 送信処理自体は既存のcronキューに任せ、管理画面のリクエストを長時間止めない。
	 */
	public static function create( $sequence_id, $subject, $body_html ) {
		global $wpdb;

		$sequence = MS_DB::get_sequence( $sequence_id );
		if ( ! $sequence ) {
			return new WP_Error( 'invalid_sequence', '配信リストが見つかりません。' );
		}

		$subject = sanitize_text_field( $subject );
		if ( '' === $subject ) {
			return new WP_Error( 'empty_subject', '件名を入力してください。' );
		}

		$ok = $wpdb->insert( MS_DB::broadcasts(), array(
			'sequence_id' => $sequence_id,
			'subject'     => $subject,
			'body_html'   => wp_kses_post( $body_html ),
			'status'      => 'queued',
			'created_at'  => current_time( 'mysql' ),
		) );
		if ( ! $ok ) {
			return new WP_Error( 'db_error', 'メルマガの予約作成に失敗しました。' );
		}

		$broadcast_id = (int) $wpdb->insert_id;
		$subscribers = $wpdb->get_col( $wpdb->prepare(
			'SELECT id FROM ' . MS_DB::subscribers() . " WHERE sequence_id = %d AND status = 'subscribed'",
			$sequence_id
		) );

		foreach ( $subscribers as $subscriber_id ) {
			$wpdb->query( $wpdb->prepare(
				'INSERT IGNORE INTO ' . MS_DB::broadcast_recipients() . ' (broadcast_id, subscriber_id, track_token, status) VALUES (%d, %d, %s, %s)',
				$broadcast_id, (int) $subscriber_id, MS_DB::token(), 'pending'
			) );
		}

		if ( empty( $subscribers ) ) {
			$wpdb->update( MS_DB::broadcasts(), array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			), array( 'id' => $broadcast_id ) );
		}

		return array( 'id' => $broadcast_id, 'recipients' => count( $subscribers ) );
	}

	public static function recent( $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT b.*, COUNT(r.id) AS recipients,
				SUM(r.status = \'sent\') AS sent,
				SUM(r.status = \'sent\' AND r.opened_at IS NOT NULL) AS opened,
				SUM(r.status = \'sent\' AND r.clicked_at IS NOT NULL) AS clicked,
				SUM(r.status = \'pending\' OR r.status = \'sending\') AS remaining,
				SUM(r.status = \'failed\') AS failed
			 FROM ' . MS_DB::broadcasts() . ' b
			 LEFT JOIN ' . MS_DB::broadcast_recipients() . ' r ON r.broadcast_id = b.id
			 GROUP BY b.id ORDER BY b.id DESC LIMIT %d',
			max( 1, $limit )
		) );
	}
}
