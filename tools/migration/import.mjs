import fs from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import {quote} from './dump.mjs';
import {connectionPool} from '../../server/database.mjs';
export async function importData(client,data){
 if(!Array.isArray(data.tables)||data.tables.length!==22)throw Error('Expected 22 historical tables');
 await client.query('BEGIN');
 try{
  await client.query('SET CONSTRAINTS ALL DEFERRED');
  for(const t of [...data.tables].sort((a,b)=>a.name.localeCompare(b.name))){await client.query(`LOCK TABLE levelingwiki.${quote(t.name)} IN ACCESS EXCLUSIVE MODE`);const r=await client.query(`SELECT COUNT(*) AS count FROM levelingwiki.${quote(t.name)}`);if(String(r.rows[0].count)!=='0')throw Error('Import refused: destination contains historical data');}
  for(const t of data.tables){for(const row of t.rows){const params=t.columns.map(c=>row[c]);if(params.some(v=>v===undefined))throw Error('Missing import column');await client.query(`INSERT INTO levelingwiki.${quote(t.name)} (${t.columns.map(quote).join(',')}) VALUES(${params.map((_,i)=>'$'+(i+1)).join(',')})`,params);}
   const count=await client.query(`SELECT COUNT(*) AS count FROM levelingwiki.${quote(t.name)}`);if(Number(count.rows[0].count)!==t.rows.length)throw Error('Imported row count mismatch');
   for(const sequence of t.sequences??[]){const max=await client.query(`SELECT COALESCE(MAX(${quote(sequence.column)}),0)::text AS maximum FROM levelingwiki.${quote(t.name)}`);const next=BigInt(sequence.next??'1')>BigInt(max.rows[0].maximum)?BigInt(sequence.next??'1'):BigInt(max.rows[0].maximum)+1n;await client.query('SELECT setval($1::regclass,$2::bigint,false)',[`levelingwiki.${t.name}_${sequence.column}_seq`,next.toString()]);}
  }
  await client.query('SET CONSTRAINTS ALL IMMEDIATE');
  await client.query('COMMIT');
 }catch(error){await client.query('ROLLBACK');throw error;}
}
if(process.argv[1]&&import.meta.url===pathToFileURL(process.argv[1]).href){
 if(!process.argv.includes('--apply'))throw Error('Explicit --apply required; no data changed');
 const data=JSON.parse(await fs.readFile('migration-output/data.json','utf8'));const pool=connectionPool(),client=await pool.connect();try{await importData(client,data);console.log('Historical data imported atomically; identifiers and foreign keys verified.');}catch{console.error('Import refused or failed; no rows committed. Verify the destination is empty and all migrations are applied.');process.exitCode=1;}finally{client.release();await pool.end();}
}
