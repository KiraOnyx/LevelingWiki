import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {PGlite} from '@electric-sql/pglite';
test('Dashboard scripts preserve 403 rows, synchronize history and refuse repeat imports',async t=>{
 try{await fs.access('migration-private/levelingwiki_mariadb.sql');}catch{t.skip('Private source unavailable');return;}
 execFileSync(process.execPath,['tools/migration/dashboard.mjs']);
 const pg=new PGlite();try{
 await pg.exec('CREATE ROLE anon; CREATE ROLE authenticated;');
 let schema=await fs.readFile('migration-output/01-dashboard-schema.sql','utf8');
 const search=(await fs.readFile('supabase/migrations/004_search.sql','utf8')).replace(/^BEGIN;\s*$/gm,'').replace(/^COMMIT;\s*$/gm,'');
 schema=schema.replace(search,"CREATE FUNCTION levelingwiki.search_text(value text) RETURNS text LANGUAGE sql IMMUTABLE AS 'SELECT value';").replace(/CREATE COLLATION historical_ci[^;]+;/,'').replaceAll(' COLLATE historical_ci','');
 await pg.exec(schema);
 assert.deepEqual((await pg.query('SELECT version FROM supabase_migrations.schema_migrations ORDER BY version')).rows.map(r=>r.version),['001','002','004']);
 const data=await fs.readFile('migration-output/02-dashboard-private-data.sql','utf8');
 await pg.exec(data);
 assert.equal((await pg.query("SELECT count(*)::int AS n FROM levelingwiki.runtime_migrations WHERE version='historical_data_once'")).rows[0].n,1);
 await assert.rejects(pg.exec(data),/already applied/);await pg.exec('ROLLBACK');
 await pg.exec('SET ROLE anon');await assert.rejects(pg.query('SELECT * FROM levelingwiki.users'),/permission denied/);
 }finally{await pg.close();}
});
