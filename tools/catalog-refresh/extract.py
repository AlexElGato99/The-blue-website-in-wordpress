import fitz, json, re
from pathlib import Path

root=Path(__file__).parent
doc=fitz.open('D:/Downloads1/Telegram Desktop/Price List.pdf')
groups=[
('MT-1','MT1'),('MT-2 (Melanotan 2 Acetate)','ML10'),('PT-141','P41'),('Gonadorelin Acetate','GND2'),('DSIP','DS5 DS10'),('Selank','SK5 SK10'),('Oxytocin Acetate','OT2 OT5'),('Epithalon','ET10 ET50'),('BPC-157','BC5 BC10'),('BPC-157 + TB-500','BB10 BB20'),('AICAR','AR50'),('Adipotide','AP5'),('Semax','XA5 XA10'),('Adamax','AX10'),('Semaglutide','SM5 SM10 SM15 SM20 SM30'),('SS-31','2S10 2S50'),('Tirzepatide','TR5 TR10 TR15 TR20 TR30 TR40 TR50 TR60 TR70 TR100 TR120'),('GHRP-2 Acetate','G25 G210 G215'),('GHRP-6 Acetate','G65 G610'),('CJC-1295 (no DAC)','CND2 CND5 CND10'),('CJC-1295 (no DAC) + Ipamorelin','CP10'),('CJC-1295 With DAC','CD2 CD5'),('TB-500','BT2 BT5 BT10'),('MGF','FM2'),('PEG MGF','FMP2'),('Sermorelin Acetate','SMO5 SMO10'),('HCG','G5K G10K'),('AOD9604','2AD 5AD'),('IGF-1 LR3','IG01 IG1'),('Tesamorelin','TSM2 TSM5 TSM10 TSM20'),('Ipamorelin','IP5 IP10'),('Hexarelin Acetate','HX5'),('GHK-Cu','CU50 CU100'),('AHK-Cu','AK100'),('Kisspeptin-10','KS5 KS10'),('Thymalin','TY10'),('Thymosin Alpha-1','TA5 TA10'),('MOTS-c','MS10 MS20 MS40'),('FOXO4','F410'),('LL37','375'),('Retatrutide','RT5 RT10 RT15 RT20 RT30 RT40 RT50 RT60 RT100'),('Melatonin','MT10'),('Glutathione','GTT600 GTT1500'),('5-amino-1mq','5AM 50AM'),('HMG','G75'),('SNAP-8','NP810'),('NAD+','NJ100 NJ500 NJ1000'),('Survodutide','SUR10'),('Cagrilintide','CGL5 CGL10'),('TB-500 + BPC-157 + GHK-Cu','BBG70'),('BT10 + BP10 + CU50 + KP10','Klow80'),('Cagrilintide + Semaglutide','CS10'),('KPV','KP10'),('VIP','VP10'),('Dihexa','DI10'),('Sterile Water','WA10 WA3'),('Benzyl Alcohol 0.9%','BA10 BA3'),('Acetic Acid 0.6%','AA10 AA3')]
by_sku={s:n for n,ss in groups for s in ss.split()}
rows={}
for page in list(doc)[:5]:
    words=page.get_text('words')
    skus=[w for w in words if w[0]<125 and w[4] in by_sku]
    for w in skus:
        vals=[v for v in words if v[0]>350 and abs(v[1]-w[1])<7]
        spec=''.join(v[4] for v in sorted(vals,key=lambda x:x[0]) if v[0]<455)
        price=''.join(v[4] for v in sorted(vals,key=lambda x:x[0]) if v[0]>=455)
        assert spec and price, (w,vals)
        rows[w[4]]={'sku':w[4],'specification':spec,'pack_price':float(price),'page':page.number+1}
assert set(rows)==set(by_sku),(set(by_sku)-set(rows))
catalog=[]
for name,ss in groups:
    slug=re.sub('[^a-z0-9]+','-',name.lower()).strip('-')
    if name=='NAD+': slug='nad-plus'
    catalog.append({'name':name,'slug':slug,'variants':[rows[s] for s in ss.split()]})
(root/'catalog.json').write_text(json.dumps(catalog,indent=2),encoding='utf-8')
print(f'{len(catalog)} products, {len(rows)} variants')
for i in range(5): doc[i].get_pixmap(matrix=fitz.Matrix(1,1)).save(str(root/f'page-{i+1}.png'))

