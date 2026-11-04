import fs from 'node:fs/promises';import path from 'node:path';import crypto from 'node:crypto';import {execFileSync} from 'node:child_process';
const tracked=execFileSync('git',['ls-files','-z'],{encoding:'utf8'}).split('\0').filter(Boolean);
for(const file of tracked)if(/^(migration-private|migration-output|storage|dist|node_modules)\//.test(file)||/^\.env(?:\.|$)/.test(file)&&file!=='.env.example')throw Error('Private or generated file tracked: '+file);
const pinned=JSON.parse(await fs.readFile('tools/historical-css.json','utf8'));
const historical=Object.keys(pinned);
for(const file of historical){const current=await fs.readFile(file);if(crypto.createHash('sha256').update(current).digest('hex')!==pinned[file])throw Error('Historical CSS modified: '+file);const published=await fs.readFile(path.join('dist',file.slice(7)));if(!current.equals(published))throw Error('Published CSS differs: '+file);}
for(const file of await fs.readdir('dist',{recursive:true})){const p=path.join('dist',file);if(!(await fs.stat(p)).isFile())continue;if(!file.startsWith('assets/')&&file!=='manifest.webmanifest')throw Error('Unexpected public artifact');if(/\.(?:php|sql|env|jsonl?)$/i.test(file))throw Error('Private format in public output');}
const functionCode=await fs.readFile('server/generated/function-check.mjs','utf8');if(/@php-wasm|php-parser|migration-private|migration-output|child_process|\bexecSync\b/.test(functionCode))throw Error('Build-only dependency in function');
console.log(`Release boundary verified; ${historical.length} historical CSS files unchanged; private exports excluded.`);
