import test from 'node:test';import assert from 'node:assert/strict';
import {fixture} from './support.mjs';
import {postgresSQL} from '../../server/database.mjs';
test('dashboard activity excludes personal events through a deterministic PostgreSQL search',async()=>{
 const f=await fixture();try{
 await f.pg.query("INSERT INTO levelingwiki.audit_logs(action,entity_type) VALUES('achievement.update','achievement'),('personal.obtained','achievement')");
 const sql=postgresSQL("SELECT action FROM audit_logs WHERE action NOT LIKE 'personal.%'");
 assert.match(sql,/COLLATE "C"\) NOT ILIKE/);
 const rows=await f.db.run(sql);assert.deepEqual(rows.rows.map(r=>r.action),['achievement.update']);
 const caught=[];f.options.onCaught=e=>caught.push(e);const session=await f.session();
 const page=await f.request('/admin',{session});assert.equal(page.response.status,200);assert.match(page.body,/achievement.update/);assert.equal(caught.length,0);
 }finally{await f.close();}
});
