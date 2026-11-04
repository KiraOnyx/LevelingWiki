import crypto from 'node:crypto';
import sharp from 'sharp';
import fs from 'node:fs/promises';
import path from 'node:path';
import {unified} from 'unified';
import remarkParse from 'remark-parse';
import remarkGfm from 'remark-gfm';
import remarkRehype from 'remark-rehype';
import rehypeStringify from 'rehype-stringify';
import {ValidationException,values} from './semantics.mjs';
const mediaPath=/^\/(guide-media|media)\/[a-f0-9]{64}\.(jpg|png|webp)$/;
export function mediaClasses(R){
 const download=async url=>{if(R.files.has(url))return url;const response=await storage(R,'GET',url);if(response.status===404)return null;if(!response.ok)throw Error('Storage unavailable');R.files.set(url,Buffer.from(await response.arrayBuffer()));return url;};
 Object.assign(R.functions,{
  realpath:async file=>{if(file==='public/assets/images')return file;if(!/^public\/assets\/images\/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|avif)$/.test(file))return false;try{const bytes=await fs.readFile(path.join(process.cwd(),'server/private-assets',path.basename(file)));R.files.set(file,bytes);return file;}catch{return false;}},is_link:()=>false,filesize:file=>R.files.get(file)?.length??0,readfile:file=>{const bytes=R.files.get(file);if(!bytes)throw Error('Unknown media');R.binary=bytes;}
 });
 return {UploadService:class{
  async __construct(guide=false){this.guide=guide;}
  async store(file){if(!file||file.error===4)return null;if(file.error!==0||!file.bytes)throw new ValidationException('Téléversement invalide ou trop volumineux.');const max=Number(R.env[this.guide?'GUIDE_IMAGE_MAX_BYTES':'UPLOAD_MAX_BYTES']??5242880);if(!file.bytes.length||file.bytes.length>max)throw new ValidationException('La capture dépasse la taille maximale autorisée.');let metadata;try{metadata=await sharp(file.bytes,{limitInputPixels:Number(R.env.GUIDE_IMAGE_MAX_PIXELS??20000000)}).metadata();}catch{throw new ValidationException('La capture doit être une image JPEG, PNG ou WebP valide (20 mégapixels maximum).');}const ext={jpeg:'jpg',png:'png',webp:'webp'}[metadata.format];if(!ext||metadata.pages>1||!metadata.width||!metadata.height||metadata.width*metadata.height>20000000||!(ext==='jpg'?['.jpg','.jpeg']:['.'+ext]).includes(path.extname(file.name).toLowerCase()))throw new ValidationException('La capture doit être une image JPEG, PNG ou WebP valide (20 mégapixels maximum).');const url=`/${this.guide?'guide-media':'media'}/${crypto.randomBytes(32).toString('hex')}.${ext}`;const response=await storage(R,'POST',url,file.bytes,metadata.format==='jpeg'?'image/jpeg':'image/'+ext);if(!response.ok)throw Error('Storage unavailable');return url;}
  async resolve(url){if(!mediaPath.test(url)||url.startsWith('/guide-media/')!==!!this.guide)return null;return download(url);}
  async remove(url){if(url&&mediaPath.test(url)){try{const response=await storage(R,'DELETE',url);if(!response.ok&&response.status!==404)throw Error();}catch{/* A failed cleanup never makes a private object public. */}}}
 },GuideMarkdown:class{static async render(markdown,images){markdown=markdown.replace(/\r\n?/g,'\n').replace(/^\n+|\n+$/g,'');const allowed=new Set(values(images??{}));const processor=unified().use(remarkParse).use(remarkGfm,{singleTilde:false}).use(()=>tree=>{const walk=(node,parent)=>{if(node.type==='listItem'&&node.checked!=null){const first=node.children[0];if(first?.type==='paragraph')first.children.unshift({type:'text',value:node.checked?'[x] ':'[ ] '});node.checked=null;}if(node.type==='html'){if(['root','blockquote','listItem'].includes(parent?.type)){node.children=[{type:'text',value:node.value}];node.type='paragraph';delete node.value;}else node.type='text';}if(node.type==='heading')node.depth=Math.min(6,Math.max(3,node.depth));if(node.children){node.children.forEach(child=>walk(child,node));node.children=node.children.flatMap(child=>child.type==='text'&&!markdown.slice(child.position?.start?.offset??0,child.position?.end?.offset??0).includes('\\')?historicalEmphasis(child):[child]);if(node.type==='paragraph'&&node.position){const tail=markdown.slice(node.children.at(-1)?.position?.end?.offset??node.position.end.offset,node.position.end.offset);if(/^ +$/.test(tail))node.children.push({type:'text',value:tail});}}};walk(tree);}).use(remarkRehype).use(()=>tree=>{const walk=node=>{if(node.type==='element'&&node.tagName==='a'){let valid=false;try{const u=new URL(node.properties.href);valid=['http:','https:'].includes(u.protocol)&&!u.username&&!u.password&&!/[\x00-\x20\\]/.test(node.properties.href);}catch{}if(!valid)delete node.properties.href;node.properties.rel=['noopener','noreferrer','nofollow'];}if(node.type==='element'&&node.tagName==='img'){if(!allowed.has(node.properties.src)){node.tagName='span';node.properties={};node.children=[{type:'text',value:'[Image non disponible]'}];}else{node.properties.alt=String(node.properties.alt??'').trim()||'Illustration du guide';node.properties.loading='lazy';}}node.children?.forEach(walk);};walk(tree);}).use(rehypeStringify,{closeSelfClosing:true});const tree=await processor.run(processor.parse(markdown));return serializeGuide(tree).trim();}}};
}
async function storage(R,method,url,body,type){const endpoint=R.env.SUPABASE_URL,key=R.env.SUPABASE_SERVICE_ROLE_KEY;if(!endpoint||!key||new URL(endpoint).protocol!=='https:')throw Error('Private storage is not configured');const bucket=R.env.SUPABASE_STORAGE_BUCKET??'levelingwiki-private';if(!/^[a-z0-9-]+$/.test(bucket))throw Error('Invalid storage bucket');return (R.storageFetch??fetch)(`${endpoint.replace(/\/$/,'')}/storage/v1/object/${bucket}${url}`,{method,headers:{Authorization:`Bearer ${key}`,apikey:key,...(type?{'Content-Type':type}:{})},body,redirect:'error',signal:AbortSignal.timeout(15000)});}

