// Generates private, manually applied Dashboard scripts. Never connects to a database.
import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import {parseDump,quote} from './dump.mjs';
const literal=value=>value===null?'NULL':"'"+String(value).replaceAll("'","''")+"'";
const digest=text=>crypto.createHash('sha256').update(text).digest('hex');
const dump=parseDump(await fs.readFile('migration-private/levelingwiki_mariadb.sql','utf8'));
const tables=[...dump.tables.values()];
if(tables.reduce((n,t)=>n+t.rows.length,0)!==403)throw Error('Expected 403 source rows');
const start="BEGIN;\nSET LOCAL standard_conforming_strings=on;\nSET LOCAL TIME ZONE 'UTC';\nSELECT pg_advisory_xact_lock(hashtext('levelingwiki_schema_migration'));\n";
let schema=start+`DO $$ BEGIN IF to_regnamespace('levelingwiki') IS NOT NULL THEN RAISE EXCEPTION 'Existing levelingwiki schema: inspect before proceeding'; END IF; END $$;
CREATE SCHEMA IF NOT EXISTS supabase_migrations;
CREATE TABLE IF NOT EXISTS supabase_migrations.schema_migrations(version text PRIMARY KEY, statements text[], name text);
ALTER TABLE supabase_migrations.schema_migrations ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON supabase_migrations.schema_migrations FROM PUBLIC, anon, authenticated;
REVOKE ALL ON SCHEMA supabase_migrations FROM PUBLIC, anon, authenticated;
`;
const migrations=(await fs.readdir('supabase/migrations')).filter(f=>/^\d+_[a-z_]+\.sql$/.test(f)).sort();
for(const file of migrations){
 const sql=await fs.readFile('supabase/migrations/'+file,'utf8');
 schema+=sql.replace(/^BEGIN;\s*$/gm,'').replace(/^COMMIT;\s*$/gm,'')+'\n';
 if(file.startsWith('001_'))schema+=`CREATE TABLE levelingwiki.runtime_migrations(version text PRIMARY KEY,checksum char(64) NOT NULL,applied_at timestamptz NOT NULL DEFAULT now());
ALTER TABLE levelingwiki.runtime_migrations ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON levelingwiki.runtime_migrations FROM PUBLIC, anon, authenticated;\n`;
 schema+=`INSERT INTO levelingwiki.runtime_migrations(version,checksum) VALUES(${literal(file)},${literal(digest(sql))});\n`;
 const [version,...name]=file.replace(/\.sql$/,'').split('_');
 schema+=`INSERT INTO supabase_migrations.schema_migrations(version,name,statements) VALUES(${literal(version)},${literal(name.join('_'))},ARRAY[${literal(sql)}]);\n`;
}
schema+=`REVOKE ALL ON ALL SEQUENCES IN SCHEMA levelingwiki FROM anon, authenticated;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA levelingwiki FROM PUBLIC, anon, authenticated;
COMMIT;
SELECT version,name FROM supabase_migrations.schema_migrations ORDER BY version;
`;
let data=start+`SET CONSTRAINTS ALL DEFERRED;
DO $$ BEGIN IF EXISTS(SELECT 1 FROM levelingwiki.runtime_migrations WHERE version='historical_data_once') THEN RAISE EXCEPTION 'Historical import already applied'; END IF; END $$;
`;
for(const t of [...tables].sort((a,b)=>a.name.localeCompare(b.name))){const table='levelingwiki.'+quote(t.name);data+=`LOCK TABLE ${table} IN ACCESS EXCLUSIVE MODE;
DO $$ BEGIN IF EXISTS(SELECT 1 FROM ${table}) THEN RAISE EXCEPTION 'Import refused: nonempty table ${t.name}'; END IF; END $$;\n`;}
for(const t of tables){
 const table='levelingwiki.'+quote(t.name),columns=t.columns.filter(c=>!c.generated).map(c=>c.name);
 for(const row of t.rows){if(columns.some(c=>row[c]===undefined))throw Error('Missing column');data+=`INSERT INTO ${table} (${columns.map(quote)}) VALUES (${columns.map(c=>literal(row[c]))});\n`;}
 for(const c of t.columns.filter(c=>c.auto)){const next=t.rows.reduce((max,row)=>BigInt(row[c.name])+1n>max?BigInt(row[c.name])+1n:max,BigInt(t.next));data+=`SELECT setval(${literal('levelingwiki.'+t.name+'_'+c.name+'_seq')}::regclass,${next},false);\n`;}
 data+=`DO $$ BEGIN IF (SELECT count(*) FROM ${table}) <> ${t.rows.length} THEN RAISE EXCEPTION 'Row count mismatch'; END IF; END $$;\n`;
 // Exact text comparisons avoid case/accent-insensitive historical collations.
 for(const row of t.rows)data+=`DO $verify$ BEGIN IF NOT EXISTS(SELECT 1 FROM ${table} WHERE ${columns.map(c=>`${quote(c)}::text COLLATE "C" IS NOT DISTINCT FROM ${literal(row[c])}::text COLLATE "C"`).join(' AND ')}) THEN RAISE EXCEPTION 'Imported value mismatch in ${t.name}'; END IF; END $verify$;\n`;
}
data+=`SET CONSTRAINTS ALL IMMEDIATE;
INSERT INTO levelingwiki.runtime_migrations(version,checksum) VALUES('historical_data_once',${literal(dump.sha256)});
COMMIT;
SELECT 22 AS historical_tables,403 AS verified_historical_rows;
`;
await fs.mkdir('migration-output',{recursive:true,mode:0o700});
for(const [file,sql]of [['01-dashboard-schema.sql',schema],['02-dashboard-private-data.sql',data]]){await fs.writeFile('migration-output/'+file,sql,{mode:0o600});await fs.chmod('migration-output/'+file,0o600);}
console.log('Prepared Dashboard schema and one-time private import: 22 tables, 403 rows. No remote operation.');
