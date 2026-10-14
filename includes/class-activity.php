<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** 個別の行動を追記保存する。初回反応の集計用カラムとは独立して扱う。 */
class MS_Activity {

	const PAGE_SIZE = 50;

	public static function labels() {
		return array(
			'subscribed'   => '登録',
			'resubscribed' => '再登録',
			'sent'         => '送信処理成功',
			'failed'       => '送信失敗（再試行終了）',
			'opened'       => '開封を検知',
			'clicked'      => 'リンクをクリック',
			'unsubscribed' => '本人による登録解除',
			'admin_stopped' => '管理者による配信停止',
		);
	}

	public static function record( $subscriber_id, $event_type, $context = array() ) {
		global $wpdb;
		if ( $subscriber_id < 1 || ! isset( self::labels()[ $event_type ] ) ) {
			return false;
		}
		$context = wp_parse_args( $context, array(
			'mail_type' => '', 'mail_id' => 0, 'subject' => '', 'url' => '', 'actor_id' => 0,
		) );
		return $wpdb->insert( MS_DB::activities(), array(
			'subscriber_id' => (int) $subscriber_id,
			'event_type'    => $event_type,
			'mail_type'     => in_array( $context['mail_type'], array( 'sequence', 'broadcast' ), true ) ? $context['mail_type'] : '',
			'mail_id'       => max( 0, (int) $context['mail_id'] ),
			'subject'       => mb_substr( sanitize_text_field( $context['subject'] ), 0, 255 ),
			'url'           => mb_substr( esc_url_raw( $context['url'], array( 'http', 'https' ) ), 0, 2000 ),
			'actor_id'      => max( 0, (int) $context['actor_id'] ),
			'created_at'    => current_time( 'mysql' ),
		) );
	}

	/** 配信IDから件名と購読者を解決。購読者が消えたメールは履歴を残さない。 */
	public static function record_mail( $mail_type, $mail_id, $event_type, $url = '' ) {
		global $wpdb;
		if ( 'sequence' === $mail_type ) {
			$sql = 'SELECT q.subscriber_id, m.subject FROM ' . MS_DB::deliveries() . ' q
				LEFT JOIN ' . MS_DB::steps() . ' m ON m.id = q.step_id';
		} elseif ( 'broadcast' === $mail_type ) {
			$sql = 'SELECT q.subscriber_id, m.subject FROM ' . MS_DB::broadcast_recipients() . ' q
				LEFT JOIN ' . MS_DB::broadcasts() . ' m ON m.id = q.broadcast_id';
		} else {
			return false;
		}
		$sql .= ' INNER JOIN ' . MS_DB::subscribers() . ' s ON s.id = q.subscriber_id WHERE q.id = %d';
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $mail_id ) );
		if ( ! $row ) {
			return false;
		}
		return self::record( $row->subscriber_id, $event_type, array(
			'mail_type' => $mail_type, 'mail_id' => $mail_id, 'subject' => (string) $row->subject, 'url' => $url,
		) );
	}

	public static function page( $subscriber_id, $event_type = '', $page = 1 ) {
		global $wpdb;
		$where = $wpdb->prepare( ' WHERE subscriber_id = %d', $subscriber_id );
		if ( isset( self::labels()[ $event_type ] ) ) {
			$where .= $wpdb->prepare( ' AND event_type = %s', $event_type );
		}
		$table = MS_DB::activities();
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" );
		$pages = max( 1, (int) ceil( $total / self::PAGE_SIZE ) );
		$page  = max( 1, min( (int) $page, $pages ) );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
			self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE
		) );
		return array( 'rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page );
	}

	/** 少量ずつ削除し、配信キューを長時間止めない。0日は無期限。 */
	public static function cleanup() {
		global $wpdb;
		$days = (int) get_option( 'ms_activity_retention_days', 365 );
		if ( $days < 1 ) {
			return;
		}
		$cutoff = new DateTime( 'now', wp_timezone() );
		$cutoff->modify( '-' . $days . ' days' );
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . MS_DB::activities() . ' WHERE created_at < %s LIMIT 1000',
			$cutoff->format( 'Y-m-d H:i:s' )
		) );
	}
}
