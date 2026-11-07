<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$plan=json_decode(file_get_contents(__DIR__.'/replacement-plan.json'),true);$results=[];
foreach($plan['new'] as $slug=>$id){if(str_starts_with($slug,'brand-'))continue;$im=imagecreatefromwebp(get_attached_file($id));$blue=0;$total=0;
 for($y=0;$y<imagesy($im);$y+=8)for($x=0;$x<imagesx($im);$x+=8){$rgb=imagecolorat($im,$x,$y);$r=($rgb>>16)&255;$g=($rgb>>8)&255;$b=$rgb&255;$total++;if($b>70&&$b>$g*1.15&&$b>$r*1.2)$blue++;}
 $results[$slug]=round(100*$blue/$total,3);imagedestroy($im);
}
file_put_contents(__DIR__.'/color-report.json',json_encode($results,JSON_PRETTY_PRINT));arsort($results);echo 'Largest blue-dominant pixel shares: '.json_encode(array_slice($results,0,5,true)).PHP_EOL;
