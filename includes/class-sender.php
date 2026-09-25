<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Sender {

	const MAX_ATTEMPTS = 3;

	/**
	 * cronから5分おきに呼ばれるキュー処理。
	 * 1回の実行で最大 batch_size 通を送る(50通×5分間隔=600通/時。Xserverの1,500通/時制限内)。
	 *
	 * $only_subscriber_id を指定すると、その購読者の分だけを処理する
	 * (登録直後のDay0即時送信用。訪問者のリクエスト中に全キューを回さない)。
	 *
	 * 二重送信対策:送信前に status を pending → sending へ原子的に更新して
	 * 行を「クレーム」する。cronと登録時実行が同時に走っても、
	 * 同じ行を掴めるのは片方だけになる。
	 */
	public static function process_queue( $only_subscriber_id = 0 ) {
		global $wpdb;

		// cron死活監視用(設定画面のインジケータが読む)
		if ( ! $only_subscriber_id ) {
			update_option( 'ms_last_cron', time() );
		}

		$batch = max( 1, (int) get_option( 'ms_batch_size', 50 ) );
		$now   = current_time( 'mysql' );

		$t_del  = MS_DB::deliveries();
		$t_sub  = MS_DB::subscribers();
		$t_step = MS_DB::steps();
		$t_seq  = MS_DB::sequences();

		// 復旧:送信中のままプロセスが落ちた行(15分以上放置)をpendingへ戻す
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$t_del} SET status = 'pending', claimed_at = NULL
			 WHERE status = 'sending' AND claimed_at IS NOT NULL AND claimed_at < %s",
			date( 'Y-m-d H:i:s', strtotime( $now ) - 15 * MINUTE_IN_SECONDS )
		) );

		// 掃除:解除・バウンス済み購読者の未送信予約をskippedへ
		// (バッチ枠を消費させないため、SELECT前にまとめて処理)
		$wpdb->query(
			"UPDATE {$t_del} d INNER JOIN {$t_sub} s ON s.id = d.subscriber_id
			 SET d.status = 'skipped'
			 WHERE d.status = 'pending' AND s.status != 'subscribed'"
		);

		// 抽出条件をWHERE句で完結させる。
		// 下書きステップ・停止中シーケンスの予約はpendingのまま「選ばれない」
		// (以前はSELECT後にcontinueしていたため、詰まるとバッチ枠を
		//  食い尽くして後続の正常な配信が止まる不具合があった)。
		$sql = "SELECT d.id AS delivery_id, d.subscriber_id, d.step_id, d.track_token, d.attempts,
		               s.email, s.name, s.token AS sub_token,
		               st.subject, st.body_html
		        FROM {$t_del} d
		        INNER JOIN {$t_sub} s   ON s.id  = d.subscriber_id
		        INNER JOIN {$t_step} st ON st.id = d.step_id
		        INNER JOIN {$t_seq} sq  ON sq.id = st.sequence_id
		        WHERE d.status = 'pending' AND d.scheduled_at <= %s
		          AND s.status  = 'subscribed'
		          AND st.status = 'active'
		          AND sq.status = 'active'";
		$args = array( $now );

		if ( $only_subscriber_id ) {
			$sql   .= ' AND d.subscriber_id = %d';
			$args[] = $only_subscriber_id;
		}

		$sql   .= ' ORDER BY d.scheduled_at ASC LIMIT %d';
		$args[] = $batch;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		foreach ( $rows as $row ) {

			// 行をクレーム(掴めなければ他プロセスが処理中なのでスキップ)
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$t_del} SET status = 'sending', claimed_at = %s
				 WHERE id = %d AND status = 'pending'",
				current_time( 'mysql' ), $row->delivery_id
			) );
			if ( ! $claimed ) {
				continue;
			}

			$sent = self::send_mail( $row->email, $row->subject, $row->body_html, array(
				'name'        => $row->name,
				'sub_token'   => $row->sub_token,
				'track_token' => $row->track_token,
			) );

			if ( $sent ) {
				$wpdb->update( MS_DB::deliveries(), array(
					'status'     => 'sent',
					'sent_at'    => current_time( 'mysql' ),
					'claimed_at' => null,
				), array( 'id' => $row->delivery_id ) );
			} else {
				$attempts = (int) $row->attempts + 1;
				$update   = array(
					'attempts'   => $attempts,
					'claimed_at' => null,
					'status'     => ( $attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'pending',
				);
				$wpdb->update( MS_DB::deliveries(), $update, array( 'id' => $row->delivery_id ) );
			}
		}

		// 一斉メールも同じ送信上限を共有する。Day0の即時送信では扱わない。
		if ( ! $only_subscriber_id ) {
			self::process_broadcast_queue( max( 0, $batch - count( $rows ) ) );
		}
	}

	/** 一斉メールの送信キュー。通常のステップメールと合計で1回のバッチ上限を守る。 */
	public static function process_broadcast_queue( $limit ) {
		global $wpdb;
		$limit = (int) $limit;
		if ( $limit < 1 ) {
			return;
		}

		$t_rec = MS_DB::broadcast_recipients();
		$t_brd = MS_DB::broadcasts();
		$t_sub = MS_DB::subscribers();
		$now   = current_time( 'mysql' );

		// 処理途中で落ちた送信を復旧し、解除・バウンス済みの人は送らない。
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$t_rec} SET status = 'pending', claimed_at = NULL
			 WHERE status = 'sending' AND claimed_at IS NOT NULL AND claimed_at < %s",
			date( 'Y-m-d H:i:s', strtotime( $now ) - 15 * MINUTE_IN_SECONDS )
		) );
		$wpdb->query(
			"UPDATE {$t_rec} r INNER JOIN {$t_sub} s ON s.id = r.subscriber_id
			 SET r.status = 'skipped' WHERE r.status = 'pending' AND s.status != 'subscribed'"
		);

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.id AS recipient_id, r.attempts, r.track_token, b.id AS broadcast_id, b.subject, b.body_html,
			        s.email, s.name, s.token AS sub_token
			 FROM {$t_rec} r
			 INNER JOIN {$t_brd} b ON b.id = r.broadcast_id
			 INNER JOIN {$t_sub} s ON s.id = r.subscriber_id
			 WHERE r.status = 'pending' AND b.status = 'queued' AND s.status = 'subscribed'
			 ORDER BY b.created_at ASC, r.id ASC LIMIT %d",
			$limit
		) );

		foreach ( $rows as $row ) {
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$t_rec} SET status = 'sending', claimed_at = %s WHERE id = %d AND status = 'pending'",
				current_time( 'mysql' ), $row->recipient_id
			) );
			if ( ! $claimed ) {
				continue;
			}

			$sent = self::send_mail( $row->email, $row->subject, $row->body_html, array(
				'name'        => $row->name,
				'sub_token'   => $row->sub_token,
				'track_token' => $row->track_token,
			) );

			if ( $sent ) {
				$wpdb->update( $t_rec, array(
					'status'     => 'sent',
					'sent_at'    => current_time( 'mysql' ),
					'claimed_at' => null,
				), array( 'id' => $row->recipient_id ) );
			} else {
				$attempts = (int) $row->attempts + 1;
				$wpdb->update( $t_rec, array(
					'attempts'   => $attempts,
					'claimed_at' => null,
					'status'     => ( $attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'pending',
				), array( 'id' => $row->recipient_id ) );
			}
		}


		// 解除などで全宛先がスキップされた場合も含め、未送信がなければ完了にする。
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$t_brd} b SET b.status = 'completed', b.completed_at = %s
			 WHERE b.status = 'queued'
			 AND NOT EXISTS (
				SELECT 1 FROM {$t_rec} r
				WHERE r.broadcast_id = b.id AND r.status IN ('pending','sending')
			 )",
			current_time( 'mysql' )
		) );
	}

	/**
	 * 1通の送信。トラッキング加工→テンプレート適用→wp_mail。
	 * SMTP接続はWP Mail SMTP等のプラグインが phpmailer_init でよしなにやる前提(仕様書 設計原則1)。
	 */
	public static function send_mail( $to, $subject, $body_html, $ctx ) {
		$body = MS_Tracking::prepare_body( $body_html, $ctx );

		$from_name  = get_option( 'ms_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'ms_from_email', get_option( 'admin_email' ) );

		$headers   = array();
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * テスト送信:トラッキングなし・ダミー差し込みで指定アドレスへ即送信。
	 */
	public static function test_send( $step_id, $to ) {
		$step = MS_DB::get_step( $step_id );
		if ( ! $step ) {
			return false;
		}
		return self::send_mail( $to, '【テスト】' . $step->subject, $step->body_html, array(
			'name'        => 'テスト太郎',
			'sub_token'   => 'test',
			'track_token' => null, // トラッキング無効
		) );
	}
}
