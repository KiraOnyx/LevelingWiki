import pg from 'pg';
import {databaseTLS} from './database-tls.mjs';
import {arr,values,PDOException} from './semantics.mjs';
let pool;
export function connectionPool(env=process.env){if(!pool){if(!env.DATABASE_URL)throw Error('DATABASE_URL is required');const u=new URL(env.DATABASE_URL);if(!['postgres:','postgresql:'].includes(u.protocol))throw Error('Invalid database URL');for(const key of ['sslmode','sslcert','sslkey','sslrootcert'])u.searchParams.delete(key);pool=new pg.Pool({connectionString:u.href,max:3,idleTimeoutMillis:20000,connectionTimeoutMillis:8000,ssl:databaseTLS(),types:{getTypeParser:oid=>[20,1700,1114,1184,114,3802].includes(oid)?v=>v:pg.types.getTypeParser(oid)}});}return pool;}
export function postgresSQL(sql){
 sql=sql.replace(/(\b(?:\w+\.)?\w+) LIKE \? ESCAPE/gi,'(levelingwiki.search_text($1) COLLATE "C") ILIKE levelingwiki.search_text(?) ESCAPE');
 sql=sql.replace(/LOCK IN SHARE MODE/gi,'FOR SHARE').replace(/CAST\((\w+) AS CHAR\)/gi,'CAST($1 AS TEXT)');
 // Known historical aggregate expressions: PostgreSQL does not SUM booleans.
 sql=sql.replace(/SUM\((a\.reward_\w+ IS NULL(?: AND (?:p\.progress='obtained'|\(p\.progress IS NULL OR p\.progress<>'obtained'\)))?)\)/g,'SUM(($1)::integer)');
 sql=sql.replace(/IF\(l\.action='personal.obtained',' a déclaré : ',' a annulé : '\)/g,"CASE WHEN l.action='personal.obtained' THEN ' a déclaré : ' ELSE ' a annulé : ' END");
 if(/ON DUPLICATE KEY UPDATE/i.test(sql)){
  const table=sql.match(/INSERT INTO\s+(\w+)/i)?.[1];const conflict={achievement_details:'achievement_id',personal_achievements:'user_id,achievement_id'}[table];if(!conflict)throw Error('Unsupported upsert');
  sql=sql.replace(/ON DUPLICATE KEY UPDATE/i,`ON CONFLICT (${conflict}) DO UPDATE SET`).replace(/VALUES\((\w+)\)/gi,'EXCLUDED.$1');
 }
 let index=0,quoted=false,out='';for(let i=0;i<sql.length;i++){const c=sql[i];if(c==="'"){out+=c;if(quoted&&sql[i+1]==="'"){out+=sql[++i];continue;}quoted=!quoted;}else out+=c==='?'&&!quoted?'$'+(++index):c;}
 return out;
}
function rowValue(name,value){if(value instanceof Date)return value.toISOString().slice(0,19).replace('T',' ');if(typeof value==='string'&&/^-?\d+$/.test(value)&&!/^reward_|^quantity$|discord_.*_id$|^password_hash$|^bucket$|^checksum$/.test(name))return Number.isSafeInteger(Number(value))?Number(value):BigInt(value);return value;}
export class Database{
 constructor(client){this.client=client;this.transaction=false;this.requestTransaction=false;this.lastId=null;}
 async run(sql,params=[]){try{sql=sql.replace(/\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+(achievement_details|achievement_guide_images|achievement_titles|achievements|audit_logs|authorization_policy|categories|discord_identities|discord_roles|guild_catalog_initializations|guild_division_levels|guild_division_masters|guild_divisions|guild_milestone_rewards|guild_milestones|guild_reward_types|login_limits|personal_achievements_legacy|personal_achievements|schema_migrations|titles|users|web_sessions)\b/gi,'$1 levelingwiki.$2');return await this.client.query(sql,params.map(v=>typeof v==='bigint'?v.toString():v));}catch(e){throw new PDOException(e);}}
 prepare(sql){return new Statement(this,sql);}
 async query(sql){const statement=this.prepare(sql);await statement.execute();return statement;}
 async exec(sql){const r=await this.run(postgresSQL(sql));return r.rowCount;}
 async beginTransaction(){if(this.transaction)throw Error('Nested transaction');await this.run(this.requestTransaction?'SAVEPOINT business_write':'BEGIN');this.transaction=true;}
 async commit(){await this.run(this.requestTransaction?'RELEASE SAVEPOINT business_write':'COMMIT');this.transaction=false;}
 async rollBack(){await this.run(this.requestTransaction?'ROLLBACK TO SAVEPOINT business_write':'ROLLBACK');this.transaction=false;}
 inTransaction(){return this.transaction;}
 lastInsertId(){return this.lastId;}
}
class Statement{
 constructor(db,sql){this.db=db;this.sql=sql;this.params=[];this.rows=[];this.cursor=0;}
 bindValue(index,value){this.params[index-1]=value;}
 async execute(params){let sql=postgresSQL(this.sql);const insert=sql.match(/^\s*INSERT INTO\s+(\w+)/i);const identity=insert&&['achievements','achievement_guide_images','audit_logs','categories','discord_roles','guild_division_levels','guild_divisions','guild_milestone_rewards','guild_milestones','guild_reward_types','personal_achievements','personal_achievements_legacy','titles','users'].includes(insert[1]);if(identity)sql+=' RETURNING id';const result=await this.db.run(sql,params?values(params):this.params);this.rows=result.rows.map(r=>arr(Object.fromEntries(Object.entries(r).map(([k,v])=>[k,rowValue(k,v)]))));this.cursor=0;if(identity&&this.rows[0])this.db.lastId=this.rows[0].id;return true;}
 fetch(){return this.rows[this.cursor++]??false;}
 fetchColumn(){const row=this.fetch();return row===false?false:values(row)[0];}
 fetchAll(mode){const rows=this.rows.slice(this.cursor);this.cursor=this.rows.length;return arr(mode==='column'?rows.map(r=>values(r)[0]):rows);}
 // Compiled foreach obtains the same remaining result rows as PDO's iterator.
 historicalRows(){return this.fetchAll();}
}
