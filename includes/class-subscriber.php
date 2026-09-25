<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Subscriber {

	/**
	 * 購読登録。成功時は subscriber ID、失敗時は WP_Error。
	 * 登録と同時にシーケンス全ステップ分の deliveries を pending で一括生成する(仕様書2章)。
	 */
	public static function subscribe( $email, $name, $sequence_id, $source = '' ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'メールアドレスの形式が正しくありません。' );
		}

		$sequence = MS_DB::get_sequence( $sequence_id );
		if ( ! $sequence ) {
			return new WP_Error( 'invalid_sequence', '配信リストが見つかりません。' );
		}

		// 既存チェック(email × sequence の複合ユニーク)
		$existing = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . MS_DB::subscribers() . ' WHERE email = %s AND sequence_id = %d',
			$email, $sequence_id
		) );

		if ( $existing ) {
			if ( 'subscribed' === $existing->status ) {
				// 二重登録:タイマーはリセットしない(締切と同じ思想)
				return new WP_Error( 'already', 'すでにご登録いただいています。' );
			}
			// 解除済みの再登録は復活させる(タイマーはリセットしない=締切と同じ思想)。
			// 解除時にskippedへ落とした予約のうち、配信時刻がまだ先のものだけpendingへ戻す。
			// すでに過ぎた分は送らない(skippedのまま)。
			$wpdb->update( MS_DB::subscribers(), array(
				'status'          => 'subscribed',
				'unsubscribed_at' => null,
			), array( 'id' => $existing->id ) );
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . MS_DB::deliveries() .
				" SET status = 'pending' WHERE subscriber_id = %d AND status = 'skipped' AND scheduled_at > %s",
				$existing->id, current_time( 'mysql' )
			) );
			return (int) $existing->id;
		}

		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert( MS_DB::subscribers(), array(
			'email'       => $email,
			'name'        => sanitize_text_field( $name ),
			'status'      => 'subscribed',
			'sequence_id' => $sequence_id,
			'token'       => MS_DB::token(),
			'source'      => sanitize_text_field( $source ),
			'created_at'  => $now,
		) );

		if ( ! $ok ) {
			return new WP_Error( 'db_error', '登録処理に失敗しました。' );
		}
		$subscriber_id = (int) $wpdb->insert_id;

		self::generate_deliveries( $subscriber_id, $sequence_id, $now );

		return $subscriber_id;
	}

	/**
	 * 全ステップ分の配信予約を一括INSERT。
	 * UNIQUE KEY (subscriber_id, step_id) により多重生成はDBレベルで弾かれる。
	 */
	public static function generate_deliveries( $subscriber_id, $sequence_id, $created_at ) {
		global $wpdb;
		$steps = MS_DB::get_steps( $sequence_id ); // draft含む全ステップを予約(送信時にdraftはスキップ)

		foreach ( $steps as $step ) {
			$scheduled = self::compute_schedule( $created_at, (int) $step->send_offset, $step->send_hour );
			$wpdb->query( $wpdb->prepare(
				'INSERT IGNORE INTO ' . MS_DB::deliveries() .
				' (subscriber_id, step_id, track_token, status, scheduled_at) VALUES (%d, %d, %s, %s, %s)',
				$subscriber_id, $step->id, MS_DB::token(), 'pending', $scheduled
			) );
		}
	}

	/**
	 * 発火時刻の計算(仕様書4-3)。
	 * created_at + offset時間 を求め、send_hour 指定があればその日のH時に丸める。
	 * 丸めた結果が登録時刻より前になる場合は翌日に送る(深夜配信・過去時刻の防止)。
	 */
	public static function compute_schedule( $created_mysql, $offset_hours, $send_hour ) {
		$tz      = wp_timezone();
		$created = new DateTime( $created_mysql, $tz );
		$dt      = clone $created;

		if ( $offset_hours > 0 ) {
			$dt->modify( '+' . $offset_hours . ' hours' );
		}

		if ( null !== $send_hour && '' !== $send_hour ) {
			$dt->setTime( (int) $send_hour, 0, 0 );
			if ( $dt <= $created ) {
				$dt->modify( '+1 day' );
			}
		}

		return $dt->format( 'Y-m-d H:i:s' );
	}

	/** 解除(購読者トークン経由) */
	public static function unsubscribe_by_token( $token ) {
		global $wpdb;
		$sub = MS_DB::get_subscriber_by_token( $token );
		if ( ! $sub ) {
			return false;
		}
		if ( 'unsubscribed' !== $sub->status ) {
			$wpdb->update( MS_DB::subscribers(), array(
				'status'          => 'unsubscribed',
				'unsubscribed_at' => current_time( 'mysql' ),
			), array( 'id' => $sub->id ) );

			// 未送信分をskippedへ(記録は消さない:仕様書4-4)
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . MS_DB::deliveries() . " SET status = 'skipped' WHERE subscriber_id = %d AND status = 'pending'",
				$sub->id
			) );
		}
		return $sub;
	}

	/** 管理画面からの手動配信停止 */
	public static function unsubscribe_by_id( $id ) {
		$sub = MS_DB::get_subscriber( $id );
		if ( ! $sub ) {
			return false;
		}
		return self::unsubscribe_by_token( $sub->token );
	}
}
