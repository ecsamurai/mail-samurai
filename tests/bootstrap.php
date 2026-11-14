<?php
/** Offline regression harness. Uses actual plugin PHP + in-memory SQLite; no mail/network. */
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){ if(error_reporting() & $severity) throw new ErrorException($message,0,$severity,$file,$line); });
define('ABSPATH', __DIR__.'/wp/');
define('MINUTE_IN_SECONDS',60); define('HOUR_IN_SECONDS',3600); define('DAY_IN_SECONDS',86400);
$GLOBALS['opts']=array();$GLOBALS['meta']=array();$GLOBALS['user_meta']=array();$GLOBALS['hooks']=array();
$GLOBALS['user']=(object)array('ID'=>0,'user_email'=>'reader@example.test','roles'=>array());
$GLOBALS['caps']=array();$GLOBALS['sent']=array();$GLOBALS['results']=array();
class WP_Error { public $code;public $message;public $data;public $errors=array();public function __construct($c='',$m='',$d=null){$this->code=$c;$this->message=$m;$this->data=$d;}public function add($c,$m){$this->errors[$c]=$m;}public function get_error_code(){return $this->code;}public function get_error_message(){return $this->message;} }
class WP_REST_Response {public $data;public $status=200;public $headers=array();public function __construct($d=array()){$this->data=$d;}public function header($k,$v){$this->headers[$k]=$v;}public function set_data($d){$this->data=$d;}public function set_status($s){$this->status=$s;} }
class TestRedirect extends Exception {public $url;public function __construct($url){$this->url=$url;parent::__construct($url);}}
class NetworkReached extends Exception {}
class TestJSON extends Exception {public $data;public $success;public function __construct($d,$s){$this->data=$d;$this->success=$s;}}
function check($condition,$label){if(!$condition)throw new Exception('FAIL: '.$label);$GLOBALS['results'][]=$label;}
function invoke($object,$method,...$args){$r=new ReflectionMethod($object,$method);$r->setAccessible(true);return $r->invokeArgs(is_object($object)?$object:null,$args);}
function add_action($n,$f,$p=10,$a=1){$GLOBALS['hooks'][$n][$p][]=array($f,$a);}
function add_filter($n,$f,$p=10,$a=1){add_action($n,$f,$p,$a);}
function remove_action($n,$f){ }
function do_action($n,...$args){$h=$GLOBALS['hooks'][$n]??array();ksort($h);foreach($h as $list)foreach($list as $i)call_user_func_array($i[0],array_slice($args,0,$i[1]));}
function apply_filters($n,$v,...$args){$h=$GLOBALS['hooks'][$n]??array();ksort($h);foreach($h as $list)foreach($list as $i)$v=call_user_func_array($i[0],array_slice(array_merge(array($v),$args),0,$i[1]));return $v;}
function add_shortcode($n,$f){}
function register_activation_hook($f,$c){}
function get_option($k,$default=false){return $GLOBALS['opts'][$k]??$default;}
function update_option($k,$v,$autoload=null){$old=get_option($k,null);$GLOBALS['opts'][$k]=$v;return $old!==$v;}
function set_transient($k,$v,$t){$GLOBALS['transients'][$k]=$v;return true;}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function delete_transient($k){if(!isset($GLOBALS['transients'][$k]))return false;unset($GLOBALS['transients'][$k]);return true;}
function get_post_meta($id,$k,$single=true){return $GLOBALS['meta'][$id][$k]??'';}
function get_user_meta($id,$k,$single=true){return $GLOBALS['user_meta'][$id][$k]??'';}
function update_user_meta($id,$k,$v){$GLOBALS['user_meta'][$id][$k]=$v;return true;}
function wp_parse_args($v,$defaults){return array_merge($defaults,$v);}
function wp_unslash($v){return $v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function sanitize_text_field($v){return trim(strip_tags($v));}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function esc_url_raw($v){return $v;}
function wp_kses_post($v){return $v;}
function absint($v){return abs((int)$v);}
function current_time($type){return $type==='mysql'?'2026-11-05 12:00:00':time();}
function wp_timezone(){return new DateTimeZone('UTC');}
function wp_generate_password($n,$special=false,$extra=false){return substr(bin2hex(random_bytes($n)),0,$n);}
function wp_generate_uuid4(){return bin2hex(random_bytes(16));}
function is_wp_error($v){return $v instanceof WP_Error;}
function home_url($v='/'){return 'https://site.example.test'.$v;}
function wp_login_url($v=''){return 'https://site.example.test/wp-login.php';}
function is_ssl(){return true;}
function add_query_arg($a,$b=null,$c=null){if(is_array($a)){return $b.(strpos($b,'?')===false?'?':'&').http_build_query($a);}return $c.(strpos($c,'?')===false?'?':'&').http_build_query(array($a=>$b));}
function wp_safe_redirect($v){throw new TestRedirect($v);}
function wp_redirect($v){throw new TestRedirect($v);}
function wp_remote_post($url,$args){throw new NetworkReached('Token endpoint reached');}
function nocache_headers(){$GLOBALS['nocache']=true;}
function is_admin(){return false;}
function is_user_logged_in(){return get_current_user_id()>0;}
function current_user_can($cap,...$args){return !empty($GLOBALS['caps'][$cap]);}
function get_current_user_id(){return $GLOBALS['user']->ID;}
function wp_get_current_user(){return $GLOBALS['user'];}
function get_the_ID(){return $GLOBALS['post_id']??0;}
function wc_customer_bought_product($email,$uid,$pid){return in_array($pid,$GLOBALS['purchases'][$uid]??array(),true);}
function wc_add_notice($s,$t){$GLOBALS['notices'][]=$s;}
function WC(){return $GLOBALS['wc'];}
function wp_mail($to,$subject,$body,$headers){$GLOBALS['sent'][]=$to;return true;}
function get_bloginfo($k){return 'Test';}
function check_ajax_referer($n,$k){}
function wp_send_json_error($data,$status=400){throw new TestJSON($data,false);}
function wp_send_json_success($data){throw new TestJSON($data,true);}
class MS_Tracking {public static function prepare_body($body,$ctx){return $body;}}
class MS_Activity {public static function record_mail(...$args){} }
class TestDB {
 public $prefix='wp_';public $insert_id=0;public $last_error='';public $pdo;public $during_insert=null;public $fail_table='';public $lock=true;
 public function __construct(){ $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->pdo->sqliteCreateFunction('LEAST',function(...$a){return min($a);}); }
 public function prepare($q,...$args){if(count($args)===1&&is_array($args[0]))$args=$args[0];$i=0;return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:$this->pdo->quote((string)$v);},$q);}
 public function query($q){
  $q=preg_replace('/UPDATE (\w+) q INNER JOIN (\w+) s ON s.id = q.subscriber_id\s+SET q.status = \'skipped\'\s+WHERE q.status = \'pending\' AND s.status != \'subscribed\'/s',"UPDATE $1 SET status = 'skipped' WHERE status = 'pending' AND subscriber_id IN (SELECT id FROM $2 WHERE status != 'subscribed')",$q);
  $q=preg_replace('/UPDATE (\w+) b SET b.status/', 'UPDATE $1 AS b SET status',$q);$q=str_replace('b.completed_at =','completed_at =',$q);
  try{$this->last_error='';return $this->pdo->exec($q);}catch(Exception $e){$this->last_error=$e->getMessage();throw $e;}
 }
 public function insert($table,$row,$format=null){if($table===$this->fail_table){$this->last_error='Injected insert failure';return false;} $cols=array_keys($row);$st=$this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');$st->execute(array_values($row));$this->insert_id=(int)$this->pdo->lastInsertId();$id=$this->insert_id;if($this->during_insert)call_user_func($this->during_insert,$table,$row);$this->insert_id=$id;return 1;}
 public function update($table,$row,$where,$format=null,$where_format=null){$set=array();$cond=array();$vals=array();foreach($row as $k=>$v){$set[]=$k.'=?';$vals[]=$v;}foreach($where as $k=>$v){$cond[]=$k.'=?';$vals[]=$v;}$st=$this->pdo->prepare('UPDATE '.$table.' SET '.implode(',',$set).' WHERE '.implode(' AND ',$cond));$st->execute($vals);return $st->rowCount();}
 public function get_results($q){return $this->pdo->query($q)->fetchAll(PDO::FETCH_OBJ);}
 public function get_row($q){return $this->pdo->query($q)->fetch(PDO::FETCH_OBJ)?:null;}
 public function get_col($q){$this->last_error='';return $this->pdo->query($q)->fetchAll(PDO::FETCH_COLUMN);}
 public function get_var($q){if(strpos($q,'GET_LOCK')!==false)return $this->lock?1:0;if(strpos($q,'RELEASE_LOCK')!==false)return 1;return $this->pdo->query($q)->fetchColumn();}
}
function schema(){global $wpdb;$wpdb=new TestDB();$wpdb->pdo->exec("CREATE TABLE wp_ms_sequences(id INTEGER PRIMARY KEY,name TEXT,status TEXT); INSERT INTO wp_ms_sequences VALUES(1,'Main','active');
CREATE TABLE wp_ms_subscribers(id INTEGER PRIMARY KEY,email TEXT,name TEXT,status TEXT,sequence_id INT,token TEXT);
CREATE TABLE wp_ms_steps(id INTEGER PRIMARY KEY,sequence_id INT,status TEXT,subject TEXT,body_html TEXT);
CREATE TABLE wp_ms_broadcasts(id INTEGER PRIMARY KEY,sequence_id INT,subject TEXT,body_html TEXT,status TEXT,created_at TEXT,completed_at TEXT);
CREATE TABLE wp_ms_broadcast_recipients(id INTEGER PRIMARY KEY,broadcast_id INT,subscriber_id INT,track_token TEXT,status TEXT,attempts INT DEFAULT 0,claimed_at TEXT,sent_at TEXT,opened_at TEXT,clicked_at TEXT);
CREATE TABLE wp_ms_deliveries(id INTEGER PRIMARY KEY,subscriber_id INT,status TEXT,claimed_at TEXT);
CREATE TABLE wp_msb_log(id INTEGER PRIMARY KEY,email TEXT,type TEXT,action TEXT,reason TEXT,subject TEXT,created_at TEXT);
CREATE TABLE wp_egdl_deadlines(id INTEGER PRIMARY KEY,visitor_key TEXT UNIQUE,email TEXT,started_at TEXT,expires_at TEXT);");}
