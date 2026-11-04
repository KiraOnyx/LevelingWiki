import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import {compile} from './compiler/compile.mjs';
import {build} from 'esbuild';
const root=process.cwd();
const historicalCSS=JSON.parse(await fs.readFile('tools/historical-css.json','utf8'));
for(const [file,expected]of Object.entries(historicalCSS)){if(crypto.createHash('sha256').update(await fs.readFile(file)).digest('hex')!==expected)throw Error('Historical CSS changed: '+file);}
await fs.rm('dist',{recursive:true,force:true});
await fs.mkdir('dist',{recursive:true});
await fs.mkdir('server/generated',{recursive:true});
await fs.mkdir('server/private-assets',{recursive:true});
const result=await compile(root);
await fs.writeFile('server/generated/application.mjs',result.code);
const manifest={};
for(const file of (await fs.readdir('public',{recursive:true})).sort()){
 const source=path.join('public',file);if(!(await fs.lstat(source)).isFile())continue;
 if(file.startsWith('.')||file.endsWith('.php'))continue;
 if(!file.startsWith('assets/')&&file!=='manifest.webmanifest')throw Error('Unapproved public file '+file);
 const privateImage=/^assets\/images\/[^/]+\.(?:png|jpe?g|webp|avif)$/i.test(file);
 const target=privateImage?path.join('server/private-assets',path.basename(file)):path.join('dist',file);
 await fs.mkdir(path.dirname(target),{recursive:true});await fs.copyFile(source,target);
 if(file.endsWith('.css'))manifest[file]=crypto.createHash('sha256').update(await fs.readFile(target)).digest('hex');
}
await fs.writeFile('server/generated/assets.json',JSON.stringify(manifest,null,2)+'\n');
// Compile the complete function now; Netlify repeats this packaging step at publication.
await build({entryPoints:['netlify/functions/site.mjs'],bundle:true,platform:'node',target:'node22',format:'esm',packages:'external',outfile:'server/generated/function-check.mjs',logLevel:'silent'});
console.log(`Built JavaScript controllers and historical HTML; ${Object.keys(manifest).length} CSS files copied byte for byte.`);
