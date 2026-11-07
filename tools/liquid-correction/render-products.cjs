const {chromium}=require('C:/Users/ayoub/wordpress-catalog-tools/node_modules/playwright');const fs=require('fs');
const products=JSON.parse(fs.readFileSync('tools/catalog-refresh/catalog.json','utf8').replace(/^\uFEFF/,''));
(async()=>{
 const b=await chromium.launch({channel:'msedge',headless:true});
 try{
 const p=await b.newPage({viewport:{width:1600,height:1600},deviceScaleFactor:1});
 await p.goto('http://localhost/wordpress/',{waitUntil:'domcontentloaded'});
 await p.setContent('<html><head><style>html,body{margin:0;width:1600px;height:1600px;overflow:hidden}img,svg{position:absolute;inset:0;width:1600px;height:1600px}text{font-family:Arial,sans-serif;font-weight:700;fill:#0F2A1E}</style></head><body><img id="bottle" src="http://localhost/wordpress/wp-content/uploads/catalog-liquid-2026/bottle-master.png"><svg viewBox="0 0 1600 1600"><g id="name"></g></svg></body></html>',{waitUntil:'domcontentloaded'});
 await p.locator('#bottle').evaluate(x=>x.decode());const manifest=[];
 for(const product of products){
  const layout=await p.evaluate(name=>{
   const ctx=document.createElement('canvas').getContext('2d');
   const wrap=size=>{ctx.font='700 '+size+'px Arial';let lines=[],line='';for(const word of name.split(/\s+/)){const t=line?line+' '+word:word;if(line&&ctx.measureText(t).width>490){lines.push(line);line=word}else line=t;}if(line)lines.push(line);return lines;};
   let size=110,lines;for(;size>=40;size-=2){lines=wrap(size);ctx.font='700 '+size+'px Arial';if(lines.length<=3&&lines.length*size*1.08<=210&&lines.every(t=>ctx.measureText(t).width<=490))break;}
   const g=document.querySelector('#name');g.replaceChildren();lines.forEach((line,i)=>{const t=document.createElementNS('http://www.w3.org/2000/svg','text');for(const [k,v] of Object.entries({x:800,y:980+(i-(lines.length-1)/2)*size*1.08,'font-size':size,'text-anchor':'middle','dominant-baseline':'central'}))t.setAttribute(k,String(v));t.textContent=line;g.appendChild(t)});return {fontSize:size,lines,bottleImage:{x:0,y:0,width:1600,height:1600},labelBox:{x:545,y:865,width:510,height:230}};
  },product.name);
  const file='D:/wordpress-liquid-correction/'+product.slug+'.png';await p.screenshot({path:file,type:'png'});manifest.push({slug:product.slug,name:product.name,path:file,width:1600,height:1600,master:'D:/wordpress-liquid-correction/bottle-master.png',...layout});
 }
 fs.writeFileSync('tools/liquid-correction/products.json',JSON.stringify(manifest,null,2));console.log('Rendered '+manifest.length+' standardized 1600x1600 product images.');
 }finally{await b.close()}
})().catch(e=>{console.error(e);process.exit(1)});