function serializeGuide(node,parent=''){
 const escape=value=>String(value).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
 if(node.type==='text')return escape(parent==='code'?node.value.replace(/\n$/,''):node.value);
 if(node.type==='root')return node.children.map(n=>serializeGuide(n)).join('');
 if(node.type!=='element')return '';
 let attributes='';for(let [key,value]of Object.entries(node.properties??{})){if(value==null||value===false)continue;if(key==='className')key='class';if(key==='align'){key='style';value='text-align: '+value+';';}if(Array.isArray(value))value=value.join(' ');attributes+=' '+key+'="'+escape(value).replace(/"/g,'&quot;').replace(/'/g,'&#039;')+'"';}
 const name=node.tagName;if(['img','hr','br','input'].includes(name))return '<'+name+attributes+' />';return '<'+name+attributes+'>'+node.children.map(n=>serializeGuide(n,name)).join('')+'</'+name+'>';
}

function historicalEmphasis(node){
 const match=/\*\*([^*]+)\*\*|__([^_]+)__|\*([^*]+)\*/.exec(node.value);if(!match)return [node];
 const value=match[1]??match[2]??match[3],children=[{type:match[3]===undefined?'strong':'emphasis',children:historicalEmphasis({type:'text',value})}];
 if(match.index)children.unshift({type:'text',value:node.value.slice(0,match.index)});const tail=node.value.slice(match.index+match[0].length);if(tail)children.push(...historicalEmphasis({type:'text',value:tail}));return children;
}
