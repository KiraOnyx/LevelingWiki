import test from 'node:test';import assert from 'node:assert/strict';import {fixture} from './support.mjs';
test('Netlify owner secret overrides historical password without changing stored hash and revokes old sessions',async()=>{
 const f=await fixture();try{
 const before=(await f.pg.query('SELECT password_hash FROM levelingwiki.users WHERE id=1')).rows[0].password_hash;
 const old=await f.session();f.env.OWNER_PASSWORD='test-only-new-owner-secret';
 assert.equal((await f.request('/admin',{session:old})).response.status,303);
 let page=await f.request(f.env.OWNER_LOGIN_PATH);let session={cookie:page.response.headers.get('set-cookie').split(';')[0]};const csrf=page.body.match(/name="csrf" value="([^"]+)"/)[1];
 const login=password=>f.request(f.env.OWNER_LOGIN_PATH,{session,method:'POST',data:{csrf,identifier:'owner',password}});
 assert.equal((await login('test-only-owner-password')).response.status,401);
 const logged=await login(f.env.OWNER_PASSWORD);assert.equal(logged.response.status,303);
 session={cookie:logged.response.headers.get('set-cookie').split(';')[0]};page=await f.request('/admin',{session});assert.equal(page.response.status,200);assert.ok(!page.body.includes(f.env.OWNER_PASSWORD));
 assert.equal((await f.pg.query('SELECT password_hash FROM levelingwiki.users WHERE id=1')).rows[0].password_hash,before);
 f.env.OWNER_PASSWORD='test-only-rotated-owner-secret';assert.equal((await f.request('/admin',{session})).response.status,303);
 }finally{await f.close();}
});
