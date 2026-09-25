<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MS_Scheduler {

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_interval' ) );
		add_action( 'ms_process_queue', array( 'MS_Sender', 'process_queue' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	public function add_interval( $schedules ) {
		$schedules['ms_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'メール侍: 5分間隔',
		);
		return $schedules;
	}

	/** 何らかの理由でcronが消えていたら再登録する保険 */
	public function ensure_scheduled() {
		if ( ! wp_next_scheduled( 'ms_process_queue' ) ) {
			wp_schedule_event( time(), 'ms_five_minutes', 'ms_process_queue' );
		}
	}
}
