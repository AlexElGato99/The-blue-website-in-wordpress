<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__,2).'/wp-load.php';
function walkLayout(&$nodes,$callback){foreach($nodes as &$node){$callback($node);if(!empty($node['elements']))walkLayout($node['elements'],$callback);}}
foreach([47,48,49,117] as $id){
 $nodes=json_decode(get_post_meta($id,'_elementor_data',true),true);
 walkLayout($nodes,function(&$node)use($id){
  $s=&$node['settings'];
  if($id===117&&($s['_title']??'')==='Card text'){
   $has=false;foreach($node['elements'] as $e)if(($e['id']??'')==='packsum')$has=true;
   if(!$has)array_splice($node['elements'],2,0,[['id'=>'packsum','elType'=>'widget','widgetType'=>'shortcode','settings'=>['shortcode'=>'[catalog_pack_summary]'],'elements'=>[]]]);
  }
  if(($node['widgetType']??'')==='counter'&&($s['ending_number']??0)==12)$s['ending_number']=58;
  if($id===49&&isset($s['link']['url'])&&preg_match('~/category/catalog/([^/]+)/~',$s['link']['url'],$m)){
   $term=get_term_by('slug',$m[1],'category');if($term)walkLayout($node['elements'],function(&$e)use($term){if(preg_match('/^\d+ entr/',$e['settings']['title']??''))$e['settings']['title']=$term->count.' entries';});
  }
  if($id===48&&($node['widgetType']??'')==='loop-grid'){$s['post_query_orderby']='title';$s['post_query_order']='asc';}
 });
 update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($nodes)));
}
if(class_exists('Elementor\\Plugin'))\Elementor\Plugin::$instance->files_manager->clear_cache();
echo "Catalog layouts updated\n";

