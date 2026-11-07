<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$plan=json_decode(file_get_contents(__DIR__.'/replacement-plan.json'),true);$errors=[];$new=$plan['new'];
foreach($new as $slug=>$id){$file=get_attached_file($id);if(!file_exists($file))$errors[]='Missing '.$slug;}
$ids=json_decode(file_get_contents(dirname(__DIR__).'/catalog-refresh/product-ids.json'),true);
foreach($ids as $slug=>$id){if(!isset($new[$slug])||get_post_thumbnail_id($id)!==$new[$slug])$errors[]='Wrong product image '.$slug;}
foreach(get_posts(['post_type'=>['post','page','elementor_library'],'post_status'=>'any','numberposts'=>-1]) as $p){
 $data=$p->post_content.' '.get_post_meta($p->ID,'_elementor_data',true).' '.wp_json_encode(get_post_meta($p->ID,'_elementor_page_settings',true));
 foreach($plan['urls'] as $old=>$replacement)if(str_contains($data,$old)||str_contains($data,str_replace('/','\\/',$old)))$errors[]='Old URL on '.$p->ID.': '.$old;
 if(in_array(get_post_thumbnail_id($p->ID),$plan['retire'],true))$errors[]='Old thumbnail '.$p->ID;
}
if(get_theme_mod('custom_logo')!=$new['brand-logo'])$errors[]='Old custom logo';if(get_option('site_icon')!=$new['brand-icon'])$errors[]='Old site icon';
file_put_contents(__DIR__.'/audit-report.json',json_encode(['assets'=>count($new),'products'=>count($ids),'retired_media'=>count($plan['retire']),'errors'=>$errors],JSON_PRETTY_PRINT));
if($errors)throw new Exception(implode("\n",$errors));echo count($new).' current assets, '.count($ids)." product images, no old active references.\n";
