import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import {connectionPool} from '../../server/database.mjs';
if(!process.argv.includes('--apply'))throw Error('Explicit --apply required; no database changed');
const pool=connectionPool(),client=await pool.connect();
try{
 await client.query('BEGIN');
 await client.query("SELECT pg_advisory_xact_lock(hashtext('levelingwiki_schema_migration'))");
 const exists=(await client.query("SELECT to_regnamespace('levelingwiki') IS NOT NULL AS present")).rows[0].present;
 if(exists){const ledger=(await client.query("SELECT to_regclass('levelingwiki.runtime_migrations') IS NOT NULL AS present")).rows[0].present;if(!ledger)throw Error('Existing schema has no migration ledger');}
 for(const file of (await fs.readdir('supabase/migrations')).filter(f=>/^\d+_[a-z_]+\.sql$/.test(f)).sort()){
  const sql=await fs.readFile('supabase/migrations/'+file,'utf8'),checksum=crypto.createHash('sha256').update(sql).digest('hex');
  if(exists){const applied=await client.query('SELECT checksum FROM levelingwiki.runtime_migrations WHERE version=$1',[file]);if(applied.rows.length){if(applied.rows[0].checksum!==checksum)throw Error('Migration checksum mismatch');continue;}}
  await client.query(sql.replace(/^BEGIN;\s*$/gm,'').replace(/^COMMIT;\s*$/gm,''));
  if(file.startsWith('001_'))await client.query('CREATE TABLE levelingwiki.runtime_migrations(version text PRIMARY KEY,checksum char(64) NOT NULL,applied_at timestamptz NOT NULL DEFAULT now()); ALTER TABLE levelingwiki.runtime_migrations ENABLE ROW LEVEL SECURITY; REVOKE ALL ON levelingwiki.runtime_migrations FROM PUBLIC;');
  await client.query('INSERT INTO levelingwiki.runtime_migrations(version,checksum) VALUES($1,$2)',[file,checksum]);
 }
 await client.query('COMMIT');console.log('PostgreSQL migrations applied. No historical data replaced.');
}catch{await client.query('ROLLBACK');console.error('Migration refused or failed; transaction rolled back. Verify the target schema and migration versions.');process.exitCode=1;}finally{client.release();await pool.end();}
