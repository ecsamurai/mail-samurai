<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Sender {

	const MAX_ATTEMPTS = 3;

	/**
	 * テスト送信・プレビュー用の購読者トークン。
	 * 実在しない値なので、テストメールの解除リンクを押しても誰も解除されず、
	 * 「テスト用の解除リンクです」という案内ページが表示される。
	 */
	const TEST_TOKEN = '00000000000000000000000000000000';

	/**
	 * cronから5分おきに呼ばれるキュー処理。
	 * 1回の実行で最大 batch_size 通を送る(50通×5分間隔=600通/時。Xserverの1,500通/時制限内)。
	 *
	 * $only_subscriber_id を指定すると、その購読者の分だけを処理する
	 * (登録直後のDay0即時送信用。訪問者のリクエスト中に全キューを回さない)。
	 * 二重送信対策は deliver_rows() を参照。
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

		self::recover_and_skip( $t_del );

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

		self::deliver_rows( $t_del, $rows, 'delivery_id' );

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

		self::recover_and_skip( $t_rec );

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

		self::deliver_rows( $t_rec, $rows, 'recipient_id' );

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
	 * 送信キュー共通の前処理(ステップメール・一斉メールの両テーブルで使う)。
	 * 1. 送信中のままプロセスが落ちた行(15分以上放置)を pending に戻す
	 * 2. 解除・バウンス済み購読者の未送信分を skipped にする(バッチ枠を消費させない)
	 */
	private static function recover_and_skip( $table ) {
		global $wpdb;
		$t_sub = MS_DB::subscribers();
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'pending', claimed_at = NULL
			 WHERE status = 'sending' AND claimed_at IS NOT NULL AND claimed_at < %s",
			date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 15 * MINUTE_IN_SECONDS )
		) );
		$wpdb->query(
			"UPDATE {$table} q INNER JOIN {$t_sub} s ON s.id = q.subscriber_id
			 SET q.status = 'skipped'
			 WHERE q.status = 'pending' AND s.status != 'subscribed'"
		);
	}

	/**
	 * 抽出済みの行を1件ずつ「クレーム → 送信 → 結果記録」する。
	 * 二重送信対策:送信前に status を pending → sending へ原子的に更新して行を掴む。
	 * cronと登録時実行が同時に走っても、同じ行を掴めるのは片方だけ。
	 * 失敗は MAX_ATTEMPTS 回まで再試行し、それを超えたら failed にする。
	 */
	private static function deliver_rows( $table, $rows, $id_field ) {
		global $wpdb;
		foreach ( $rows as $row ) {
			$id      = (int) $row->$id_field;
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET status = 'sending', claimed_at = %s WHERE id = %d AND status = 'pending'",
				current_time( 'mysql' ), $id
			) );
			if ( ! $claimed ) {
				continue; // 他プロセスが処理中
			}

			$sent = self::send_mail( $row->email, $row->subject, $row->body_html, array(
				'name'        => $row->name,
				'sub_token'   => $row->sub_token,
				'track_token' => $row->track_token,
			) );

			if ( $sent ) {
				$update = array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ), 'claimed_at' => null );
			} else {
				$attempts = (int) $row->attempts + 1;
				$update   = array(
					'attempts'   => $attempts,
					'claimed_at' => null,
					'status'     => ( $attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'pending',
				);
			}
			$saved = $wpdb->update( $table, $update, array( 'id' => $id ) );
			if ( $saved && ( $sent || 'failed' === $update['status'] ) ) {
				MS_Activity::record_mail( MS_DB::deliveries() === $table ? 'sequence' : 'broadcast', $id, $sent ? 'sent' : 'failed' );
			}
		}
	}

	/**
	 * 送信失敗の理由を記録する(wp_mail は false を返すだけで理由を教えてくれないため)。
	 * メール侍が送っている間だけ wp_mail_failed を聞くので、他プラグインの失敗は混ざらない。
	 * 設定画面とテスト送信の結果表示で使う。
	 */
	public static function capture_failure( $error ) {
		$message = is_wp_error( $error ) ? $error->get_error_message() : '不明なエラー';
		update_option( 'ms_last_mail_error', array(
			'time'    => current_time( 'mysql' ),
			'message' => wp_strip_all_tags( $message ),
		), false );
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

		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_failure' ) );
		$sent = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', array( __CLASS__, 'capture_failure' ) );
		return $sent;
	}

	/**
	 * テスト送信・プレビューで使う差し込み内容。
	 * 計測(開封・クリック)はしないので、本文のリンクはそのまま本来のURLに飛ぶ。
	 */
	private static function test_context() {
		return array(
			'name'        => 'テスト太郎',
			'sub_token'   => self::TEST_TOKEN,
			'track_token' => null,
		);
	}

	/** ステップのテスト送信(自分宛)。 */
	public static function test_send( $step_id, $to ) {
		$step = MS_DB::get_step( $step_id );
		if ( ! $step ) {
			return false;
		}
		return self::send_test( $to, $step->subject, $step->body_html );
	}

	/** 件名・本文を指定してテスト送信する(メルマガの送信前確認など)。 */
	public static function send_test( $to, $subject, $body_html ) {
		return self::send_mail( $to, '【テスト】' . $subject, wp_kses_post( $body_html ), self::test_context() );
	}

	/** 実際に届くメールと同じ見た目のHTMLを返す(プレビュー画面用)。 */
	public static function render_preview( $subject, $body_html ) {
		$html = MS_Tracking::prepare_body( wp_kses_post( $body_html ), self::test_context() );
		return str_replace( '<head>', '<head><title>' . esc_html( $subject ) . '(プレビュー)</title>', $html );
	}
}
