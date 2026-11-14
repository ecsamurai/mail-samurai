<?php
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/includes/class-db.php';
require dirname(__DIR__).'/includes/class-broadcast.php';
require dirname(__DIR__).'/includes/class-sender.php';
// Real sender interleaved at every insert: no early completion or partial send.
schema();$GLOBALS['sent']=array();
$wpdb->query("INSERT INTO wp_ms_subscribers VALUES(1,'one@example.test','One','subscribed',1,'a'),(2,'two@example.test','Two','subscribed',1,'b')");
$wpdb->during_insert=function($table,$row){MS_Sender::process_broadcast_queue(50);};
$created=MS_Broadcast::create(1,'Hello','Message');
check(!is_wp_error($created),'Broadcast creates with concurrent cron');
check(count($GLOBALS['sent'])===0,'No recipient sent while batch is building');
check($wpdb->get_var('SELECT status FROM wp_ms_broadcasts')==='queued','Batch becomes queued after all recipients exist');
$wpdb->during_insert=null;MS_Sender::process_broadcast_queue(50);
check(count($GLOBALS['sent'])===2,'Both recipients sent after publication');
check($wpdb->get_var('SELECT status FROM wp_ms_broadcasts')==='completed','Batch completes after sending');
MS_Sender::process_broadcast_queue(50);check(count($GLOBALS['sent'])===2,'Subsequent cron does not resend');
$wpdb->fail_table='wp_ms_broadcast_recipients';$failed=MS_Broadcast::create(1,'Fail','Body');
check(is_wp_error($failed),'Recipient insert error reported');
check($wpdb->get_var('SELECT status FROM wp_ms_broadcasts ORDER BY id DESC LIMIT 1')==='failed','Partial batch remains unsendable');
$wpdb->fail_table='';$wpdb->query("UPDATE wp_ms_subscribers SET status='unsubscribed'");$empty=MS_Broadcast::create(1,'Empty','Body');
check(!is_wp_error($empty)&&$empty['recipients']===0,'Empty batch completes safely');

// A failure after one recipient exists must leave the entire batch unsendable.
schema();$GLOBALS['sent']=array();
$wpdb->query("INSERT INTO wp_ms_subscribers VALUES(1,'one@example.test','One','subscribed',1,'a'),(2,'two@example.test','Two','subscribed',1,'b')");
$wpdb->during_insert=function($table,$row)use($wpdb){if($table===MS_DB::broadcast_recipients())$wpdb->fail_table=$table;};
$partial=MS_Broadcast::create(1,'Partial','Message');
check(is_wp_error($partial),'Partial recipient creation reports failure');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_ms_broadcast_recipients')===1,'Failure was injected after first recipient');
$wpdb->during_insert=null;$wpdb->fail_table='';MS_Sender::process_broadcast_queue(50);
check(count($GLOBALS['sent'])===0,'Partially built failed batch never sends');
echo "OK: broadcast regression completed on PHP ".PHP_VERSION."\n";
