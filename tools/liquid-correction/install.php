<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__,2).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/image.php';
$products=json_decode(file_get_contents(__DIR__.'/products.json'),true);if(count($products)!==58)throw new Exception('Expected 58 product images');
$assets=$products;foreach(glob(__DIR__.'/scenes/*.json') as $f)$assets[]=json_decode(file_get_contents($f),true);if(count($assets)!==69)throw new Exception('Expected 11 corrected supporting images');
$existing=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>-1]);$uploads=wp_upload_dir();$dir=$uploads['basedir'].'/catalog-liquid-2026';wp_mkdir_p($dir);
$ids=json_decode(file_get_contents(dirname(__DIR__).'/catalog-refresh/product-ids.json'),true);$new=[];
foreach($assets as $a){
 $slug=$a['slug'];if(!file_exists($a['path']))throw new Exception('Missing source '.$slug);
 $found=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_liquid_asset_slug','meta_value'=>$slug,'numberposts'=>1]);
 if($found){$new[$slug]=$found[0]->ID;continue;}
 $file=$dir.'/'.$slug.'.webp';$editor=wp_get_image_editor($a['path']);if(is_wp_error($editor))throw new Exception($editor->get_error_message());$editor->set_quality(94);$saved=$editor->save($file,'image/webp');if(is_wp_error($saved))throw new Exception($saved->get_error_message());
 $title=isset($ids[$slug])?get_the_title($ids[$slug]):ucwords(str_replace('-',' ',str_replace('scene-','',$slug)));
 $id=wp_insert_attachment(['post_title'=>$title.' — clear liquid','post_mime_type'=>'image/webp','post_status'=>'inherit','post_excerpt'=>'AI-generated illustrative packaging. Actual product appearance may vary.'],$file);
 wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$file));update_post_meta($id,'_liquid_asset_slug',$slug);update_post_meta($id,'_catalog_asset_slug',$slug);update_post_meta($id,'_catalog_palette','green');
 update_post_meta($id,'_wp_attachment_image_alt',isset($ids[$slug])?$title.' green-capped research vial containing clear, colorless liquid':$title.' with clear, colorless liquid in the laboratory vials');
 if(isset($ids[$slug]))update_post_meta($id,'_bottle_layout',['master'=>'shared-liquid-master','width'=>1600,'height'=>1600,'image_x'=>0,'image_y'=>0,'name_center_x'=>800,'name_center_y'=>980]);
 $new[$slug]=$id;echo 'Installed '.$slug.PHP_EOL;
}
$master=wp_get_image_editor('D:/wordpress-liquid-correction/bottle-master.png');if(!is_wp_error($master)){$master->set_quality(98);$master->save($dir.'/bottle-master.webp','image/webp');}
$legacy=[318=>'scene-caps-grid',319=>'scene-hero-lab',320=>'scene-lab-dark-bg',323=>'scene-newsletter',325=>'scene-storage-case',326=>'scene-story-batch-documentation',327=>'scene-story-certificate-of-analysis',328=>'scene-story-hplc',329=>'scene-story-storage',330=>'bpc-157',331=>'cjc-1295-no-dac',332=>'dsip',333=>'epithalon',334=>'ghk-cu',335=>'ipamorelin',336=>'scene-vial-lineup-light',337=>'scene-vial-lineup-tall',338=>'mots-c',339=>'nad-plus',340=>'sterile-water',341=>'semax',342=>'tb-500',343=>'thymosin-alpha-1'];
$brandPlan=json_decode(file_get_contents(dirname(__DIR__).'/palette-refresh/replacement-plan.json'),true);$brand=$brandPlan['new'];
foreach([321=>'brand-white',322=>'brand-logo',324=>'brand-icon',646=>'brand-logo',647=>'brand-white',648=>'brand-mark',649=>'brand-icon'] as $old=>$slug)if(isset($brand[$slug]))$legacy[$old]=$slug;
$map=[];$urls=[];$retire=[];
foreach($existing as $p){$slug=$legacy[$p->ID]??get_post_meta($p->ID,'_catalog_asset_slug',true);$target=$new[$slug]??($brand[$slug]??null);if(!$slug||!$target||$target===$p->ID)continue;
 $map[$p->ID]=$target;$oldurl=wp_get_attachment_url($p->ID);$targeturl=wp_get_attachment_url($target);$urls[$oldurl]=$targeturl;$meta=wp_get_attachment_metadata($p->ID);foreach(($meta['sizes']??[]) as $size=>$data)$urls[dirname($oldurl).'/'.$data['file']]=wp_get_attachment_image_url($target,$size)?:$targeturl;$retire[]=$p->ID;
}
$replace=function($data)use(&$replace,$map,$urls){if(is_string($data)){$data=strtr($data,$urls);foreach($map as $from=>$to)$data=preg_replace('/wp-image-'.$from.'(?![0-9])/','wp-image-'.$to,$data);return $data;}if(!is_array($data))return $data;if(isset($data['id'],$data['url'])&&isset($map[$data['id']])){$data['id']=$map[$data['id']];$data['url']=wp_get_attachment_url($data['id']);$data['alt']=get_post_meta($data['id'],'_wp_attachment_image_alt',true);}foreach($data as $key=>$value)$data[$key]=$replace($value);return $data;};
foreach(get_posts(['post_type'=>['post','page','elementor_library'],'post_status'=>'any','numberposts'=>-1]) as $p){foreach(['_elementor_data','_elementor_page_settings'] as $key){$v=get_post_meta($p->ID,$key,true);if(!$v)continue;$json=$key==='_elementor_data';$v=$replace($json?json_decode($v,true):$v);update_post_meta($p->ID,$key,$json?wp_slash(wp_json_encode($v)):$v);}$content=$replace($p->post_content);if($content!==$p->post_content)wp_update_post(['ID'=>$p->ID,'post_content'=>$content]);$thumb=get_post_thumbnail_id($p->ID);if(isset($map[$thumb]))set_post_thumbnail($p->ID,$map[$thumb]);}
$kit=get_option('elementor_active_kit');update_post_meta($kit,'_elementor_page_settings',$replace(get_post_meta($kit,'_elementor_page_settings',true)));
foreach($ids as $slug=>$id)set_post_thumbnail($id,$new[$slug]);
foreach([117,126] as $id){$nodes=json_decode(get_post_meta($id,'_elementor_data',true),true);$walk=function(&$ns)use(&$walk){foreach($ns as &$n){if(($n['widgetType']??'')==='theme-post-featured-image'){$n['settings']['object-fit']='contain';$n['settings']['object-position']='center center';}if(!empty($n['elements']))$walk($n['elements']);}};$walk($nodes);update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($nodes)));}
if(isset($brand['brand-logo']))set_theme_mod('custom_logo',$brand['brand-logo']);if(isset($brand['brand-icon']))update_option('site_icon',$brand['brand-icon']);
file_put_contents(__DIR__.'/replacement-plan.json',json_encode(['new'=>$new,'map'=>$map,'urls'=>$urls,'retire'=>$retire],JSON_PRETTY_PRINT));\Elementor\Plugin::$instance->files_manager->clear_cache();echo count($new).' corrected assets; '.count($retire)." obsolete media queued for cleanup.\n";
if(in_array('--delete-old',$argv,true)){
 foreach($retire as $id){$file=get_attached_file($id);$path=str_replace('\\','/',realpath($file)?:$file);$base=rtrim(str_replace('\\','/',realpath($uploads['basedir'])),'/').'/';if(!str_starts_with(strtolower($path),strtolower($base)))throw new Exception('Unexpected deletion path');if(!wp_delete_attachment($id,true))throw new Exception('Media deletion failed '.$id);}
 file_put_contents(__DIR__.'/deleted-media.json',json_encode($retire));echo 'Removed '.count($retire)." obsolete media entries.\n";
}
