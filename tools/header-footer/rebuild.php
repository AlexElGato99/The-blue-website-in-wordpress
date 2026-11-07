<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/wp-load.php';
function clean_values($v){if(is_array($v))return array_map('clean_values',$v);return is_string($v)?str_replace(['\\n','\\"','\\/'],["\n",'"','/'],$v):$v;}
function each_element(&$nodes,$fn){foreach($nodes as &$e){$fn($e);if(!empty($e['elements']))each_element($e['elements'],$fn);}}
foreach([184,186,188] as $id){
$original=get_post_meta($id,'_elementor_data',true);$backup=__DIR__.'/backup-'.$id.'.json';if(!file_exists($backup))file_put_contents($backup,json_encode(['data'=>$original,'settings'=>get_post_meta($id,'_elementor_page_settings',true)],JSON_PRETTY_PRINT));
$d=clean_values(json_decode($original,true));
each_element($d,function(&$e)use($id){$s=&$e['settings'];
if(($e['widgetType']??'')==='polylang-language-switcher'){$s['aspect_ratio_flag']='43';$s['size_flag']=['unit'=>'px','size'=>20];if($s['layout']==='dropdown'){$s['typography_menu_item_typography']='custom';$s['typography_menu_item_font_family']='Inter';$s['typography_menu_item_font_size']=['unit'=>'px','size'=>13];$s['custom_css']=<<<'CSS'
selector{width:auto;--rotate:0deg}
selector .cpel-switcher__flag{width:22px;height:15px;flex:0 0 22px}
selector .cpel-switcher__flag img{width:22px;height:15px;object-fit:cover;border-radius:0}
selector .cpel-switcher__toggle a{display:flex;align-items:center;gap:8px;padding:8px 0;background:transparent!important;color:inherit;font-size:13px}
selector .cpel-switcher__toggle .cpel-switcher__name{display:none}
selector .cpel-switcher__code{padding:0;font-weight:600}
selector .cpel-switcher__icon{font-size:9px;margin-left:2px}
selector .cpel-switcher__list{max-height:none!important;top:calc(100% + 10px);inset-inline-end:0;inset-inline-start:auto;min-width:190px;padding:6px;background:#fff;border:1px solid #E5E1D6;border-radius:10px;box-shadow:0 18px 40px rgba(20,26,21,.14);opacity:0;visibility:hidden;transform:translateY(-6px);transition:opacity .2s,transform .2s,visibility .2s}
selector .cpel-switcher__toggle--on + .cpel-switcher__list{opacity:1;visibility:visible;transform:none}
selector .cpel-switcher__list a{display:flex!important;align-items:center;padding:10px!important;color:#6B6B6B!important;font-size:13px;border-radius:6px}
selector .cpel-switcher__list a:hover{background:#F6F3EA;color:#171717!important}
selector .cpel-switcher__list .cpel-switcher__code{margin-inline-start:auto;font-size:10px;color:#999}
CSS;
}}
if(($e['widgetType']??'')==='nav-menu'&&($s['_css_classes']??'')==='tm-nav'){$s['menu_typography_font_weight']='700';$s['menu_typography_letter_spacing']=['unit'=>'px','size'=>1.1];$s['padding_horizontal_menu_item']=['unit'=>'px','size'=>10];}
if(($s['_title']??'')==='Brand + menu'){$s['flex_gap']=['unit'=>'px','size'=>24,'column'=>'24','row'=>'24','isLinked'=>true];}
if(($s['_css_classes']??'')==='tm-cta'){$s['text_padding']=['unit'=>'px','top'=>'14','right'=>'25','bottom'=>'14','left'=>'25','isLinked'=>false];$s['border_radius']=['unit'=>'px','top'=>'40','right'=>'40','bottom'=>'40','left'=>'40','isLinked'=>true];}
if($id===188&&($s['_title']??'')==='Columns'){$s['custom_css']="selector{grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) minmax(0,1.15fr) minmax(0,1.1fr) minmax(0,1.6fr);gap:32px}selector > .e-con{min-width:0}@media(max-width:1024px){selector{grid-template-columns:repeat(2,minmax(0,1fr));gap:36px}}@media(max-width:767px){selector{grid-template-columns:minmax(0,1fr);gap:32px}}";}
if($id===188&&($s['_title']??'')==='Footer body'){$s['boxed_width']=['unit'=>'px','size'=>1160];$s['padding']['left']='0';$s['padding']['right']='0';$s['custom_css']='selector{padding-inline:max(28px,calc((100vw - 1160px)/2))}selector > .e-con-inner{max-width:1160px;width:100%}';}
});
if($id!==188){$s=&$d[0]['settings'];$s['flex_gap']=['unit'=>'px','size'=>24,'column'=>'24','row'=>'24','isLinked'=>true];$s['custom_css'].="\n".<<<'CSS'
selector{padding-inline:max(28px,calc((100vw - 1160px)/2));height:76px;min-height:76px}
selector .tm-logo-light,selector .tm-logo-dark{flex:0 0 177px;width:177px}
selector .tm-logo-light img,selector .tm-logo-dark img{width:177px;height:50px;object-fit:contain}
selector .tm-lang{color:rgba(255,255,255,.75)}
selector .tm-nav{width:auto}
selector .tm-cta{width:auto}
selector .tm-cta .elementor-button{white-space:nowrap}
selector .elementor-widget-off-canvas{position:absolute;width:0;height:0}
@media(min-width:1025px) and (max-width:1240px){selector .tm-nav{display:none!important}selector .tm-burger{display:block!important}selector .elementor-widget-off-canvas{display:block!important}}
@media(max-width:767px){selector{height:68px;min-height:68px;padding-inline:20px}selector .tm-logo-light,selector .tm-logo-dark{flex-basis:150px;width:150px}selector .tm-logo-light img,selector .tm-logo-dark img{width:150px;height:43px}}
CSS;
if($id===184)$s['custom_css'].="\n.elementor-184.elementor-location-header{position:absolute;top:0;left:0;width:100%;z-index:100}\nselector .tm-logo-dark{display:none}\nselector.elementor-sticky--effects .tm-logo-dark{display:block}\nselector.elementor-sticky--effects .tm-logo-light{display:none}";
else $s['custom_css'].="\nselector .tm-lang{color:#6B6B6B}";
}
update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($d)));delete_post_meta($id,'_elementor_element_cache');\Elementor\Core\Files\CSS\Post::create($id)->delete();\Elementor\Core\Files\CSS\Post::create($id)->update();echo "Updated $id\n";
}
