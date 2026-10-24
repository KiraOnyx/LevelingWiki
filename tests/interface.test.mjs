import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../public/assets/js/interface.js',import.meta.url),'utf8');
function setup(fetch){
 let handler;const feedback={textContent:'',classList:{add(){},remove(){}}};const button={disabled:false,setAttribute(){}};
 const form={action:{value:'obtained'},dataset:{},addEventListener(type,fn){handler=fn;},hasAttribute(name){return name==='data-inline-progress';},getAttribute(){return '/progression';},setAttribute(){},removeAttribute(){},closest(){return null;},querySelector(s){return s==='[data-undo-confirm]'?null:s==='button'?button:s==='.form-feedback'?feedback:{value:'obtained'};},querySelectorAll(){return [button];}};
 vm.runInNewContext(source,{document:{querySelectorAll:s=>s==='form[method="post"]'?[form]:[],querySelector:()=>null,dispatchEvent(){}},window:{addEventListener(){}},FormData:class{get(){return 'obtained';}},CustomEvent:class{},confirm:()=>true,setTimeout,fetch});
 return {submit:()=>handler({preventDefault(){},submitter:button}),feedback,button,form};
}
test('progression utilise URL du formulaire malgré le champ action et bloque les doublons',async()=>{
 let complete;let calls=0;const ui=setup(async url=>{assert.equal(url,'/progression');calls++;await new Promise(r=>complete=r);return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,progress:'obtained'})};});
 const first=ui.submit();await ui.submit();assert.equal(calls,1);assert.equal(ui.button.disabled,true);complete();await first;assert.equal(ui.feedback.textContent,'Progression enregistrée.');assert.equal(ui.button.disabled,false);
});
test('erreur réseau laisse une nouvelle tentative possible sans annoncer un succès',async()=>{
 let calls=0;const ui=setup(async()=>{calls++;throw new Error('offline');});await ui.submit();assert.match(ui.feedback.textContent,/non confirmé/);assert.equal(ui.button.disabled,false);await ui.submit();assert.equal(calls,2);
});
function setupQuick(fetch){
 const events={};const select={value:'published',disabled:false,addEventListener(t,fn){events[t]=fn;}};
 const previous={value:'draft'},row={dataset:{publication:'draft'},hidden:false},feedback={textContent:'',classList:{add(){},remove(){}}},stat={dataset:{adminStat:'1'},textContent:'0'};
 const form={action:{value:'quick'},querySelector(s){return s==='select'?select:s==='[name="previous"]'?previous:{hidden:false};},getAttribute(){return '/admin/achievements/save';},setAttribute(){},removeAttribute(){},closest(){return row;},addEventListener(){}};
 vm.runInNewContext(source,{document:{querySelectorAll:s=>s==='[data-quick-status]'?[form]:s==='[data-admin-stat]'?[stat]:[],querySelector:s=>s==='[data-quick-feedback]'?feedback:null,dispatchEvent(){}},FormData:class{},CustomEvent:class{},fetch,TypeError,window:{},setTimeout});
 return {change:()=>events.change(),select,previous,row,feedback,stat};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
test('édition rapide : une requête, URL non masquée, compteurs et état confirmés',async()=>{
 let calls=0,complete;const ui=setupQuick(async url=>{assert.equal(url,'/admin/achievements/save');calls++;await new Promise(r=>complete=r);return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,value:'published',field:'publication_status',stats:[1,1]})};});
 ui.change();ui.change();await tick();assert.equal(calls,1);assert.equal(ui.select.disabled,true);complete();await tick();assert.equal(ui.previous.value,'published');assert.equal(ui.row.dataset.publication,'published');assert.equal(ui.stat.textContent,1);assert.equal(ui.select.disabled,false);
});
test('édition rapide : erreur réseau restaure la sélection sans modifier les compteurs',async()=>{
 const ui=setupQuick(async()=>{throw new TypeError('offline');});ui.change();await tick();assert.equal(ui.select.value,'draft');assert.equal(ui.stat.textContent,'0');assert.equal(ui.select.disabled,false);assert.match(ui.feedback.textContent,/non confirmé/);
});
test('édition rapide : validation serveur et expiration ne laissent pas de faux succès',async()=>{
 for(const response of [{ok:false,headers:{get:()=> 'application/json'},json:async()=>({ok:false,message:'Statut invalide'})},{ok:true,headers:{get:()=> 'text/html'}}]){
  const ui=setupQuick(async()=>response);ui.change();await tick();assert.equal(ui.select.value,'draft');assert.equal(ui.previous.value,'draft');assert.equal(ui.select.disabled,false);assert.doesNotMatch(ui.feedback.textContent,/enregistré/);
 }
});
