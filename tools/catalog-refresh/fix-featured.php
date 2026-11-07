<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
$original=json_decode(file_get_contents('C:/Users/ayoub/site-audit.json'),true);
foreach($original as $p){$special=array_filter(array_column($p['categories'],'term_id'),fn($id)=>in_array($id,[306,307]));if($special)wp_set_post_categories($p['id'],$special,true);}
$id=39;$p=get_post($id);$c=str_replace('<th scope="col">Blend specification</th>','',$p->post_content);$c=str_replace('<td>See product name</td>','',$c);wp_update_post(['ID'=>$id,'post_content'=>$c]);
echo "Featured categories restored\n";
