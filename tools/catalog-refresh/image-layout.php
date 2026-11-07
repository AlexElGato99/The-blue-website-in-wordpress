<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$d=json_decode(get_post_meta(117,'_elementor_data',true),true);$d[0]['elements'][0]['elements']=array_values(array_filter($d[0]['elements'][0]['elements'],fn($e)=>($e['id']??'')!=='c5e7a06'));update_post_meta(117,'_elementor_data',wp_slash(wp_json_encode($d)));\Elementor\Plugin::$instance->files_manager->clear_cache();echo "Product image overlays cleared\n";
