<?php
// Run from the command line only. Re-runnable catalog import.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 2) . '/wp-load.php';
$catalog=json_decode(file_get_contents(__DIR__.'/catalog.json'),true);
$existing=['GHK-Cu'=>30,'BPC-157'=>31,'TB-500'=>32,'MOTS-c'=>33,'Epithalon'=>34,'Ipamorelin'=>35,'CJC-1295 (no DAC)'=>36,'Semax'=>37,'DSIP'=>38,'NAD+'=>39,'Thymosin Alpha-1'=>40,'Sterile Water'=>41];
$categories=[
 'cognition-neuro'=>['DSIP','Selank','Semax','Adamax','Melatonin','Dihexa'],
 'energy-mitochondria'=>['AICAR','Adipotide','Semaglutide','SS-31','Tirzepatide','AOD9604','MOTS-c','Retatrutide','Glutathione','5-amino-1mq','NAD+','Survodutide','Cagrilintide','Cagrilintide + Semaglutide'],
 'recovery-research'=>['Epithalon','BPC-157','BPC-157 + TB-500','TB-500','MGF','PEG MGF','Thymalin','Thymosin Alpha-1','FOXO4','LL37','TB-500 + BPC-157 + GHK-Cu','BT10 + BP10 + CU50 + KP10','KPV','VIP'],
 'skin-copper'=>['GHK-Cu','AHK-Cu','SNAP-8'],
 'lab-supplies'=>['Sterile Water','Benzyl Alcohol 0.9%','Acetic Acid 0.6%'],
];
$blend=['BB10'=>'BPC 5 mg + TB 5 mg','BB20'=>'BPC 10 mg + TB 10 mg','CP10'=>'CJC-1295 without DAC 5 mg + IPA 5 mg','BBG70'=>'TB 10 mg + BPC-157 10 mg + GHK 50 mg','Klow80'=>'BT10 + BP10 + CU50 + KP10 (as listed)','CS10'=>'Cagrilintide 5 mg + Semaglutide 5 mg'];
$result=[];
foreach($catalog as $p){
 $found=get_posts(['post_type'=>'post','post_status'=>'any','meta_key'=>'_catalog_source_slug','meta_value'=>$p['slug'],'numberposts'=>1]);
 $id=$existing[$p['name']]??($found[0]->ID??0);
 $cat='hormone-signaling';foreach($categories as $slug=>$names)if(in_array($p['name'],$names,true))$cat=$slug;
 $term=get_term_by('slug',$cat,'category');if(!$term)throw new Exception('Missing category '.$cat);
 $sizes=[];$table='';
 foreach($p['variants'] as $v){
  $size=preg_split('/[*\/]/',$v['specification'])[0];$size=preg_replace('/(mg|ml|IU)/',' $1',$size);$sizes[]=$size;
  $table.='<tr><td>'.esc_html($v['sku']).'</td><td>'.esc_html($size).'</td><td>10 vials</td>'.(isset($blend[$v['sku']])?'<td>'.esc_html($blend[$v['sku']]).'</td>':((count(array_intersect(array_column($p['variants'],'sku'),array_keys($blend)))>0)?'<td>See product name</td>':'')).'</tr>';
 }
 $intro=esc_html($p['name']).' is available in '.esc_html(implode(', ',$sizes)).' formats, supplied in packs of 10 vials.';
 $content='<p>'.$intro.'</p><h2>Available specifications</h2><div class="catalog-specs"><table><thead><tr><th scope="col">SKU</th><th scope="col">Content per vial</th><th scope="col">Pack size</th>'.((count(array_intersect(array_column($p['variants'],'sku'),array_keys($blend)))>0)?'<th scope="col">Blend specification</th>':'').'</tr></thead><tbody>'.$table.'</tbody></table></div>';
 $content.='<p>Contact us with the SKU and quantity for current pricing, availability and batch documentation.</p><p class="catalog-image-note">Product image is an AI-generated packaging illustration. Actual packaging may vary.</p>';
 $id=wp_insert_post(['ID'=>$id,'post_type'=>'post','post_status'=>'publish','post_title'=>$p['name'],'post_name'=>$id?get_post_field('post_name',$id):$p['slug'],'post_content'=>$content,'post_excerpt'=>implode(' / ',$sizes).' per vial · 10-vial pack','post_category'=>[$term->term_id]],true);
 if(is_wp_error($id))throw new Exception($id->get_error_message());
 update_post_meta($id,'_catalog_source_slug',$p['slug']);
 update_post_meta($id,'_catalog_variants',$p['variants']);
 update_post_meta($id,'_catalog_price_currency','EUR');
 $result[$p['slug']]=$id;
}
// Retain the former accessory as a draft: it is absent from the supplied list.
wp_update_post(['ID'=>42,'post_status'=>'draft']);
file_put_contents(__DIR__.'/product-ids.json',json_encode($result,JSON_PRETTY_PRINT));
echo count($result)." products imported\n";


