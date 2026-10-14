<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		// フォーム処理(admin-post)
		add_action( 'admin_post_ms_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ms_save_step', array( $this, 'save_step' ) );
		add_action( 'admin_post_ms_delete_step', array( $this, 'delete_step' ) );
		add_action( 'admin_post_ms_test_send', array( $this, 'test_send' ) );
		add_action( 'admin_post_ms_add_sequence', array( $this, 'add_sequence' ) );
		add_action( 'admin_post_ms_toggle_sequence', array( $this, 'toggle_sequence' ) );
		add_action( 'admin_post_ms_delete_sequence', array( $this, 'delete_sequence' ) );
		add_action( 'admin_post_ms_unsub_subscriber', array( $this, 'unsub_subscriber' ) );
		add_action( 'admin_post_ms_send_broadcast', array( $this, 'send_broadcast' ) );
	}

	public function menu() {
		add_menu_page( 'メール侍', 'メール侍', 'manage_options', 'ms-dashboard', array( $this, 'page_dashboard' ), 'dashicons-email-alt', 26 );
		add_submenu_page( 'ms-dashboard', 'ダッシュボード', 'ダッシュボード', 'manage_options', 'ms-dashboard', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'ms-dashboard', 'シーケンス', 'シーケンス', 'manage_options', 'ms-sequences', array( $this, 'page_sequences' ) );
		add_submenu_page( 'ms-dashboard', '登録フォーム', '登録フォーム', 'manage_options', 'ms-form', array( $this, 'page_form' ) );
		add_submenu_page( 'ms-dashboard', '購読者', '購読者', 'manage_options', 'ms-subscribers', array( $this, 'page_subscribers' ) );
		add_submenu_page( 'ms-dashboard', 'メルマガ', 'メルマガ', 'manage_options', 'ms-broadcasts', array( $this, 'page_broadcasts' ) );
		add_submenu_page( 'ms-dashboard', '設定', '設定', 'manage_options', 'ms-settings', array( $this, 'page_settings' ) );
	}

	public function assets( $hook ) {
		if ( false !== strpos( $hook, 'ms-' ) ) {
			wp_enqueue_style( 'ms-admin', MS_PLUGIN_URL . 'admin/assets/admin.css', array(), MS_VERSION );
		}
	}

	/* ---------- 画面 ---------- */

	public function page_dashboard() {
		$sequences   = MS_DB::get_sequences();
		$sequence_id = isset( $_GET['sequence_id'] ) ? (int) $_GET['sequence_id'] : ( $sequences ? (int) $sequences[0]->id : 0 );
		$days        = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30;
		$kpi         = self::kpi_summary( $sequence_id, $days );
		$report      = self::step_report( $sequence_id );
		include MS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * 登録フォーム画面:ショートコードの作成・コピー・設置手順・プレビュー。
	 */
	public function page_form() {
		$sequences   = MS_DB::get_sequences();
		$sequence_id = isset( $_GET['sequence_id'] ) ? (int) $_GET['sequence_id'] : ( $sequences ? (int) $sequences[0]->id : 0 );
		$fb_name     = ( isset( $_GET['name'] ) && 'yes' === $_GET['name'] ) ? 'yes' : 'no';
		$fb_button   = isset( $_GET['button'] ) ? sanitize_text_field( wp_unslash( $_GET['button'] ) ) : '無料で受け取る';
		$fb_button   = trim( str_replace( array( '"', '[', ']' ), '', $fb_button ) );
		$fb_source   = isset( $_GET['source'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) wp_unslash( $_GET['source'] ) ) : '';

		$fb_color    = isset( $_GET['color'] ) ? MS_Form::sanitize_color( wp_unslash( $_GET['color'] ) ) : MS_Form::DEFAULT_COLOR;

		$shortcode = '[mailsamurai_form sequence="' . $sequence_id . '"';
		if ( 'no' === $fb_name ) {
			$shortcode .= ' name="no"';
		}
		if ( '' !== $fb_button ) {
			$shortcode .= ' button="' . $fb_button . '"';
		}
		if ( MS_Form::DEFAULT_COLOR !== $fb_color ) {
			$shortcode .= ' color="' . $fb_color . '"';
		}
		if ( '' !== $fb_source ) {
			$shortcode .= ' source="' . $fb_source . '"';
		}
		$shortcode .= ']';

		// 登録直後(offset=0)の稼働ステップがあるか
		$has_day0 = false;
		if ( $sequence_id ) {
			foreach ( MS_DB::get_steps( $sequence_id ) as $st ) {
				// 時刻指定(例: 8:00)があると「登録直後」ではなく次のその時刻になる
				if ( 0 === (int) $st->send_offset && 'active' === $st->status && ( null === $st->send_hour || '' === $st->send_hour ) ) {
					$has_day0 = true;
					break;
				}
			}
		}

		// プレビュー(送信できないようにする)
		$preview_html = '';
		if ( $sequence_id ) {
			$preview_html = do_shortcode( $shortcode );
			$preview_html = str_replace( '<form ', '<form onsubmit="return false;" ', $preview_html );
		}

		include MS_PLUGIN_DIR . 'admin/views/form.php';
	}

	public function page_sequences() {
		$sequences   = MS_DB::get_sequences();
		$sequence_id = isset( $_GET['sequence_id'] ) ? (int) $_GET['sequence_id'] : ( $sequences ? (int) $sequences[0]->id : 0 );
		$sequence    = $sequence_id ? MS_DB::get_sequence( $sequence_id ) : null;
		$steps       = $sequence_id ? MS_DB::get_steps( $sequence_id ) : array();
		$edit_step   = isset( $_GET['step_id'] ) ? MS_DB::get_step( (int) $_GET['step_id'] ) : null;
		$stats       = self::step_report( $sequence_id );
		$can_delete_sequence = $sequence_id ? MS_Sequence::is_empty( $sequence_id ) : false;
		$stats_by_id = array();
		foreach ( $stats as $s ) {
			$stats_by_id[ $s['step_id'] ] = $s;
		}
		include MS_PLUGIN_DIR . 'admin/views/sequences.php';
	}

	public function page_subscribers() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}

		$view_id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
		if ( $view_id ) {
			$subscriber = MS_DB::get_subscriber( $view_id );
			$timeline   = $subscriber ? self::subscriber_timeline( $view_id ) : array();
			$activity_filter = isset( $_GET['activity_type'] ) && is_string( $_GET['activity_type'] ) ? sanitize_key( $_GET['activity_type'] ) : '';
			if ( ! isset( MS_Activity::labels()[ $activity_filter ] ) ) $activity_filter = '';
			$activity_page = isset( $_GET['activity_page'] ) ? max( 1, (int) $_GET['activity_page'] ) : 1;
			$activity = $subscriber ? MS_Activity::page( $view_id, $activity_filter, $activity_page ) : array();
			include MS_PLUGIN_DIR . 'admin/views/subscriber-detail.php';
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per    = 50;

		$where = ' WHERE 1=1';
		$args  = array();
		if ( $search ) {
			$where .= ' AND email LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( $status ) {
			$where .= ' AND status = %s';
			$args[] = $status;
		}

		$sql_total = 'SELECT COUNT(*) FROM ' . MS_DB::subscribers() . $where;
		$total     = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql_total, $args ) ) : $wpdb->get_var( $sql_total ) );

		$sql  = 'SELECT * FROM ' . MS_DB::subscribers() . $where . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$args2 = array_merge( $args, array( $per, ( $paged - 1 ) * $per ) );
		$subscribers = $wpdb->get_results( $wpdb->prepare( $sql, $args2 ) );

		// 進捗と最終反応をまとめて取得
		$progress = array();
		if ( $subscribers ) {
			$ids = implode( ',', array_map( 'intval', wp_list_pluck( $subscribers, 'id' ) ) );
			$rows = $wpdb->get_results(
				"SELECT subscriber_id,
				        SUM(status = 'sent') AS sent_count,
				        COUNT(*) AS total_count,
				        MAX(GREATEST(COALESCE(opened_at,'1970-01-01'), COALESCE(clicked_at,'1970-01-01'))) AS last_reaction,
				        MAX(clicked_at IS NOT NULL) AS has_click
				 FROM " . MS_DB::deliveries() . "
				 WHERE subscriber_id IN ($ids)
				 GROUP BY subscriber_id"
			);
			foreach ( $rows as $r ) {
				$progress[ $r->subscriber_id ] = $r;
			}
		}

		include MS_PLUGIN_DIR . 'admin/views/subscribers.php';
	}

	public function page_settings() {
		include MS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function page_broadcasts() {
		$sequences  = MS_DB::get_sequences();
		$broadcasts = MS_Broadcast::recent();
		$draft      = get_user_meta( get_current_user_id(), 'ms_broadcast_draft', true );
		$draft      = is_array( $draft ) ? $draft : array( 'sequence_id' => 0, 'subject' => '', 'body_html' => '' );
		$test_email = get_user_meta( get_current_user_id(), 'ms_test_email', true );
		$test_email = $test_email ? $test_email : wp_get_current_user()->user_email;
		include MS_PLUGIN_DIR . 'admin/views/broadcasts.php';
	}

	/* ---------- 集計 ---------- */

	/** 上段KPIカード(仕様書 画面1) */
	public static function kpi_summary( $sequence_id, $days ) {
		global $wpdb;
		$since      = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );
		$since_prev = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 2 * $days * DAY_IN_SECONDS );

		$t_sub = MS_DB::subscribers();
		$t_del = MS_DB::deliveries();
		$t_stp = MS_DB::steps();

		$new_subs = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $t_sub WHERE sequence_id = %d AND created_at >= %s", $sequence_id, $since
		) );
		$prev_subs = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $t_sub WHERE sequence_id = %d AND created_at >= %s AND created_at < %s",
			$sequence_id, $since_prev, $since
		) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS sent,
			        SUM(d.opened_at IS NOT NULL) AS opened,
			        SUM(d.clicked_at IS NOT NULL) AS clicked
			 FROM $t_del d INNER JOIN $t_stp st ON st.id = d.step_id
			 WHERE st.sequence_id = %d AND d.status = 'sent' AND d.sent_at >= %s",
			$sequence_id, $since
		) );

		$unsubs = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $t_sub WHERE sequence_id = %d AND unsubscribed_at >= %s", $sequence_id, $since
		) );

		$sent = $row ? (int) $row->sent : 0;
		return array(
			'new_subs'   => $new_subs,
			'prev_subs'  => $prev_subs,
			'open_rate'  => $sent ? round( 100 * (int) $row->opened / $sent, 1 ) : 0,
			'click_rate' => $sent ? round( 100 * (int) $row->clicked / $sent, 1 ) : 0,
			'unsub_rate' => $sent ? round( 100 * $unsubs / $sent, 2 ) : 0,
			'sent'       => $sent,
		);
	}

	/** ステップ通過レポート+ボトルネック検出(前の通より開封率10pt以上低下で警告) */
	public static function step_report( $sequence_id ) {
		global $wpdb;
		$t_del = MS_DB::deliveries();
		$t_stp = MS_DB::steps();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT st.id AS step_id, st.step_no, st.subject, st.status AS step_status,
			        st.send_offset, st.send_hour,
			        SUM(d.status = 'sent') AS sent,
			        SUM(d.status = 'sent' AND d.opened_at IS NOT NULL) AS opened,
			        SUM(d.status = 'sent' AND d.clicked_at IS NOT NULL) AS clicked
			 FROM $t_stp st
			 LEFT JOIN $t_del d ON d.step_id = st.id
			 WHERE st.sequence_id = %d
			 GROUP BY st.id
			 ORDER BY st.step_no ASC",
			$sequence_id
		) );

		$report    = array();
		$prev_open = null;
		foreach ( $rows as $r ) {
			$sent  = (int) $r->sent;
			$open  = $sent ? round( 100 * (int) $r->opened / $sent, 1 ) : null;
			$click = $sent ? round( 100 * (int) $r->clicked / $sent, 1 ) : null;

			$warn = false;
			if ( null !== $open && null !== $prev_open && $sent >= 20 && ( $prev_open - $open ) >= 10 ) {
				$warn = true; // 母数20未満はノイズなので警告しない
			}

			$report[] = array(
				'step_id'     => (int) $r->step_id,
				'step_no'     => (int) $r->step_no,
				'subject'     => $r->subject,
				'step_status' => $r->step_status,
				'send_offset' => (int) $r->send_offset,
				'send_hour'   => $r->send_hour,
				'sent'        => $sent,
				'open_rate'   => $open,
				'click_rate'  => $click,
				'warn'        => $warn,
			);
			if ( null !== $open ) {
				$prev_open = $open;
			}
		}
		return $report;
	}

	/** 個人タイムライン(仕様書 画面3) */
	public static function subscriber_timeline( $subscriber_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT d.id, d.subscriber_id, d.step_id, d.status, d.scheduled_at, d.sent_at, d.opened_at, d.clicked_at, d.clicked_url,
			        st.step_no, st.subject, \'sequence\' AS mail_type
			 FROM ' . MS_DB::deliveries() . ' d
			 INNER JOIN ' . MS_DB::steps() . ' st ON st.id = d.step_id
			 WHERE d.subscriber_id = %d
			 UNION ALL
			 SELECT r.id, r.subscriber_id, 0 AS step_id, r.status, b.created_at AS scheduled_at, r.sent_at, r.opened_at, r.clicked_at, r.clicked_url,
			        0 AS step_no, b.subject, \'broadcast\' AS mail_type
			 FROM ' . MS_DB::broadcast_recipients() . ' r
			 INNER JOIN ' . MS_DB::broadcasts() . ' b ON b.id = r.broadcast_id
			 WHERE r.subscriber_id = %d
			 ORDER BY scheduled_at ASC',
			$subscriber_id, $subscriber_id
		) );
	}

	/* ---------- admin-post ハンドラ ---------- */

	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $nonce_action ) ) {
			wp_die( '権限がありません。' );
		}
	}

	public function save_settings() {
		$this->guard( 'ms_save_settings' );
		update_option( 'ms_from_name', sanitize_text_field( $_POST['ms_from_name'] ) );
		update_option( 'ms_from_email', sanitize_email( $_POST['ms_from_email'] ) );
		update_option( 'ms_batch_size', max( 1, min( 500, (int) $_POST['ms_batch_size'] ) ) );
		update_option( 'ms_track_open', empty( $_POST['ms_track_open'] ) ? 0 : 1 );
		update_option( 'ms_track_click', empty( $_POST['ms_track_click'] ) ? 0 : 1 );
		$retention = isset( $_POST['ms_activity_retention_days'] ) ? (int) $_POST['ms_activity_retention_days'] : 365;
		update_option( 'ms_activity_retention_days', in_array( $retention, array( 0, 90, 180, 365 ), true ) ? $retention : 365 );
		wp_safe_redirect( admin_url( 'admin.php?page=ms-settings&saved=1' ) );
		exit;
	}

	public function save_step() {
		$this->guard( 'ms_save_step' );
		$result = MS_Sequence::save_step( array(
			'sequence_id' => (int) $_POST['sequence_id'],
			'step_id'     => (int) $_POST['step_id'],
			'send_offset' => isset( $_POST['send_offset_days'] )
				? ( (int) $_POST['send_offset_days'] * 24 + (int) $_POST['send_offset_hours'] )
				: (int) $_POST['send_offset'],
			'send_hour'   => ( '' === $_POST['send_hour'] ) ? '' : (int) $_POST['send_hour'],
			'subject'     => wp_unslash( $_POST['subject'] ),
			'body_html'   => wp_unslash( $_POST['body_html'] ),
			'status'      => sanitize_key( $_POST['step_status'] ),
		) );

		$url = admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $_POST['sequence_id'] . '&saved=1' );
		if ( ! empty( $result['warnings'] ) ) {
			$url .= '&warn=unsub';
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function delete_step() {
		$this->guard( 'ms_delete_step' );
		MS_Sequence::delete_step( (int) $_GET['step_id'] );
		wp_safe_redirect( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $_GET['sequence_id'] . '&deleted=1' ) );
		exit;
	}

	public function test_send() {
		$this->guard( 'ms_test_send' );
		$user = wp_get_current_user();
		$ok   = MS_Sender::test_send( (int) $_GET['step_id'], $user->user_email );
		wp_safe_redirect( admin_url(
			'admin.php?page=ms-sequences&sequence_id=' . (int) $_GET['sequence_id'] .
			'&step_id=' . (int) $_GET['step_id'] .
			( $ok ? '&test=ok' : '&test=fail' )
		) );
		exit;
	}

	public function add_sequence() {
		$this->guard( 'ms_add_sequence' );
		$id = MS_Sequence::create( wp_unslash( $_POST['sequence_name'] ) );
		wp_safe_redirect( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . $id ) );
		exit;
	}

	public function toggle_sequence() {
		$this->guard( 'ms_toggle_sequence' );
		MS_Sequence::toggle_status( (int) $_GET['sequence_id'] );
		wp_safe_redirect( admin_url( 'admin.php?page=ms-sequences&sequence_id=' . (int) $_GET['sequence_id'] ) );
		exit;
	}

	public function delete_sequence() {
		$this->guard( 'ms_delete_sequence' );
		$sequence_id = (int) $_GET['sequence_id'];
		$result = MS_Sequence::delete_empty( $sequence_id );
		$status = is_wp_error( $result ) ? $result->get_error_code() : 'ok';
		wp_safe_redirect( admin_url( 'admin.php?page=ms-sequences&seqdelete=' . $status ) );
		exit;
	}

	public function unsub_subscriber() {
		$this->guard( 'ms_unsub_subscriber' );
		MS_Subscriber::unsubscribe_by_id( (int) $_GET['subscriber_id'] );
		wp_safe_redirect( admin_url( 'admin.php?page=ms-subscribers&view=' . (int) $_GET['subscriber_id'] ) );
		exit;
	}

	/**
	 * メルマガ画面のフォーム。押したボタンで動作が変わる。
	 * - preview:実際に届く見た目を新しいタブで表示
	 * - test   :入力したアドレスにだけ送る(計測なし・解除リンクはテスト用)
	 * - send   :購読中の全員へ送信キューを作る
	 * プレビュー・テストのたびに内容を下書きとして保存し、画面に戻っても入力が消えないようにする。
	 */
	public function send_broadcast() {
		$this->guard( 'ms_send_broadcast' );

		$mode        = isset( $_POST['ms_mode'] ) ? sanitize_key( $_POST['ms_mode'] ) : 'send';
		$sequence_id = (int) $_POST['sequence_id'];
		$subject     = sanitize_text_field( wp_unslash( $_POST['subject'] ) );
		$body_html   = wp_kses_post( wp_unslash( $_POST['body_html'] ) );
		$user_id     = get_current_user_id();

		if ( 'preview' === $mode || 'test' === $mode ) {
			update_user_meta( $user_id, 'ms_broadcast_draft', array(
				'sequence_id' => $sequence_id,
				'subject'     => $subject,
				'body_html'   => $body_html,
			) );
		}

		if ( 'preview' === $mode ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=UTF-8' );
			echo MS_Sender::render_preview( $subject, $body_html ); // 管理者入力を wp_kses_post 済み
			exit;
		}

		if ( 'test' === $mode ) {
			$to = sanitize_email( wp_unslash( $_POST['ms_test_email'] ) );
			if ( ! is_email( $to ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=ms-broadcasts&test=bademail' ) );
				exit;
			}
			update_user_meta( $user_id, 'ms_test_email', $to );
			$ok = MS_Sender::send_test( $to, $subject, $body_html );
			wp_safe_redirect( admin_url( 'admin.php?page=ms-broadcasts&test=' . ( $ok ? 'ok' : 'fail' ) ) );
			exit;
		}

		$result = MS_Broadcast::create( $sequence_id, $subject, $body_html );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ms-broadcasts&error=' . $result->get_error_code() ) );
			exit;
		}

		delete_user_meta( $user_id, 'ms_broadcast_draft' ); // 送信したら下書きは不要
		wp_safe_redirect( admin_url( 'admin.php?page=ms-broadcasts&queued=' . (int) $result['recipients'] ) );
		exit;
	}

	/* ---------- 表示ユーティリティ ---------- */

	public static function timing_label( $offset_hours, $send_hour ) {
		if ( 0 === (int) $offset_hours && ( null === $send_hour || '' === $send_hour ) ) {
			return '登録直後';
		}
		$days  = floor( $offset_hours / 24 );
		$hours = $offset_hours % 24;
		$label = '';
		if ( $days ) {
			$label .= $days . '日';
		}
		if ( $hours ) {
			$label .= $hours . '時間';
		}
		$label .= '後';
		if ( null !== $send_hour && '' !== $send_hour ) {
			$label .= ' ' . (int) $send_hour . ':00';
		}
		return $label;
	}
}
