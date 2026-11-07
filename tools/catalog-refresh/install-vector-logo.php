<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$dir=ABSPATH.'wp-content/uploads/brand/';$map=[];
foreach(['peptide-research-logo.svg'=>322,'peptide-research-logo-white.svg'=>321,'peptide-research-mark.svg'=>324] as $file=>$old){
 $found=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_brand_vector_file','meta_value'=>$file,'numberposts'=>1]);
 $id=$found?$found[0]->ID:wp_insert_attachment(['post_mime_type'=>'image/svg+xml','post_title'=>'Peptide Research — '.($old==324?'vector symbol':($old==321?'white vector logo':'vector logo')),'post_status'=>'inherit'],$dir.$file);
 update_post_meta($id,'_brand_vector_file',$file);update_post_meta($id,'_wp_attachment_image_alt','Peptide Research');
 wp_update_attachment_metadata($id,['width'=>$old==324?56:426,'height'=>64,'file'=>'brand/'.$file,'sizes'=>[]]);
 $map[$old]=['id'=>$id,'url'=>wp_get_attachment_url($id)];
}
set_theme_mod('custom_logo',$map[322]['id']);
$walk=function(&$nodes,$postId)use(&$walk,$map){foreach($nodes as &$node){
 $s=&$node['settings'];
 foreach($s as &$v)if(is_array($v)&&isset($v['id'],$v['url'])&&isset($map[$v['id']]))$v=array_merge($v,$map[$v['id']]);unset($v);
 if(($node['widgetType']??'')==='theme-site-logo'||(isset($s['image']['id'])&&$s['image']['id']===$map[321]['id'])){
  $s['width']=['unit'=>'px','size'=>$postId===111?250:230];$s['width_tablet']=['unit'=>'px','size'=>220];$s['width_mobile']=['unit'=>'px','size'=>200];
  $s['image_size']='full';$s['image_custom_dimension']=['width'=>'','height'=>''];
 }
 if(!empty($node['elements']))$walk($node['elements'],$postId);
}};
foreach([111,113] as $postId){$nodes=json_decode(get_post_meta($postId,'_elementor_data',true),true);$walk($nodes,$postId);update_post_meta($postId,'_elementor_data',wp_slash(wp_json_encode($nodes)));}
\Elementor\Plugin::$instance->files_manager->clear_cache();
file_put_contents(__DIR__.'/vector-logo-ids.json',json_encode($map,JSON_PRETTY_PRINT));echo "Vector header and footer logos installed\n";
