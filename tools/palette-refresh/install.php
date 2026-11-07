<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__,2).'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/image.php';
$uploads=wp_upload_dir();$dir=$uploads['basedir'].'/catalog-green-2026';wp_mkdir_p($dir);
$ids=json_decode(file_get_contents(dirname(__DIR__).'/catalog-refresh/product-ids.json'),true);
$existing=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>-1]);
$legacy=[318=>'scene-caps-grid',319=>'scene-hero-lab',320=>'scene-lab-dark-bg',323=>'scene-newsletter',325=>'scene-storage-case',326=>'scene-story-batch-documentation',327=>'scene-story-certificate-of-analysis',328=>'scene-story-hplc',329=>'scene-story-storage',330=>'bpc-157',331=>'cjc-1295-no-dac',332=>'dsip',333=>'epithalon',334=>'ghk-cu',335=>'ipamorelin',336=>'scene-vial-lineup-light',337=>'scene-vial-lineup-tall',338=>'mots-c',339=>'nad-plus',340=>'sterile-water',341=>'semax',342=>'tb-500',343=>'thymosin-alpha-1',321=>'brand-white',322=>'brand-logo',324=>'brand-icon',646=>'brand-logo',647=>'brand-white',648=>'brand-mark',649=>'brand-icon'];
$new=[];
foreach(glob(__DIR__.'/results/*.json') as $result){
 $a=json_decode(file_get_contents($result),true);if(!$a||!file_exists($a['path']))throw new Exception('Invalid result '.$result);
 $slug=$a['slug'];$found=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'meta_key'=>'_catalog_green_asset_slug','meta_value'=>$slug]);
 if($found){$new[$slug]=$found[0]->ID;continue;}
 $file=$dir.'/'.$slug.'.webp';$editor=wp_get_image_editor($a['path']);if(is_wp_error($editor))throw new Exception($editor->get_error_message());
 $editor->set_quality(90);$saved=$editor->save($file,'image/webp');if(is_wp_error($saved))throw new Exception($saved->get_error_message());
 $title=isset($ids[$slug])?get_the_title($ids[$slug]):ucwords(str_replace('-',' ',str_replace('scene-','',$slug)));
 $id=wp_insert_attachment(['post_title'=>$title.' — green collection','post_mime_type'=>'image/webp','post_status'=>'inherit','post_excerpt'=>'AI-generated illustrative imagery; actual packaging may vary.'],$file);
 wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$file));
 update_post_meta($id,'_catalog_green_asset_slug',$slug);update_post_meta($id,'_catalog_asset_slug',$slug);update_post_meta($id,'_catalog_palette','green');
 update_post_meta($id,'_wp_attachment_image_alt',isset($ids[$slug])?$title.' research vial with green cap':$title.' laboratory photograph in the green brand palette');
 $new[$slug]=$id;echo 'Installed '.$slug.PHP_EOL;
}
foreach(['brand-logo'=>'peptide-research-logo-green.svg','brand-white'=>'peptide-research-logo-white-green.svg','brand-mark'=>'peptide-research-mark-green.svg','brand-icon'=>'peptide-research-site-icon-green.png'] as $slug=>$file){
 $path=$uploads['basedir'].'/brand/'.$file;if(!file_exists($path))continue;
 $found=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'meta_key'=>'_green_brand_asset','meta_value'=>$slug]);
 if($found){$new[$slug]=$found[0]->ID;continue;}
 $svg=str_ends_with($file,'.svg');$id=wp_insert_attachment(['post_title'=>'Peptide Research — '.$slug.' green','post_mime_type'=>$svg?'image/svg+xml':'image/png','post_status'=>'inherit'],$path);
 wp_update_attachment_metadata($id,$svg?['width'=>$slug==='brand-mark'?56:426,'height'=>64,'file'=>'brand/'.$file,'sizes'=>[]]:wp_generate_attachment_metadata($id,$path));
 update_post_meta($id,'_green_brand_asset',$slug);update_post_meta($id,'_wp_attachment_image_alt','Peptide Research');$new[$slug]=$id;
}
$map=[];$urls=[];$retire=[];
foreach($existing as $attachment){
 $slug=$legacy[$attachment->ID]??get_post_meta($attachment->ID,'_catalog_asset_slug',true);
 if(!$slug||!isset($new[$slug])||get_post_meta($attachment->ID,'_catalog_palette',true)==='green')continue;
 $old=$attachment->ID;$replacement=$new[$slug];if($old===$replacement)continue;
 $map[$old]=$replacement;$oldurl=wp_get_attachment_url($old);$newurl=wp_get_attachment_url($replacement);$urls[$oldurl]=$newurl;
 $meta=wp_get_attachment_metadata($old);foreach(($meta['sizes']??[]) as $size=>$data)$urls[dirname($oldurl).'/'.$data['file']]=wp_get_attachment_image_url($replacement,$size)?:$newurl;
 $retire[]=$old;
}
$replace=function($data)use(&$replace,$map,$urls){
 if(is_string($data)){$data=strtr($data,$urls);foreach($map as $from=>$to)$data=preg_replace('/wp-image-'.$from.'(?![0-9])/','wp-image-'.$to,$data);return $data;}
 if(!is_array($data))return $data;
 if(isset($data['id'],$data['url'])&&isset($map[$data['id']])){$data['id']=$map[$data['id']];$data['url']=wp_get_attachment_url($data['id']);$data['alt']=get_post_meta($data['id'],'_wp_attachment_image_alt',true);}
 foreach($data as $key=>$value)$data[$key]=$replace($value);return $data;
};
foreach(get_posts(['post_type'=>['post','page','elementor_library'],'post_status'=>'any','numberposts'=>-1]) as $post){
 foreach(['_elementor_data','_elementor_page_settings'] as $key){$data=get_post_meta($post->ID,$key,true);if(!$data)continue;$json=$key==='_elementor_data';$updated=$replace($json?json_decode($data,true):$data);update_post_meta($post->ID,$key,$json?wp_slash(wp_json_encode($updated)):$updated);}
 $content=$replace($post->post_content);if($content!==$post->post_content)wp_update_post(['ID'=>$post->ID,'post_content'=>$content]);
 $thumb=get_post_thumbnail_id($post->ID);if(isset($map[$thumb]))set_post_thumbnail($post->ID,$map[$thumb]);
}
$kit=get_option('elementor_active_kit');$settings=get_post_meta($kit,'_elementor_page_settings',true);update_post_meta($kit,'_elementor_page_settings',$replace($settings));
foreach($ids as $slug=>$id)if(isset($new[$slug]))set_post_thumbnail($id,$new[$slug]);
if(isset($new['brand-logo']))set_theme_mod('custom_logo',$new['brand-logo']);
if(isset($new['brand-icon']))update_option('site_icon',$new['brand-icon']);
file_put_contents(__DIR__.'/replacement-plan.json',json_encode(['new'=>$new,'map'=>$map,'urls'=>$urls,'retire'=>$retire],JSON_PRETTY_PRINT));
\Elementor\Plugin::$instance->files_manager->clear_cache();
echo count($new).' green assets installed; '.count($retire)." old media mapped.\n";
if(in_array('--delete-old',$argv,true)){
 if(count(glob(__DIR__.'/results/*.json'))!==69||count($new)!==73)throw new Exception('Full image set is required before cleanup.');
 foreach($retire as $old){
  $file=get_attached_file($old);$resolved=str_replace('\\','/',realpath($file)?:$file);$base=rtrim(str_replace('\\','/',realpath($uploads['basedir'])),'/').'/';
  if(!str_starts_with(strtolower($resolved),strtolower($base)))throw new Exception('Unexpected media path '.$file);
  if(!wp_delete_attachment($old,true))throw new Exception('Could not delete media '.$old);
 }
 file_put_contents(__DIR__.'/deleted-media.json',json_encode($retire));echo 'Removed '.count($retire)." superseded media items.\n";
}
