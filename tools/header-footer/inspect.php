<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
echo 'URL '.home_url().'\n';
foreach(get_posts(['post_type'=>['elementor_library','page'],'numberposts'=>-1,'post_status'=>'publish']) as $p){echo $p->ID.' '.$p->post_type.' '.$p->post_title.' '.get_post_meta($p->ID,'_elementor_template_type',true).' '.json_encode(get_post_meta($p->ID,'_elementor_conditions',true))."\n";if(in_array(get_post_meta($p->ID,'_elementor_template_type',true),['header','footer']))file_put_contents(__DIR__.'/template-'.$p->ID.'.json',json_encode(['data'=>get_post_meta($p->ID,'_elementor_data',true),'settings'=>get_post_meta($p->ID,'_elementor_page_settings',true)],JSON_PRETTY_PRINT));}
