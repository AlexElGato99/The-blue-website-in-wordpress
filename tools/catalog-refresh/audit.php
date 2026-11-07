<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$catalog=json_decode(file_get_contents(__DIR__.'/catalog.json'),true);$ids=json_decode(file_get_contents(__DIR__.'/product-ids.json'),true);$result=[];$skus=[];
foreach($catalog as $p){$id=$ids[$p['slug']];$vars=get_post_meta($id,'_catalog_variants',true);if($vars!==$p['variants'])throw new Exception('Variant mismatch '.$p['slug']);$thumb=get_post_thumbnail_id($id);$path=get_attached_file($thumb);if(!$thumb||!file_exists($path)||get_post_meta($thumb,'_catalog_asset_slug',true)!==$p['slug'])throw new Exception('Image mismatch '.$p['slug']);foreach($vars as $v){if(isset($skus[$v['sku']]))throw new Exception('Duplicate SKU');$skus[$v['sku']]=1;}$result[]=['name'=>$p['name'],'url'=>get_permalink($id),'image'=>wp_get_attachment_url($thumb),'image_path'=>$path,'variants'=>count($vars)];}
file_put_contents(__DIR__.'/validated-products.json',json_encode($result,JSON_PRETTY_PRINT));echo count($result).' products, '.count($skus)." unique SKUs; all specifications and image assignments validated\n";
