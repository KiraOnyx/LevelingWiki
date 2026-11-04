import {PGlite} from '@electric-sql/pglite';
import fs from 'node:fs/promises';
import bcrypt from 'bcryptjs';
import {Database} from '../../server/database.mjs';
import {arr} from '../../server/semantics.mjs';
import {createRuntime,handle} from '../../server/handler.mjs';
export async function fixture(){
 const pg=new PGlite({parsers:{1114:v=>v,1184:v=>v,114:v=>v,3802:v=>v}});await pg.exec((await fs.readFile('supabase/migrations/001_historical.sql','utf8')).replace(/CREATE COLLATION historical_ci[^;]+;/,'').replaceAll(' COLLATE historical_ci',''));await pg.exec(await fs.readFile('supabase/migrations/002_runtime.sql','utf8'));await pg.exec("CREATE FUNCTION levelingwiki.search_text(value text) RETURNS text LANGUAGE sql IMMUTABLE AS 'SELECT value';");
 await pg.query(`INSERT INTO levelingwiki.users(id,username,password_hash,role,auth_provider) VALUES(1,'owner',$1,'super_admin','local'),(2,'discord_member','unused','member','discord'),(3,'discord_editor','unused','editor','discord'),(4,'discord_admin','unused','admin','discord')`,[await bcrypt.hash('test-only-owner-password',12)]);
 await pg.exec(`INSERT INTO levelingwiki.authorization_policy(id,version) VALUES(1,1);
 INSERT INTO levelingwiki.discord_identities(user_id,discord_user_id,display_name) VALUES(2,'123456789012345672','Membre'),(3,'123456789012345673','Éditeur'),(4,'123456789012345674','Admin');
 INSERT INTO levelingwiki.discord_roles(id,level,discord_role_id,label) VALUES(1,'member','223456789012345672','Membre'),(2,'editor','223456789012345673','Éditeur'),(3,'admin','223456789012345674','Admin');
 INSERT INTO levelingwiki.categories(id,slug,name,color) VALUES(1,'vert','Régularité et séries','#70A47A');
 INSERT INTO levelingwiki.titles(id,slug,name) VALUES(1,'titre','Titre');
 INSERT INTO levelingwiki.achievements(id,slug,name,category_id,secret_type,publication_status,reward_xp,reward_gold,reward_gems,reward_keys,title_rewards_known) VALUES(1,'premier','Premier succès',1,'normal','published',10,0,NULL,2,1),(2,'secret','Succès secret',1,'secret','published',20,NULL,1,0,0),(3,'brouillon','Brouillon confidentiel',1,'normal','draft',NULL,NULL,NULL,NULL,0);
 INSERT INTO levelingwiki.achievement_details(achievement_id,unlock_conditions,guide_markdown) VALUES(1,'Une condition','# Guide\n\nUn **guide**.'),(2,'Condition secrète',NULL);
 INSERT INTO levelingwiki.achievement_titles(achievement_id,title_id) VALUES(1,1);
 INSERT INTO levelingwiki.guild_divisions(id,name,short_name,description,color,level) VALUES(1,'Division','DIV','Description','#B6A2FF',2);
 INSERT INTO levelingwiki.guild_division_masters(division_id,user_id,assigned_by) VALUES(1,3,1);
 INSERT INTO levelingwiki.guild_milestones(id,level,title,description) VALUES(1,1,'Palier','Description');
 INSERT INTO levelingwiki.guild_reward_types(id,slug,name,short_name,description,category) VALUES(1,'or','Or','Or','Pièces','personal');
 INSERT INTO levelingwiki.guild_milestone_rewards(id,milestone_id,type_id,quantity,distribution,description,visibility) VALUES(1,1,1,5,'individual','Récompense','known'),(2,1,1,999,'collective','SECRET-REWARD','unknown');
 `);
 for(const table of ['users','discord_roles','categories','titles','achievements','guild_divisions','guild_milestones','guild_reward_types','guild_milestone_rewards'])await pg.query(`SELECT setval('levelingwiki.${table}_id_seq',100,false)`);
 const db=new Database(pg),env={SESSION_SECRET:'a'.repeat(64),APP_ENV:'test',DISCORD_CLIENT_ID:'123456789012345678',DISCORD_GUILD_ID:'123456789012345679',DISCORD_CLIENT_SECRET:'test-only-client-secret',DISCORD_REDIRECT_URI:'https://wiki.test/auth/discord/callback',OWNER_LOGIN_PATH:'/sanctuaire/'+'a'.repeat(64)};
 const options={db,env,onError:e=>{throw e;}};
 async function session(id=1){const R=await createRuntime(new Request('https://wiki.test/'),options);await R.session.start();const roles={2:'member',3:'editor',4:'admin'},now=Math.floor(Date.now()/1000);R.globals._SESSION=arr({user_id:id,session_version:1,signed_in:now,last_seen:now,csrf:'test-csrf',...(id!==1?{discord:arr({member:arr({roles:arr(['22345678901234567'+id]),pending:false}),checked:now,version:1,role:roles[id],guild:env.DISCORD_GUILD_ID,client:env.DISCORD_CLIENT_ID,tokens:arr({access:'test-access',refresh:'test-refresh',expires:now+86400})})}:{})});await R.session.persist();return {cookie:R.headers.get('set-cookie').split(';')[0],csrf:'test-csrf'};}
 async function request(route,{session:s,method='GET',data,headers={}}={}){const response=await handle(new Request('https://wiki.test'+route,{method,headers:{...(s?{cookie:s.cookie}:{}),...(data?{'content-type':'application/x-www-form-urlencoded'}:{}),...headers},body:data?new URLSearchParams(data):undefined}),{ip:'192.0.2.1'},options);return {response,body:await response.text()};}
 return {pg,db,env,options,session,request,close:()=>pg.close()};
}
