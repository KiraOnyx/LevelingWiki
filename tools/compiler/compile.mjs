import Parser from 'php-parser';
import fs from 'node:fs/promises';
import path from 'node:path';
const parser=new Parser({parser:{extractDoc:false},ast:{withPositions:true}});
const native=new Set(['Auth','Database','DiscordClient','UploadService','GuideMarkdown','ValidationException','DiscordAccessException']);
const omitted=new Set(['AchievementImportService','OwnerService']);
const q=JSON.stringify;
export async function compile(root){
 let file='',cls='',serial=0; const calls=new Set();
 const name=n=>typeof n==='string'?n:n.name;
 const short=n=>name(n).split('\\').at(-1);
 const variable=n=>n.name==='this'?'this':['_GET','_POST','_SERVER','_SESSION','_FILES'].includes(n.name)?`R.globals[${q(n.name)}]`:`$[${typeof n.name==='string'?q(n.name):e(n.name)}]`;
 const key=n=>n.kind==='identifier'?q(n.name):e(n);
 function ref(n){if(n.kind==='variable')return `R.ref(${['_GET','_POST','_SERVER','_SESSION','_FILES'].includes(n.name)?'R.globals':'$'},${q(n.name)})`;if(n.kind==='offsetlookup')return `R.slot(${ref(n.what)},${n.offset?e(n.offset):'null'})`;if(n.kind==='propertylookup')return `R.ref(${e(n.what)},${key(n.offset)})`;throw Error('Invalid reference '+n.kind);}
 function put(n,v){if(n.kind==='list'){let t=`_d${serial++}`;return `(await (async()=>{const ${t}=${v};${n.items.map((it,i)=>it?put(it.value,`R.get(${t},${it.key?e(it.key):i})`)+';':'').join('')}return ${t};})())`;}return `R.set(${ref(n)},${v})`;}
 function params(n){return n.arguments.map((a,i)=>`$[${q(a.name.name)}]=R.copy(args[${i}]===undefined?${a.value?e(a.value):'null'}:args[${i}]);${a.flags?`this[${q(a.name.name)}]=$[${q(a.name.name)}];`:''}`).join('\n');}
 function fn(n,capture=false){const init=capture?'R.copy(_capture)':'Object.create(null)';return `${capture?'((_capture)=>':''}async function(...args){const $=${init};${params(n)}${n.kind==='arrowfunc'?'return '+e(n.body)+';':s(n.body)}}${capture?')($)':''}`;}
 function e(n){if(n===null||n===undefined||n===false)return 'null';switch(n.kind){
 case 'string':return q(n.value);case 'number':return n.value;case 'boolean':return String(n.value);case 'nullkeyword':return 'null';case 'variable':return variable(n);
 case 'name':return `R.constant(${q(short(n))})`;case 'identifier':return q(n.name);case 'magic':return q(n.value==='__DIR__'?path.posix.dirname(file):file);
 case 'array':return `R.array([${n.items.map(it=>it.unpack?`['spread',${e(it.value)}]`:`[${it.key?e(it.key):'null'},${e(it.value)}]`).join(',')}])`;
 case 'offsetlookup':return `R.get(${e(n.what)},${e(n.offset)})`;
 case 'propertylookup':return `${e(n.what)}[${key(n.offset)}]`;
 case 'staticlookup':return n.offset.name==='class'?q(n.what.kind==='selfreference'?cls:short(n.what)):`C[${q(n.what.kind==='selfreference'?cls:short(n.what))}][${key(n.offset)}]`;
 case 'selfreference':return `C[${q(cls)}]`;
 case 'assign':{const r=e(n.right),l=n.operator==='data'||n.operator==='='?null:e(n.left);return put(n.left,n.operator==='='?`R.copy(${r})`:n.operator==='??='?`(${l}??${r})`:n.operator===' .='?`R.str(${l})+R.str(${r})`:n.operator==='.'+'='?`R.str(${l})+R.str(${r})`:`R.bin(${q(n.operator.slice(0,-1))},${l},${r})`);}
 case 'bin':if(n.type==='??')return `(${e(n.left)}??${e(n.right)})`;if(['&&','and','||','or'].includes(n.type))return `(R.truth(${e(n.left)}) ${['&&','and'].includes(n.type)?'&&':'||'} R.truth(${e(n.right)}))`;if(n.type==='instanceof')return `R.isException(${e(n.left)},${q(short(n.right))})`;return `R.bin(${q(n.type)},${e(n.left)},${e(n.right)})`;
 case 'unary':return n.type==='!'?`(!R.truth(${e(n.what)}))`:`(${n.type}${e(n.what)})`;
 case 'retif':return n.trueExpr?`(R.truth(${e(n.test)})?${e(n.trueExpr)}:${e(n.falseExpr)})`:`(await R.or(${e(n.test)},async()=>${e(n.falseExpr)}))`;
 case 'cast':return `R.cast(${q(n.type)},${e(n.expr)})`;
 case 'empty':return `(!R.truth(${e(n.expression)}))`;
 case 'isset':return `(${n.variables.map(v=>`(${e(v)}!=null)`).join('&&')})`;
 case 'new':return `(await R.construct(C[${q(n.what.kind==='selfreference'?cls:short(n.what))}],[${n.arguments.map(e).join(',')}]))`;
 case 'call':{
   if(n.arguments.some(a=>a.kind==='variadicplaceholder'))return n.what.kind==='staticlookup'?`${e(n.what)}.bind(C[${q(n.what.what.kind==='selfreference'?cls:short(n.what.what))}])`:e(n.what);
   let target=n.what, args=n.arguments.map(e);
   if(target.kind==='name'){
     const f=short(target);calls.add(f);
     if(f==='compact')return `R.compact($,[${args.join(',')}])`;
     if(f==='extract')return `R.extract($,${args.join(',')})`;
     if(['preg_match','preg_match_all'].includes(f)&&n.arguments[2])args[2]=ref(n.arguments[2]);
     if(['sort','ksort','usort','array_pop','array_shift'].includes(f))args[0]=ref(n.arguments[0]);
     return `(await F[${q(f)}](${args.join(',')}))`;
   }
   return `(await ${e(target)}(${args.join(',')}))`;
 }
 case 'arrowfunc':case 'closure':return fn(n,true);
 case 'encapsed':return '('+n.value.map(v=>`R.str(${e(v.expression)})`).join('+')+')';
 case 'match':{let t=`_m${serial++}`;return `(await (async()=>{const ${t}=${e(n.cond)};${n.arms.filter(a=>a.conds).map(a=>`if(${a.conds.map(c=>`R.bin('===',${t},${e(c)})`).join('||')})return ${e(a.body)};`).join('')} ${n.arms.some(a=>!a.conds)?'return '+e(n.arms.find(a=>!a.conds).body)+';':"throw new Error('Unhandled match');"}})())`;}
 case 'include':return `(await R.include(T,${e(n.target)},$,${!!n.once}))`;
 case 'post':{let r=ref(n.what);return `R.post(${r},${n.type==='+'?1:-1})`;}
 case 'silent':return e(n.expr);
 default:throw Error(`Unsupported expression ${n.kind} in ${file}:${n.loc?.start.line}`);
 }}
 function s(n){if(!n)return '';switch(n.kind){
 case 'program':case 'block':case 'namespace':return (n.children??[]).map(s).join('\n');
 case 'declare':case 'usegroup':case 'commentline':case 'commentblock':return '';
 case 'expressionstatement':return e(n.expression)+';';
 case 'inline':return `R.echo(${q(n.value)});`;
 case 'echo':return n.expressions.map(v=>`R.echo(${e(v)});`).join('\n');
 case 'return':return `return ${e(n.expr)};`;
 case 'if':return `if(R.truth(${e(n.test)})){${s(n.body)}}${n.alternate?'else{'+s(n.alternate)+'}':''}`;
 case 'foreach':{const t=`_a${serial++}`,k=`_k${serial++}`;return `{const ${t}=R.iter(${e(n.source)});for(const ${k} of R.keys(${t})){${n.key?put(n.key,k)+';':''}${n.value.byref?`R.alias($,${q(n.value.name)},R.ref(${t},${k}));`:put(n.value,`R.copy(R.get(${t},${k}))`)+';'}${s(n.body)}}}`;}
 case 'unset':return n.variables.map(v=>`R.unset(${ref(v)});`).join('');
 case 'continue':return 'continue;';case 'break':return 'break;';
 case 'throw':return `throw ${e(n.what)};`;
 case 'try':return `try{${s(n.body)}}${n.catches.length?`catch(_error){R.onCaught?.(_error);${n.catches.map((c,i)=>`${i?'else ':''}if(${c.what.map(t=>`R.isException(_error,${q(short(t))})`).join('||')}){${c.variable?put(c.variable,'_error')+';':''}${s(c.body)}}`).join('')}else{throw _error;}}`:''}${n.always?'finally{'+s(n.always)+'}':''}`;
 case 'function':return `F[${q(n.name.name)}]??=${fn(n)};`;
 case 'class':{const prev=cls;cls=n.name.name;const body=n.body.map(b=>{if(b.kind==='propertystatement')return b.properties.map(p=>`${p.isStatic?'static ':''}${p.name.name}=${p.value?e(p.value):'null'};`).join('');if(b.kind==='classconstant')return b.constants.map(c=>`static ${c.name.name}=${e(c.value)};`).join('');if(b.kind==='method')return `${b.isStatic?'static ':''}async ${b.name.name}(...args){const $=Object.create(null);${params(b)}${s(b.body)}}`;throw Error('Unsupported class member '+b.kind);}).join('\n');const out=`C[${q(cls)}]=class ${cls}${n.extends?' extends C['+q(short(n.extends))+']':''}{${body}};`;cls=prev;return out;}
 default:throw Error(`Unsupported statement ${n.kind} in ${file}:${n.loc?.start.line}`);
 }}
 let classes='',templates='';
 for(const dir of ['app/Services','app/Models','app/Middleware','app/Controllers','app/Views','config','tests/fixtures']){
  for(const entry of (await fs.readdir(path.join(root,dir),{recursive:true})).filter(x=>x.endsWith('.php')).sort()){
   file=path.posix.join(dir,entry);const base=path.basename(entry,'.php');if(dir==='tests/fixtures'&&!['tower','tower-levels'].includes(base))continue;
   if(native.has(base)||omitted.has(base)||dir==='config'&&base==='database')continue;
   let source=await fs.readFile(path.join(root,file),'utf8');
   if(base==='DiscordAuthService')source=source.replace("$pending=$_SESSION['oauth'] ?? null;unset($_SESSION['oauth']);","$pending=consume_oauth();");
   if(base==='DiscordAuthService')source=source.replace("try {\n            $q=$this->pdo->prepare('SELECT u.*", "try {\n            $this->pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(?))')->execute(['discord:'.$id]);\n            $q=$this->pdo->prepare('SELECT u.*");
   const ast=parser.parseCode(source,file);
   const code=s(ast);
   if(dir==='app/Views'||dir==='config'||dir==='tests/fixtures')templates+=`T[${q(file)}]=async($)=>{${code}};\n`;
   else classes+=`// ${file}\n${code}\n`;
  }
 }
 file='public/index.php';const router=s(parser.parseCode((await fs.readFile(path.join(root,file),'utf8')).replace("require dirname(__DIR__) . '/app/bootstrap.php';",''),file));
 return {code:`// Generated from the historical controllers, services and HTML templates. Do not edit.\nexport function createApplication(R){const C={...R.native},F={...R.functions},T={};R.classes=C;R.functions=F;${classes}\n${templates}\nreturn {classes:C,templates:T,run:async()=>{const $=Object.create(null);${router}}};}\n`,calls:[...calls].sort()};
}
