<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$id=48;$nodes=json_decode(get_post_meta($id,'_elementor_data',true),true);
$f=function(&$ns)use(&$f){foreach($ns as &$n){if(str_contains($n['settings']['editor']??'','Every entry lists its compound class'))$n['settings']['editor']='<p>Explore 58 products and 117 specifications. Each entry lists vial content, pack size and SKU. Contact us for current availability and batch documentation.</p>';if(!empty($n['elements']))$f($n['elements']);}};$f($nodes);update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($nodes)));\Elementor\Plugin::$instance->files_manager->clear_cache();echo "Catalog introduction updated\n";
