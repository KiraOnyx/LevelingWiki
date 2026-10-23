import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../public/assets/js/interface.js',import.meta.url),'utf8');
function setup(fetch){
 let handler;const feedback={textContent:'',classList:{add(){},remove(){}}};const button={disabled:false,setAttribute(){}};
 const form={action:{value:'obtained'},dataset:{},addEventListener(type,fn){handler=fn;},hasAttribute(){return true;},getAttribute(){return '/progression';},setAttribute(){},removeAttribute(){},closest(){return null;},querySelector(s){return s==='[data-undo-confirm]'?null:s==='button'?button:s==='.form-feedback'?feedback:{value:'obtained'};},querySelectorAll(){return [button];}};
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
