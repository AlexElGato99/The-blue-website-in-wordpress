<?php
if(PHP_SAPI!=='cli')exit;
$products=json_decode(file_get_contents(__DIR__.'/products.json'),true);$reference=null;$errors=[];
foreach($products as $p){
 [$w,$h]=getimagesize($p['path']);if($w!==1600||$h!==1600)$errors[]='Wrong dimensions: '.$p['slug'];
 $im=imagecreatefrompng($p['path']);$ctx=hash_init('sha256');
 foreach([['x'=>0,'y'=>0,'width'=>1600,'height'=>850],['x'=>0,'y'=>1120,'width'=>1600,'height'=>480],['x'=>0,'y'=>850,'width'=>530,'height'=>270],['x'=>1070,'y'=>850,'width'=>530,'height'=>270]] as $box){$crop=imagecrop($im,$box);ob_start();imagepng($crop,null,1);$bytes=ob_get_clean();hash_update($ctx,$bytes);imagedestroy($crop);}
 $hash=hash_final($ctx);if($reference===null)$reference=$hash;elseif($reference!==$hash)$errors[]='Bottle geometry or alignment differs: '.$p['slug'];imagedestroy($im);
}
file_put_contents(__DIR__.'/geometry-audit.json',json_encode(['products'=>count($products),'width'=>1600,'height'=>1600,'outside_label_sha256'=>$reference,'errors'=>$errors],JSON_PRETTY_PRINT));
if($errors)throw new Exception(implode("\n",$errors));echo count($products)." images: exact 1600x1600 dimensions and pixel-identical bottle geometry, scale, margins and alignment outside product-name area.\n";
