<?php
// Isolated test for write confirmation and published-post copy behaviour.
define('ABSPATH', __DIR__ . '/');
class WP_Error { private $m; function __construct($c,$m){$this->m=$m;} function get_error_message(){return $this->m;} }
function is_wp_error($x){return $x instanceof WP_Error;}
$GLOBALS['transients']=array();$GLOBALS['posts']=array(27=>(object) array('ID'=>27,'post_type'=>'post','post_status'=>'publish','post_modified_gmt'=>'2026-10-10 10:00:00'),28=>(object)array('ID'=>28,'post_type'=>'post','post_status'=>'draft','post_modified_gmt'=>'2026-10-10 10:00:00'));
function add_action($n,$cb){}
function current_user_can($what,$id=null){return true;}
function check_ajax_referer($a,$b){}
function sanitize_text_field($t){return trim(strip_tags($t));}
function wp_unslash($v){return $v;}
function get_current_user_id(){return 5;}
function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function delete_transient($key){unset($GLOBALS['transients'][$key]);}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function absint($n){return abs((int)$n);}
function wp_kses_post($s){return $s;}
function wp_slash($arr){return $arr;}
function wp_insert_post($args,$return_error){$GLOBALS['posted']=$args;return $args['ID']??333;}
function update_post_meta($id,$k,$value){$GLOBALS['meta'][$k]=$value;}
function get_edit_post_link($id,$ctx){return 'https://test.example/wp-admin/post.php?post='.$id.'&action=edit';}
class Capture extends Exception {public $data;function __construct($data){$this->data=$data;parent::__construct();}}
function wp_send_json_success($d){throw new Capture($d);}
function wp_send_json_error($d,$status){throw new Capture(array('error'=>$d));}
class WPR_YAI_Contract { public static function valid_type($t){return $t==='post';} public static function normalize_fields($v,$type){return $v;} public static function normalize_terms($v,$type){return $v;} public static function acf_writable($t){return array();}}
require dirname(__DIR__).'/includes/class-core.php';
function verify($yes,$msg){if(!$yes){fwrite(STDERR,"FAIL $msg\n");exit(1);}echo "OK $msg\n";}
function save_candidate($original){
 $ticket='1234567890abcdef1234567890abcdef';
 $key='wpr_yai_ticket_5_'.$ticket;
 $GLOBALS['transients'][$key]=array('mode'=>'rewrite','post_id'=>$original,'modified_gmt'=>'2026-10-10 10:00:00','candidate'=>array('title'=>'Новая версия','slug'=>'new-post','excerpt'=>'Анонс','content_html'=>'<h2>Вступление</h2><p>Текст</p>','seo_title'=>'SEO','meta_description'=>'Описание','focus_keyphrase'=>'слово'));
 $_POST=array('ticket'=>$ticket);
 try{WPR_YAI_Core::ajax_save();}catch(Capture $e){return $e->data;}
 return null;
}
$result=save_candidate(27);
verify($result['post_id']===333 && empty($GLOBALS['posted']['ID']),'published source creates new draft');
verify($GLOBALS['posted']['post_status']==='draft','new post not published');
verify($GLOBALS['meta']['_yoast_wpseo_focuskw']==='слово','Yoast meta saved');
$result=save_candidate(28);
verify($result['post_id']===28 && $GLOBALS['posted']['ID']===28,'draft can be updated');
verify($GLOBALS['posted']['post_status']==='draft','updated post remains draft');
