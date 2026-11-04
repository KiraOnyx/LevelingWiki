import pg from 'pg';import fs from 'node:fs/promises';import assert from 'node:assert/strict';
if(!process.env.TEST_DATABASE_URL)throw Error('Dedicated test database required');
const client=new pg.Client({connectionString:process.env.TEST_DATABASE_URL});await client.connect();
try{
 await client.query('BEGIN');
 if((await client.query("SELECT to_regnamespace('levelingwiki') AS existing")).rows[0].existing)throw Error('Refusing to use an existing application schema');
 await client.query('CREATE SCHEMA storage; CREATE TABLE storage.buckets(id text PRIMARY KEY,name text,public boolean,file_size_limit bigint,allowed_mime_types text[]);');
 for(const file of (await fs.readdir('supabase/migrations')).sort()){const sql=await fs.readFile('supabase/migrations/'+file,'utf8');await client.query(sql.replace(/^BEGIN;\s*$/gm,'').replace(/^COMMIT;\s*$/gm,''));}
 await client.query("INSERT INTO levelingwiki.categories(slug,name,color) VALUES('endurance','Épreuves','#123456')");
 assert.equal((await client.query("SELECT count(*)::int AS n FROM levelingwiki.categories WHERE name='epreuves'")).rows[0].n,1);
 assert.equal((await client.query("SELECT count(*)::int AS n FROM levelingwiki.categories WHERE (levelingwiki.search_text(name) COLLATE \"C\") ILIKE levelingwiki.search_text('%EPREUVES%')")).rows[0].n,1);
 assert.equal((await client.query("SELECT public FROM storage.buckets WHERE id='levelingwiki-private'")).rows[0].public,false);
 assert.equal((await client.query("SELECT count(*)::int AS n FROM pg_tables WHERE schemaname='levelingwiki' AND rowsecurity")).rows[0].n,23);
 console.log('All migrations validated on PostgreSQL, including ICU equality, accent-insensitive search, RLS and private storage.');
}finally{await client.query('ROLLBACK');await client.end();}
