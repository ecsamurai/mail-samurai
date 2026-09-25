<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * テーブル名の解決と共通クエリを集約するヘルパー。
 * 仕様書4章の4テーブル構成。status系はdbDelta互換性のためENUMではなくVARCHARで実装。
 */
class MS_DB {

	public static function subscribers() {
		global $wpdb;
		return $wpdb->prefix . 'ms_subscribers';
	}

	public static function sequences() {
		global $wpdb;
		return $wpdb->prefix . 'ms_sequences';
	}

	public static function steps() {
		global $wpdb;
		return $wpdb->prefix . 'ms_steps';
	}

	public static function deliveries() {
		global $wpdb;
		return $wpdb->prefix . 'ms_deliveries';
	}

	public static function broadcasts() {
		global $wpdb;
		return $wpdb->prefix . 'ms_broadcasts';
	}

	public static function broadcast_recipients() {
		global $wpdb;
		return $wpdb->prefix . 'ms_broadcast_recipients';
	}

	/** ランダムトークン(32文字hex) */
	public static function token() {
		return bin2hex( random_bytes( 16 ) );
	}

	/** シーケンス一覧 */
	public static function get_sequences() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::sequences() . ' ORDER BY id ASC' );
	}

	public static function get_sequence( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::sequences() . ' WHERE id = %d', $id ) );
	}

	/** シーケンスの全ステップ(通番順) */
	public static function get_steps( $sequence_id, $only_active = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::steps() . ' WHERE sequence_id = %d';
		if ( $only_active ) {
			$sql .= " AND status = 'active'";
		}
		$sql .= ' ORDER BY step_no ASC';
		return $wpdb->get_results( $wpdb->prepare( $sql, $sequence_id ) );
	}

	public static function get_step( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::steps() . ' WHERE id = %d', $id ) );
	}

	public static function get_subscriber( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::subscribers() . ' WHERE id = %d', $id ) );
	}

	public static function get_subscriber_by_token( $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::subscribers() . ' WHERE token = %s', $token ) );
	}

	public static function get_delivery_by_token( $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::deliveries() . ' WHERE track_token = %s', $token ) );
	}

	public static function get_broadcast_recipient_by_token( $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::broadcast_recipients() . ' WHERE track_token = %s', $token
		) );
	}
}
