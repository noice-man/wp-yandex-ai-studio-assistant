<?php
// Isolated unit smoke tests without a running WordPress installation.
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $code; private $message;
    public function __construct($code,$message){$this->code=$code;$this->message=$message;}
    public function get_error_code(){return $this->code;}
    public function get_error_message(){return $this->message;}
}
function is_wp_error($x){return $x instanceof WP_Error;}
$GLOBALS['opts']=array();
function get_option($n,$d=''){return array_key_exists($n,$GLOBALS['opts'])?$GLOBALS['opts'][$n]:$d;}
function update_option($n,$v,$autoload=false){$GLOBALS['opts'][$n]=$v;return true;}
function wp_salt($x){return 'sample-salt-'.$x;}
function sanitize_text_field($s){return trim(strip_tags($s));}
function sanitize_textarea_field($s){return trim(strip_tags($s));}
function sanitize_title($s){return strtolower(preg_replace('/\s+/','-',preg_replace('/[^a-zA-Z0-9 -]/','', $s)));}
function wp_kses_post($s){return strip_tags($s,'<p><h2><h3><ul><li><a><strong><em>');}
function wp_strip_all_tags($s){return strip_tags($s);}
function wp_json_encode($a,$opts=0){return json_encode($a,$opts);}
function add_action($a,$b){}
function wp_remote_post($url,$data){$GLOBALS['captured']=array($url,$data); return array('code'=>200,'body'=>json_encode(array('choices'=>array(array('message'=>array('content'=>'Готово'))))));}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
class WPR_YAI_Contract { public static function normalize_fields($v,$type){return $v;} public static function normalize_terms($v,$type){return $v;} }
require dirname(__DIR__).'/includes/class-core.php';
function test($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL: $msg\n");exit(1);} echo "OK: $msg\n";}
$key='test-apikey-123';
$c=WPR_YAI_Core::encrypt_key($key);
test(!is_wp_error($c) && $c!==$key,'key encrypted');
update_option(WPR_YAI_Core::KEY_OPTION,$c);
test(WPR_YAI_Core::api_key()===$key,'key decrypted');
update_option(WPR_YAI_Core::OPTION,array('folder'=>'b1gl123456','model'=>'yandexgpt/latest'));
$r=WPR_YAI_Core::request(array(array('role'=>'user','content'=>'test')),true,200);
test($r==='Готово','chat completion response extracted');
test($GLOBALS['captured'][0]==='https://ai.api.cloud.yandex.net/v1/chat/completions','correct endpoint');
test($GLOBALS['captured'][1]['headers']['Authorization']==='Api-Key '.$key,'correct auth header');
$sent=json_decode($GLOBALS['captured'][1]['body'],true);
test($sent['model']==='gpt://b1gl123456/yandexgpt/latest','correct model URI');
test($sent['response_format']['type']==='json_schema','structured output schema sent');
$data=array('title'=>'Запись','slug'=>'a-post','excerpt'=>'Кратко','content_html'=>'<h2>Заголовок</h2><p>Текст</p><script>alert(1)</script>','seo_title'=>'SEO','meta_description'=>'Desc','focus_keyphrase'=>'word');
$candidate=WPR_YAI_Core::parse_candidate('```json'."\n".json_encode($data,JSON_UNESCAPED_UNICODE)."\n```\n");
test(is_array($candidate) && !str_contains($candidate['content_html'],'<script>'),'unsafe HTML removed');
test(is_wp_error(WPR_YAI_Core::parse_candidate('{invalid')),'invalid JSON rejected');
foreach(array('title','content_html','meta_description') as $name){$tmp=$data;unset($tmp[$name]);test(is_wp_error(WPR_YAI_Core::parse_candidate(json_encode($tmp))),'missing '.$name.' rejected');}
