import crypto from 'node:crypto';
export const quote=name=>{if(!/^[a-z_][a-z0-9_]*$/.test(name))throw Error('Invalid SQL identifier');return '"'+name+'"';};
// A lexer, not a SQL executor. Quoted delimiters and MariaDB string escapes are consumed here.
export function splitSQL(text,delimiter=';'){
 const result=[];let start=0,quote=null,depth=0;
 for(let i=0;i<text.length;i++){const c=text[i];if(quote){if(c==='\\'&&quote!== '`'){i++;continue;}if(c===quote){if(text[i+1]===quote){i++;continue;}quote=null;}continue;}if(c==="'"||c==='"'||c==='`'){quote=c;continue;}if(delimiter===','){if(c==='(')depth++;if(c===')')depth--;}
 if(c===delimiter&&depth===0){result.push(text.slice(start,i).trim());start=i+1;}}
 if(quote||depth!==0)throw Error('Unterminated SQL value');if(text.slice(start).trim())result.push(text.slice(start).trim());return result;
}
export function literal(token){token=token.trim();if(token==='NULL')return null;if(/^[-+]?\d+(?:\.\d+)?$/.test(token))return token;if(token[0]!=="'"||token.at(-1)!=="'")throw Error('Unsupported dump literal');const body=token.slice(1,-1);let out='';for(let i=0;i<body.length;i++){if(body[i]==='\\'){const c=body[++i];out+=({n:'\n',r:'\r',t:'\t',0:'\0',b:'\b',Z:'\x1a'}[c]??c);}else if(body[i]==="'"&&body[i+1]==="'"){out+="'";i++;}else out+=body[i];}if(out.includes('\0'))throw Error('NUL cannot be imported into PostgreSQL text');return out;}
export function parseDump(sql){
 const tables=new Map();
 for(const match of sql.matchAll(/CREATE TABLE `([^`]+)`\s*\(([\s\S]*?)\) ENGINE=[^;]+;/g)){
  const [,name,body]=match;quote(name);const definitions=splitSQL(body,','),columns=definitions.filter(s=>s.startsWith('`')).map(s=>({name:s.match(/^`([^`]+)`/)[1],definition:s,generated:/GENERATED ALWAYS/.test(s),auto:/AUTO_INCREMENT/.test(s)}));tables.set(name,{name,definitions,columns,next:match[0].match(/AUTO_INCREMENT=(\d+)/)?.[1]??'1',rows:[]});
 }
 // Remove only full-line comments; quoted data is never rewritten.
 const clean=sql.replace(/^--[^\n]*(?:\n|$)/gm,'').replace(/^\/\*![\s\S]*?\*\/;?\s*$/gm,'');
 for(const statement of splitSQL(clean)){
  if(!/^INSERT INTO /i.test(statement))continue;
  const m=statement.match(/^INSERT INTO `([^`]+)`(?:\s*\(([^)]+)\))? VALUES\s*([\s\S]*)$/i);if(!m)throw Error('Unsupported INSERT format');const table=tables.get(m[1]);if(!table)throw Error('Unknown INSERT table');const names=m[2]?m[2].split(',').map(x=>x.trim().replace(/^`|`$/g,'')):table.columns.map(c=>c.name);
  for(const row of splitSQL(m[3],',')){if(row[0]!=='('||row.at(-1)!==')')throw Error('Invalid row');const vals=splitSQL(row.slice(1,-1),',').map(literal);if(vals.length!==names.length)throw Error('Column count mismatch');table.rows.push(Object.fromEntries(names.map((n,i)=>[n,vals[i]])));}
 }
 if(tables.size!==22)throw Error(`Expected 22 historical tables, received ${tables.size}`);
 return {tables,sha256:crypto.createHash('sha256').update(sql).digest('hex')};
}
export function schemaFor(dump){
 const definitions=[],constraints=[],indexes=[],triggers=[],enums=[];
 definitions.push(`CREATE SCHEMA levelingwiki;\nSET search_path TO levelingwiki, public;\nCREATE COLLATION historical_ci (provider=icu, locale='und-u-ks-level1', deterministic=false);`);
 definitions.push(`CREATE FUNCTION touch_updated_at() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW IS DISTINCT FROM OLD AND NEW.updated_at IS NOT DISTINCT FROM OLD.updated_at THEN NEW.updated_at = date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC'); END IF; RETURN NEW; END $$;`);
 for(const table of dump.tables.values()){
  const columns=[];
  for(let line of table.definitions){
   if(/^CONSTRAINT .* FOREIGN KEY/.test(line)){constraints.push(`ALTER TABLE ${quote(table.name)} ADD ${line.replace(/`([^`]+)`/g,'"$1"')} DEFERRABLE INITIALLY IMMEDIATE;`);continue;}
   const index=line.match(/^(UNIQUE )?KEY `([^`]+)` (.*)$/);if(index){indexes.push(`CREATE ${index[1]??''}INDEX ${quote(table.name+'_'+index[2])} ON ${quote(table.name)} ${index[3].replace(/`([^`]+)`/g,'"$1"')};`);continue;}
   const col=line.match(/^`([^`]+)`\s+(.*)$/);if(col){
    const n=col[1];line=col[2].replace(/ COMMENT '(?:[^'\\]|\\.)*'/g,'').replace(/ CHARACTER SET \w+/g,'').replace(/ COLLATE \w+/g,'').replace(/ ON UPDATE current_timestamp\(\)/gi,'').replace(/current_timestamp\(\)/gi,"(date_trunc('second', CURRENT_TIMESTAMP AT TIME ZONE 'UTC'))");
    const en=line.match(/^enum\(([^)]+)\)/);if(en){const type=table.name+'_'+n;enums.push(`CREATE TYPE ${quote(type)} AS ENUM (${en[1]});`);line=line.replace(en[0],quote(type));}
    const unsigned=/ unsigned\b/.test(line);line=line.replace(/ unsigned\b/,'');
    line=line.replace(/bigint\(\d+\)/,unsigned?'numeric(20,0)':'bigint').replace(/(?:tinyint|smallint)\(\d+\)/,unsigned?'integer':'smallint').replace(/int\(\d+\)/,unsigned?'bigint':'integer').replace(/mediumtext|longtext/,'text').replace(/\bdatetime\b/,'timestamp').replace(/\btimestamp\b/,'timestamp(0)');
    if(unsigned)line+=` CHECK (${quote(n)} >= 0 AND ${quote(n)} <= ${/^`[^`]+` bigint/.test(col[0])?'18446744073709551615':/^`[^`]+` smallint/.test(col[0])?'65535':'4294967295'})`;
    if(/AUTO_INCREMENT/.test(line)){const seq=table.name+'_'+n+'_seq';definitions.push(`CREATE SEQUENCE ${quote(seq)};`);line=line.replace('AUTO_INCREMENT',`DEFAULT nextval('levelingwiki.${seq}')`);}
    line=line.replace(/if\(`archived_at` is null,`level`,NULL\)/i,'CASE WHEN "archived_at" IS NULL THEN "level" ELSE NULL END');
    if(/json_valid/.test(line))line=line.replace(/^text/,'json').replace(/ CHECK \(json_valid\(`[^`]+`\)\)/,'');
    if(/^(?:varchar|char|text)\b/.test(line)&&!(/CHARACTER SET ascii|COLLATE .*_bin/.test(col[2])))line=line.replace(/^((?:varchar|char)(?:\(\d+\))?|text)/,'$1 COLLATE historical_ci');
    line=quote(n)+' '+line;
   }
   columns.push(line.replace(/`([^`]+)`/g,'"$1"'));
  }
  definitions.push(`CREATE TABLE ${quote(table.name)} (\n  ${columns.join(',\n  ')}\n);`);
  for(const col of table.columns.filter(c=>c.auto))definitions.push(`ALTER SEQUENCE ${quote(table.name+'_'+col.name+'_seq')} OWNED BY ${quote(table.name)}.${quote(col.name)};`);
  if(table.columns.some(c=>/ON UPDATE/.test(c.definition)))triggers.push(`CREATE TRIGGER touch_updated_at BEFORE UPDATE ON ${quote(table.name)} FOR EACH ROW EXECUTE FUNCTION touch_updated_at();`);
  triggers.push(`ALTER TABLE ${quote(table.name)} ENABLE ROW LEVEL SECURITY;`);
 }
 return ['-- Historical schema only. No private data. Apply once to an unused levelingwiki schema.','BEGIN;',definitions[0],...enums,...definitions.slice(1),...constraints,...indexes,...triggers,'REVOKE ALL ON SCHEMA levelingwiki FROM PUBLIC;','REVOKE ALL ON ALL TABLES IN SCHEMA levelingwiki FROM PUBLIC;','REVOKE ALL ON ALL SEQUENCES IN SCHEMA levelingwiki FROM PUBLIC;','COMMIT;',''].join('\n');
}
