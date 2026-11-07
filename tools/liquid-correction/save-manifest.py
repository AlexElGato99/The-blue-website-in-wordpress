import json
from pathlib import Path
root=Path('tools/liquid-correction')
read=lambda p:json.loads(Path(p).read_text(encoding='utf-8-sig'))
products=read(root/'products.json');master=read(root/'master.json');scenes=[read(p) for p in sorted((root/'scenes').glob('*.json'))];plan=read(root/'replacement-plan.json')
for a in products+scenes:
 a['website_file']='C:/xampp/htdocs/wordpress/wp-content/uploads/catalog-liquid-2026/'+a['slug']+'.webp'
 a['attachment_id']=plan['new'][a['slug']]
manifest={'method':'Built-in image editing for liquid master and supporting scenes; shared photo master with native vector label typesetting for pixel-identical product geometry.','master':master,'product_dimensions':[1600,1600],'products':products,'scenes':scenes}
(root/'assets.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print('Saved full image manifest and prompts')
