import test from 'node:test';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
const cwd=fileURLToPath(new URL('..',import.meta.url));
const git=(...args)=>execFileSync('git',args,{cwd,encoding:'utf8',maxBuffer:64*1024*1024});
const files=git('ls-files','-z').split('\0').filter(Boolean);
// Diagnostics contain filenames/categories only, never matched values.
const rules=[
 ['private-key',/-----BEGIN (?:RSA |EC |OPENSSH |DSA |ENCRYPTED )?PRIVATE KEY-----/],
 ['discord-webhook',/https:\/\/(?:canary\.|ptb\.)?discord(?:app)?\.com\/api\/webhooks\/\d+\/[A-Za-z0-9_-]{20,}/],
 ['provider-token',/\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}|AKIA[A-Z0-9]{16}|sk-proj-[A-Za-z0-9_-]{40,})\b/],
 ['discord-token',/\b[A-Za-z0-9_-]{24,28}\.[A-Za-z0-9_-]{6}\.[A-Za-z0-9_-]{27,110}\b/],
 ['authenticated-url',/\b(?:mysql|mariadb|postgres(?:ql)?):\/\/[^\s:@/]+:[^\s@/]+@/],
];
const scan=text=>rules.filter(([,pattern])=>pattern.test(text)).map(([category])=>category);
test('secrets : fichiers suivis et fixtures sans signature reconnue',()=>{
 const findings=[];for(const file of files){let text;try{text=readFileSync(new URL('../'+file,import.meta.url),'utf8');}catch{continue;}for(const category of scan(text))findings.push(`${file}: ${category}`);}
 assert.equal(findings.length,0,findings.join('\n'));
});
test('secrets : détecteur vérifié avec valeurs synthétiques, diagnostic expurgé',()=>{
 assert.deepEqual(scan('-----BEGIN '+'PRIVATE KEY-----'),['private-key']);
 assert.deepEqual(scan('gh'+'p_'+'A'.repeat(36)),['provider-token']);
 assert.deepEqual(scan('isolated-test-password mock-access-token test-only'),[]);
});
test('environnement : secrets du modèle laissés vides et fichiers locaux ignorés',()=>{
 const example=readFileSync(new URL('../.env.example',import.meta.url),'utf8');
 for(const line of example.split('\n'))if(/^(?:DB_PASSWORD|DB_ROOT_PASSWORD|DISCORD_CLIENT_SECRET|DISCORD_CLIENT_ID|OWNER_LOGIN_PATH)=/.test(line))assert.equal(line.split('=').slice(1).join(''),'','Valeur non vide dans .env.example');
 assert.ok(!files.includes('.env'));assert.equal(git('check-ignore','.env','config/local.php').trim().split('\n').length,2);
});
test('tests : base jetable, mots de passe fictifs, aucun chargement du .env réel',()=>{
 const compose=readFileSync(new URL('./compose.yaml',import.meta.url),'utf8');
 const runner=readFileSync(new URL('./run-admin.sh',import.meta.url),'utf8');
 assert.ok(compose.includes('name: levelingwiki-tests')&&compose.includes('APP_ENV: test')&&compose.includes('DB_DATABASE: levelingwiki_test'));
 assert.ok(compose.includes('isolated-test-password')&&!compose.includes('${DB_PASSWORD'));
 assert.ok(runner.includes('--env-file /dev/null')&&!runner.includes('--env-file .env'));
});
