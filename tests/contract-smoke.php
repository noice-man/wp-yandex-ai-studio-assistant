<?php
// Standalone schema + validation tests, without WordPress runtime.
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
class WP_Error {private $code;private $msg;public function __construct($a,$b){$this->code=$a;$this->msg=$b;}public function get_error_message(){return $this->msg;}}
function is_wp_error($value){return $value instanceof WP_Error;}
function current_user_can($cap){return true;}
function get_post_types($args,$format){return array('post'=>(object) array('labels'=>(object)array('name'=>'Записи'),'cap'=>(object)array('edit_posts'=>'edit_posts')), 'case'=>(object)array('labels'=>(object)array('name'=>'Кейсы'),'cap'=>(object)array('edit_posts'=>'edit_posts')), 'attachment'=>(object)array('labels'=>(object)array('name'=>'Медиа'),'cap'=>(object)array('edit_posts'=>'edit_posts')));}
function post_type_supports($post_type,$feature){return true;}
function get_all_post_type_supports($post_type){return array('title'=>true,'editor'=>true,'thumbnail'=>true);}
function wp_strip_all_tags($text){return strip_tags($text);}
function get_post_type_object($type){return (object) array();}
function get_object_taxonomies($type,$format){return 'case'===$type?array('topic'=>(object)array('show_ui'=>true,'labels'=>(object)array('name'=>'Темы'),'hierarchical'=>true,'cap'=>(object)array('assign_terms'=>'edit_posts'))):array();}
function get_terms($args){return array((object)array('term_id'=>3,'name'=>'UX','slug'=>'ux'));}
function term_exists($id,$tax){return 'topic'===$tax&&3===$id;}
function get_registered_meta_keys($obj,$type){return array('public_summary'=>array('show_in_rest'=>true,'type'=>'string'),'private_token'=>array('show_in_rest'=>true,'type'=>'string'),'hidden'=>array('show_in_rest'=>false,'type'=>'string'));}
function acf_get_field_groups(){return array(array('title'=>'Кейс','active'=>true,'location'=>array(array(array('param'=>'post_type','operator'=>'==','value'=>'case')))));}
function acf_get_fields($group){return array(array('name'=>'hero_title','key'=>'field_hero','type'=>'text','label'=>'Главный заголовок','instructions'=>'Заголовок кейса','required'=>true),array('name'=>'outcome','key'=>'field_outcome','type'=>'select','label'=>'Итог','choices'=>array('win'=>'Рост','hold'=>'Без изменения')),array('name'=>'gallery','key'=>'field_gallery','type'=>'gallery','label'=>'Галерея'),array('name'=>'api_secret','key'=>'field_secret','type'=>'text','label'=>'Токен'));}
function get_page_templates($post,$type){return array('Кейс'=>'templates/case.php');}
class ThemeMock {public function get($key){return 'Моя тема';}}
function wp_get_theme(){return new ThemeMock();}
function apply_filters($name,$value,$post_type){return array('guide'=>'Описание шаблона: выводится поле hero_title.');}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function sanitize_text_field($text){return trim(strip_tags($text));}
function sanitize_textarea_field($text){return trim(strip_tags($text));}
function wp_kses_post($text){return strip_tags($text,'<p><strong>');}
function sanitize_key($text){return preg_replace('/[^a-z0-9_-]/','',strtolower($text));}
require dirname(__DIR__).'/includes/class-contract.php';
function ok($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}echo "OK $msg\n";}
$types=WPR_YAI_Contract::types();
ok(isset($types['case'])&&!isset($types['attachment']),'editable CPT discovered');
$schema=WPR_YAI_Contract::schema('case');
ok($schema['acf_groups'][0]['fields'][0]['key']==='field_hero','ACF field keys discovered');
ok($schema['acf_groups'][0]['fields'][2]['writable']===false,'complex ACF remains read-only');
ok(count($schema['registered_meta_readonly'])===1,'secrets and unregistered REST meta excluded');
ok($schema['taxonomies'][0]['terms_sample'][0]['id']===3,'existing taxonomy term recognized');
ok(isset($schema['developer_notes']),'developer contract extension included');
$valid=WPR_YAI_Contract::normalize_fields(array('hero_title'=>'Лучший кейс','outcome'=>'win'),'case');
ok(!is_wp_error($valid)&&$valid['outcome']==='win','ACF simple fields accepted');
ok(is_wp_error(WPR_YAI_Contract::normalize_fields(array('outcome'=>'notvalid'),'case')),'unknown choice rejected');
ok(is_wp_error(WPR_YAI_Contract::normalize_fields(array('gallery'=>12),'case')),'complex field write rejected');
ok(is_wp_error(WPR_YAI_Contract::normalize_fields(array('api_secret'=>'abc'),'case')),'secret-looking field rejected');
ok(WPR_YAI_Contract::normalize_terms(array('topic'=>array(3)),'case')['topic'][0]===3,'existing term accepted');
ok(is_wp_error(WPR_YAI_Contract::normalize_terms(array('topic'=>array(99)),'case')),'unknown term rejected');
