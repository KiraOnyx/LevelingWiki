import {semantics,arr,slot,ref} from './semantics.mjs';
import {Database,connectionPool} from './database.mjs';
import {Session} from './session.mjs';
import {authClasses} from './auth.mjs';
import {mediaClasses} from './media.mjs';
import {createApplication} from './generated/application.mjs';
const secureHeaders={'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store','X-Content-Type-Options':'nosniff','Referrer-Policy':'same-origin','Content-Security-Policy':"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' https://cdn.discordapp.com; object-src 'none'; base-uri 'none'; frame-ancestors 'none'",'X-Frame-Options':'DENY'};
function fields(entries){const out=arr();let count=0;for(const [name,value]of entries){if(++count>2000||name.length>200)throw Error('Too many fields');const match=name.match(/^([a-zA-Z0-9_-]+)(\[\])?$/);if(!match)throw Error('Invalid field');if(match[2]){out[match[1]]??=arr();if(!out[match[1]]||typeof out[match[1]]!=='object')throw Error('Conflicting field');slot(ref(out,match[1]),null).set(value);}else{if(Object.hasOwn(out,name))throw Error('Duplicate field');out[name]=value;}}return out;}
export async function createRuntime(request,options={}){
 const url=new URL(request.url),env=options.env??process.env;
 const R=semantics({request,env,onCaught:options.onCaught,onTemplate:options.onTemplate,status:200,headers:new Headers(secureHeaders),output:[],binary:null,included:new Set(),files:new Map(),fetch:options.fetch,storageFetch:options.storageFetch,globals:{_GET:fields(url.searchParams),_POST:arr(),_FILES:arr(),_SESSION:arr(),_SERVER:arr({REQUEST_METHOD:request.method,REQUEST_URI:url.pathname+url.search,HTTP_ACCEPT:request.headers.get('accept')??'',REMOTE_ADDR:options.ip??'unknown',HTTPS:'on'})}});
 R.db=options.db??null;
 if(request.method==='POST'){
  if(request.headers.get('origin')&&request.headers.get('origin')!==url.origin)throw new HttpError(403);
  if(request.headers.get('sec-fetch-site')==='cross-site')throw new HttpError(403);
  const limit=6*1024*1024;if(Number(request.headers.get('content-length')??0)>limit)throw new HttpError(413);
  const bytes=new Uint8Array(await request.arrayBuffer());if(bytes.length>limit)throw new HttpError(413);
  const type=request.headers.get('content-type')??'';
  if(type.startsWith('application/x-www-form-urlencoded'))R.globals._POST=fields(new URLSearchParams(new TextDecoder('utf-8',{fatal:true}).decode(bytes)));
  else if(type.startsWith('multipart/form-data')){const form=await new Response(bytes,{headers:{'content-type':type}}).formData(),text=[];for(const [name,value]of form){if(typeof value==='string')text.push([name,value]);else {if(!['image','evidence'].includes(name)||R.globals._FILES[name])throw new HttpError(400);R.globals._FILES[name]=arr({name:value.name,error:value.size?0:4,bytes:Buffer.from(await value.arrayBuffer())});}}R.globals._POST=fields(text);}else throw new HttpError(415);
 }
 R.session=new Session(R);
 R.functions.consume_oauth=()=>R.session.consumeOAuth();
 Object.assign(R.native,authClasses(R),mediaClasses(R),{Database:class{static async connect(){if(!R.db)throw Error('Database unavailable');return R.db;}}});
 R.application=createApplication(R);return R;
}
export class HttpError extends Error{constructor(status){super('Request rejected');this.status=status;}}
export async function handle(request,context={},options={}){
 let client,R,db=options.db;try{
  if(new URL(request.url).pathname.startsWith('/.netlify/'))return new Response('Page introuvable.',{status:404,headers:secureHeaders});
  if(!db){client=await connectionPool(options.env).connect();db=new Database(client);}
  await db.run('BEGIN');db.requestTransaction=true;await db.run("SET LOCAL TIME ZONE 'UTC'");
  R=await createRuntime(request,{...options,db,ip:context.ip??'unknown'});
  await R.application.run();
  if(R.db.inTransaction())await R.db.rollBack();
  await R.session.persist();
  await R.db.run('COMMIT');R.db.requestTransaction=false;
  return new Response(request.method==='HEAD'?null:(R.binary??R.output.join('')),{status:R.status,headers:R.headers});
 }catch(e){if(db?.requestTransaction){await db.run('ROLLBACK').catch(()=>{});db.requestTransaction=false;db.transaction=false;}if(options.onError)options.onError(e);return new Response(e instanceof HttpError?'Requête refusée.':'Service momentanément indisponible.',{status:e instanceof HttpError?e.status:503,headers:secureHeaders});}finally{client?.release();}
}
