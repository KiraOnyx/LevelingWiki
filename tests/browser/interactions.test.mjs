import test from 'node:test';import assert from 'node:assert/strict';import fs from 'node:fs/promises';import path from 'node:path';import {chromium} from '@playwright/test';import {fixture} from '../node/support.mjs';import {handle} from '../../server/handler.mjs';
test('historical scripts support filtering, progression, mobile layout and administration',async()=>{const f=await fixture();const launch={headless:true};if(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE)launch.executablePath=process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE;const browser=await chromium.launch(launch);try{await f.pg.query("INSERT INTO levelingwiki.achievement_guide_images(achievement_id,path,alt_text) VALUES(1,'/assets/images/valhalla-hall.svg','Illustration existante')");await f.pg.query("UPDATE levelingwiki.achievement_details SET guide_markdown='![Illustration existante](/assets/images/valhalla-hall.svg)' WHERE achievement_id=1");const session=await f.session(2);const context=await browser.newContext({viewport:{width:1440,height:1000}});await context.addCookies([{name:'lw_session',value:session.cookie.split('=')[1],domain:'wiki.test',path:'/',secure:true,httpOnly:true,sameSite:'Lax'}]);const errors=[],assetErrors=[];
await context.route('https://wiki.test/**',async route=>{const req=route.request(),url=new URL(req.url());if(url.pathname.startsWith('/assets/')||url.pathname==='/manifest.webmanifest'){const file=path.join('dist',url.pathname);try{const body=await fs.readFile(file);const ext=path.extname(file);await route.fulfill({status:200,body,contentType:({'.css':'text/css','.js':'text/javascript','.mjs':'text/javascript','.svg':'image/svg+xml','.png':'image/png','.webp':'image/webp','.webmanifest':'application/manifest+json'})[ext]??'application/octet-stream'});}catch{await route.fulfill({status:404});}return;}
const response=await handle(new Request(req.url(),{method:req.method(),headers:await req.allHeaders(),body:req.postDataBuffer()??undefined}),{ip:'192.0.2.1'},f.options);await route.fulfill({status:response.status,headers:Object.fromEntries(response.headers),body:Buffer.from(await response.arrayBuffer())});});
const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));page.on('response',response=>{if(new URL(response.url()).pathname.startsWith('/assets/')&&response.status()>=400)assetErrors.push(new URL(response.url()).pathname);});await page.goto('https://wiki.test/succes');await page.waitForSelector('[data-card="premier"]');await page.locator('#search').fill('Premier');await page.waitForFunction(()=>document.querySelector('[data-count]').textContent==='1');assert.equal(await page.locator('[data-card]:visible').count(),1);await page.locator('#search').fill('');await page.waitForFunction(()=>document.querySelector('[data-count]').textContent==='2');await page.locator('[data-card="premier"] [data-inline-progress] button').click();await page.waitForFunction(()=>document.querySelector('[data-card="premier"]')?.classList.contains('is-obtained'));await page.locator('[data-collection-tab="obtained"]').click();assert.equal(await page.locator('[data-card]:visible').count(),1);
await page.setViewportSize({width:390,height:844});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1));await page.goto('https://wiki.test/tour-de-guilde');await page.waitForSelector('[data-tower-prototype]');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1));
await fs.mkdir('test-results',{recursive:true});
for(const [label,width,height]of [['desktop',1440,1000],['mobile',390,844]]){
 await page.setViewportSize({width,height});
 for(const route of ['/','/profil','/succes','/succes/premier','/tour-de-guilde']){
  const response=await page.goto('https://wiki.test'+route);assert.equal(response.status(),200,route);if(route==='/succes/premier')await page.waitForFunction(()=>{const img=document.querySelector('img[alt="Illustration existante"]');return img?.complete&&img.naturalWidth>0;});if(route==='/admin/achievements/1/edit'){assert.equal(await page.locator('input[type=file]:enabled').count(),0);assert.ok(await page.locator('[data-guide-upload]').isDisabled());}
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),label+' '+route);
  assert.doesNotMatch(await page.content(),/test-only-client-secret|test-only-storage-key|discord-secret-access|discord-secret-refresh/);
 }
 await page.goto('https://wiki.test/succes');await page.screenshot({path:'test-results/catalog-'+label+'.png',fullPage:true});
}
const owner=await f.session();await context.addCookies([{name:'lw_session',value:owner.cookie.split('=')[1],domain:'wiki.test',path:'/',secure:true,httpOnly:true,sameSite:'Lax'}]);await page.goto('https://wiki.test/admin/achievements');await page.waitForSelector('[data-filter-row]');await fs.mkdir('test-results',{recursive:true});await page.screenshot({path:'test-results/admin-mobile.png',fullPage:true});for(const [label,width,height]of [['desktop',1440,1000],['mobile',390,844]]){
 await page.setViewportSize({width,height});
 for(const route of ['/admin','/admin/achievements','/admin/achievements/1/edit','/admin/settings/discord','/admin/tour-de-guilde/paliers?id=1']){
  const response=await page.goto('https://wiki.test'+route);assert.equal(response.status(),200,route);if(route==='/succes/premier')await page.waitForFunction(()=>{const img=document.querySelector('img[alt="Illustration existante"]');return img?.complete&&img.naturalWidth>0;});if(route==='/admin/achievements/1/edit'){assert.equal(await page.locator('input[type=file]:enabled').count(),0);assert.ok(await page.locator('[data-guide-upload]').isDisabled());}
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),label+' '+route);
 }
 await page.goto('https://wiki.test/admin');await page.screenshot({path:'test-results/dashboard-'+label+'.png',fullPage:true});
}
await context.clearCookies();
for(const [label,width,height]of [['desktop',1440,1000],['mobile',390,844]]){
 await page.setViewportSize({width,height});await page.goto('https://wiki.test/connexion');
 assert.equal(await page.locator('input[type=password]').count(),0);
 assert.ok(!(await page.content()).includes(f.env.OWNER_LOGIN_PATH));
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1));
 await page.screenshot({path:'test-results/login-'+label+'.png',fullPage:true});
 const missing=await page.goto('https://wiki.test/validation-page-absente');assert.equal(missing.status(),404);
}
assert.deepEqual(errors,[]);assert.deepEqual(assetErrors,[]);await context.close();
}finally{await browser.close();await f.close();}});
