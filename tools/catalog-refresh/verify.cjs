const {chromium}=require('C:/Users/ayoub/wordpress-catalog-tools/node_modules/playwright');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 const report={pages:[],errors:[]};
 for(const width of [1440,390]){
  const page=await browser.newPage({viewport:{width,height:1000}});
  page.on('pageerror',e=>report.errors.push(e.message));
  for(const slug of ['', 'shop/','ghk-cu/','tirzepatide/','product-categories/']){
   const response=await page.goto('http://localhost/wordpress/'+slug,{waitUntil:'networkidle'});
   await page.evaluate(()=>document.querySelectorAll('img').forEach(x=>x.loading='eager'));
   await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=700){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,250));}window.scrollTo(0,0)});
   await page.evaluate(()=>Promise.all([...document.images].map(x=>x.decode().catch(()=>{}))));
   await page.waitForTimeout(500);
   const state=await page.evaluate(()=>({title:document.title,h1:[...document.querySelectorAll('h1')].map(e=>e.textContent.trim()),overflow:document.documentElement.scrollWidth>innerWidth,broken:[...document.images].filter(e=>!e.complete||e.naturalWidth===0).map(e=>e.src),productCards:document.querySelectorAll('.elementor-loop-container .e-loop-item').length,body:document.body.innerText.slice(0,250)}));
   report.pages.push({slug,width,status:response.status(),...state});
   await page.screenshot({path:'C:/Users/ayoub/wordpress-catalog-backup/after-'+(slug.replaceAll('/','')||'home')+'-'+width+'.png',fullPage:true});
  }
  await page.close();
 }
 fs.writeFileSync('tools/catalog-refresh/browser-report.json',JSON.stringify(report,null,2));
 console.log(JSON.stringify(report,null,2));await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});


