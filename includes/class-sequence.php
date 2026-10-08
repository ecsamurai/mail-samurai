<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Sequence {

	public static function create( $name ) {
		global $wpdb;
		$wpdb->insert( MS_DB::sequences(), array(
			'name'       => sanitize_text_field( $name ),
			'status'     => 'active',
			'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function toggle_status( $sequence_id ) {
		global $wpdb;
		$seq = MS_DB::get_sequence( $sequence_id );
		if ( ! $seq ) {
			return false;
		}
		$new = ( 'active' === $seq->status ) ? 'paused' : 'active';
		$wpdb->update( MS_DB::sequences(), array( 'status' => $new ), array( 'id' => $sequence_id ) );
		return $new;
	}

	/**
	 * ステップ保存(新規/更新)。
	 * 戻り値: array( 'id' => int, 'warnings' => string[] )
	 */
	public static function save_step( $data ) {
		global $wpdb;

		$sequence_id = (int) $data['sequence_id'];
		$step_id     = isset( $data['step_id'] ) ? (int) $data['step_id'] : 0;

		$send_hour = ( '' === $data['send_hour'] || null === $data['send_hour'] )
			? null
			: max( 0, min( 23, (int) $data['send_hour'] ) );

		$row = array(
			'sequence_id' => $sequence_id,
			'send_offset' => max( 0, (int) $data['send_offset'] ),
			'send_hour'   => $send_hour,
			'subject'     => sanitize_text_field( $data['subject'] ),
			'body_html'   => wp_kses_post( $data['body_html'] ),
			'status'      => ( 'active' === $data['status'] ) ? 'active' : 'draft',
		);

		if ( $step_id ) {
			$wpdb->update( MS_DB::steps(), $row, array( 'id' => $step_id ) );
			// タイミング変更を未送信の配信予約に反映
			self::reschedule_pending( $step_id );
		} else {
			$max = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT MAX(step_no) FROM ' . MS_DB::steps() . ' WHERE sequence_id = %d', $sequence_id
			) );
			$row['step_no'] = $max + 1;
			$wpdb->insert( MS_DB::steps(), $row );
			$step_id = (int) $wpdb->insert_id;

			// 既存購読者にも新ステップの配信予約を追加(登録日起点で再計算)
			self::backfill_deliveries( $step_id );
		}

		// 特電法チェック:解除リンクタグ(テンプレートのフッターで自動付与はされるが、明示挿入を推奨)
		$warnings = array();
		if ( false === strpos( $data['body_html'], '{unsubscribe_url}' ) ) {
			$warnings[] = '本文に {unsubscribe_url} がありません。メール末尾のフッターに解除リンクが自動挿入されますが、本文内への明示挿入を推奨します(特定電子メール法)。';
		}

		return array( 'id' => $step_id, 'warnings' => $warnings );
	}

	/**
	 * 新ステップ追加時、既存の購読中メンバーへ配信予約を後追い生成。
	 * scheduled_at が過去になる人(すでにそのDayを過ぎた人)には送らない=skippedで記録。
	 */
	public static function backfill_deliveries( $step_id ) {
		global $wpdb;
		$step = MS_DB::get_step( $step_id );
		if ( ! $step ) {
			return;
		}

		$subs = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, created_at FROM ' . MS_DB::subscribers() . " WHERE sequence_id = %d AND status = 'subscribed'",
			$step->sequence_id
		) );

		$now = current_time( 'mysql' );
		foreach ( $subs as $sub ) {
			$scheduled = MS_Subscriber::compute_schedule( $sub->created_at, (int) $step->send_offset, $step->send_hour );
			$status    = ( $scheduled < $now ) ? 'skipped' : 'pending';
			$wpdb->query( $wpdb->prepare(
				'INSERT IGNORE INTO ' . MS_DB::deliveries() .
				' (subscriber_id, step_id, track_token, status, scheduled_at) VALUES (%d, %d, %s, %s, %s)',
				$sub->id, $step->id, MS_DB::token(), $status, $scheduled
			) );
		}
	}

	/**
	 * ステップの配信タイミング変更を、未送信(pending)の予約に反映する。
	 * 送信済み(sent)には触らない。再計算の結果すでに過去になった分は
	 * 次のcronで送信される(=「変更したら即ドカッと送信」を許容。
	 * それが嫌な場合はステップを下書きに戻してから変更する運用)。
	 */
	public static function reschedule_pending( $step_id ) {
		global $wpdb;
		$step = MS_DB::get_step( $step_id );
		if ( ! $step ) {
			return;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.id, s.created_at FROM ' . MS_DB::deliveries() . ' d
			 INNER JOIN ' . MS_DB::subscribers() . " s ON s.id = d.subscriber_id
			 WHERE d.step_id = %d AND d.status = 'pending'",
			$step_id
		) );

		foreach ( $rows as $r ) {
			$scheduled = MS_Subscriber::compute_schedule( $r->created_at, (int) $step->send_offset, $step->send_hour );
			$wpdb->update( MS_DB::deliveries(), array( 'scheduled_at' => $scheduled ), array( 'id' => $r->id ) );
		}
	}

	public static function delete_step( $step_id ) {
		global $wpdb;
		// 送信済みログは残し、未送信予約のみ削除
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . MS_DB::deliveries() . " WHERE step_id = %d AND status IN ('pending','skipped')",
			$step_id
		) );
		$wpdb->delete( MS_DB::steps(), array( 'id' => $step_id ) );
	}

	/**
	 * 空のシーケンスだけを削除する。
	 * 購読者・ステップ・配信履歴を持つシーケンスは、誤って運用データを失わないよう削除しない。
	 */
	public static function delete_empty( $sequence_id ) {
		global $wpdb;
		$sequence = MS_DB::get_sequence( $sequence_id );
		if ( ! $sequence ) {
			return new WP_Error( 'not_found', 'シーケンスが見つかりません。' );
		}

		if ( ! self::is_empty( $sequence_id ) ) {
			return new WP_Error( 'not_empty', 'ステップ・購読者・メルマガ履歴のあるシーケンスは削除できません。' );
		}

		$wpdb->delete( MS_DB::sequences(), array( 'id' => $sequence_id ) );
		return true;
	}

	/**
	 * ステップ・購読者・メルマガが1件もないか。
	 * 配信履歴は必ずステップに紐づくので、ステップが0件なら配信履歴も0件。
	 */
	public static function is_empty( $sequence_id ) {
		global $wpdb;
		$steps = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . MS_DB::steps() . ' WHERE sequence_id = %d', $sequence_id
		) );
		$subscribers = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . MS_DB::subscribers() . ' WHERE sequence_id = %d', $sequence_id
		) );
		$broadcasts = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . MS_DB::broadcasts() . ' WHERE sequence_id = %d', $sequence_id
		) );
		return ! $steps && ! $subscribers && ! $broadcasts;
	}
}
