<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__,2).'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/image.php';
$assets=json_decode(file_get_contents(__DIR__.'/assets.json'),true);
$ids=json_decode(file_get_contents(__DIR__.'/product-ids.json'),true);
$oldProducts=['ghk-cu'=>334,'bpc-157'=>330,'tb-500'=>342,'mots-c'=>338,'epithalon'=>333,'ipamorelin'=>335,'cjc-1295-no-dac'=>331,'semax'=>341,'dsip'=>332,'nad-plus'=>339,'thymosin-alpha-1'=>343,'sterile-water'=>340];
$oldScenes=['hero-lab'=>319,'lab-dark-bg'=>320,'caps-grid'=>318,'newsletter'=>323,'storage-case'=>325,'story-batch-documentation'=>326,'story-certificate-of-analysis'=>327,'story-hplc'=>328,'story-storage'=>329,'vial-lineup-light'=>336,'vial-lineup-tall'=>337];
$uploads=wp_upload_dir();$dir=$uploads['basedir'].'/catalog-2026';wp_mkdir_p($dir);
$replacements=[];
foreach($assets as $a){
 if(empty($a['path'])||!file_exists($a['path']))throw new Exception('Missing image '.$a['slug']);
 $scene=str_starts_with($a['slug'],'scene-');$slug=$scene?substr($a['slug'],6):$a['slug'];
 $file=$dir.'/'.$a['slug'].'.webp';
 $matches=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_catalog_asset_slug','meta_value'=>$a['slug'],'numberposts'=>1]);
 if($matches){$new=$matches[0]->ID;}else{
  $editor=wp_get_image_editor($a['path']);if(is_wp_error($editor))throw new Exception($editor->get_error_message());
  $editor->set_quality(90);$saved=$editor->save($file,'image/webp');if(is_wp_error($saved))throw new Exception($saved->get_error_message());
  $title=$scene?ucwords(str_replace('-',' ',$slug)):get_the_title($ids[$slug]);
  $new=wp_insert_attachment(['post_mime_type'=>'image/webp','post_title'=>$title.' — realistic catalog image','post_status'=>'inherit','post_excerpt'=>'AI-generated illustrative imagery; actual packaging may vary.'],$file);
  wp_update_attachment_metadata($new,wp_generate_attachment_metadata($new,$file));
  update_post_meta($new,'_wp_attachment_image_alt',$scene?$title.' laboratory scene':$title.' research vial with blue cap');
  update_post_meta($new,'_catalog_asset_slug',$a['slug']);
 }
 $old=$scene?($oldScenes[$slug]??null):($oldProducts[$slug]??null);
 if($old){
  $oldMeta=wp_get_attachment_metadata($old);$oldUrl=wp_get_attachment_url($old);$newUrl=wp_get_attachment_url($new);
  $replacements[$old]=['new'=>$new,'url'=>$newUrl,'oldurl'=>$oldUrl];
  $urlMap[$oldUrl]=$newUrl;
  foreach(($oldMeta['sizes']??[]) as $size=>$data){$urlMap[dirname($oldUrl).'/'.$data['file']]=wp_get_attachment_image_url($new,$size);}
 }
 if(!$scene)set_post_thumbnail($ids[$slug],$new);
 echo $a['slug']." installed\n";
}
function replaceAssetTree($data,$replacements){
 if(!is_array($data))return $data;
 if(isset($data['id'],$data['url'])&&isset($replacements[$data['id']])){$r=$replacements[$data['id']];$data['id']=$r['new'];$data['url']=$r['url'];$data['alt']=get_post_meta($r['new'],'_wp_attachment_image_alt',true);}
 foreach($data as $key=>$value)if(is_array($value))$data[$key]=replaceAssetTree($value,$replacements);
 return $data;
}
foreach(get_posts(['post_type'=>['page','post','elementor_library'],'post_status'=>'any','numberposts'=>-1]) as $post){
 $data=get_post_meta($post->ID,'_elementor_data',true);
 if($data){$decoded=replaceAssetTree(json_decode($data,true),$replacements);$json=wp_json_encode($decoded);foreach(($urlMap??[]) as $from=>$to)$json=str_replace(str_replace('/','\\/',$from),str_replace('/','\\/',$to),$json);update_post_meta($post->ID,'_elementor_data',wp_slash($json));}
 $content=strtr($post->post_content,$urlMap??[]);if($content!==$post->post_content)wp_update_post(['ID'=>$post->ID,'post_content'=>$content]);
 $thumb=get_post_thumbnail_id($post->ID);if(isset($replacements[$thumb]))set_post_thumbnail($post->ID,$replacements[$thumb]['new']);
}
if(class_exists('Elementor\\Plugin'))\Elementor\Plugin::$instance->files_manager->clear_cache();
echo "Asset references updated\n";
