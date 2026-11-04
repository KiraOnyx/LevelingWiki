import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {fixture} from './support.mjs';
import {handle,HttpError} from '../../server/handler.mjs';
test('uploads are disabled and versioned guide illustrations remain available',async()=>{
 const f=await fixture();f.options.onError=error=>{if(!(error instanceof HttpError))throw error;};try{
 const owner=await f.session(),member=await f.session(2);
 for(const [route,field]of [['/admin/achievements/guide-image','image'],['/admin/achievements/save','evidence']]){
  const form=new FormData();form.set('csrf',owner.csrf);form.set('achievement_id','1');form.set(field,new File(['image'],'image.png',{type:'image/png'}));
  const response=await handle(new Request('https://wiki.test'+route,{method:'POST',headers:{cookie:owner.cookie},body:form}),{},f.options);
  assert.equal(response.status,422);
 }
 assert.equal((await f.request('/admin/achievements/guide-image',{session:owner,method:'POST',data:{csrf:owner.csrf,achievement_id:'1'}})).response.status,422);
 assert.equal((await f.pg.query('SELECT count(*)::int AS n FROM levelingwiki.achievement_guide_images')).rows[0].n,0);
 const edit=await f.request('/admin/achievements/1/edit',{session:owner});
 assert.match(edit.body,/type="file" disabled/);assert.match(edit.body,/data-guide-upload disabled/);
 const image='/assets/images/valhalla-hall.svg';
 await f.pg.query('INSERT INTO levelingwiki.achievement_guide_images(achievement_id,path,alt_text) VALUES(1,$1,$2)',[image,'Illustration existante']);
 await f.pg.query('UPDATE levelingwiki.achievement_details SET guide_markdown=$1 WHERE achievement_id=1',['![Illustration existante]('+image+')']);
 const page=await f.request('/succes/premier',{session:member});assert.equal(page.response.status,200);assert.ok(page.body.includes('src="'+image+'"'));
 const preview=await f.request('/admin/achievements/guide-preview',{session:owner,method:'POST',data:{csrf:owner.csrf,achievement_id:'1',markdown:'![Illustration existante]('+image+')'}});assert.equal(preview.response.status,200);assert.ok(JSON.parse(preview.body).html.includes('src="'+image+'"'));
 const files=execFileSync('git',['ls-files','-z','public/assets'],{encoding:'utf8'}).split('\0').filter(file=>/\.(svg|png|jpe?g|webp|avif|gif|ico)$/i.test(file));assert.ok(files.length);
 for(const file of files){let output;try{output=await fs.readFile('dist/'+file.slice(7));}catch{output=await fs.readFile('server/private-assets/'+file.split('/').at(-1));}assert.deepEqual(output,await fs.readFile(file),file);}
 }finally{await f.close();}
});
