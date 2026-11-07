<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$labels=['brand-logo'=>'Peptide Research logo','brand-white'=>'Peptide Research logo — white lettering','brand-mark'=>'Peptide Research symbol','brand-icon'=>'Peptide Research favicon'];
foreach($labels as $slug=>$title){$p=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'meta_key'=>'_green_brand_asset','meta_value'=>$slug]);if($p)wp_update_post(['ID'=>$p[0]->ID,'post_title'=>$title]);}
$im=imagecreatefrompng(ABSPATH.'wp-content/uploads/brand/peptide-research-site-icon-green.png');echo 'Favicon color: '.json_encode(imagecolorsforindex($im,imagecolorat($im,256,40))).PHP_EOL;echo 'Transparent corner: '.json_encode(imagecolorsforindex($im,imagecolorat($im,0,0))).PHP_EOL;
