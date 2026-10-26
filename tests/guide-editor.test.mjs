import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../public/assets/js/guide-editor.js',import.meta.url),'utf8');
const {markdownInsertion,imageMarkdown}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
test('barre Markdown : formats et sélection conservée',()=>{
 assert.equal(markdownInsertion('bold','mot'),'**mot**');assert.equal(markdownInsertion('italic','mot'),'*mot*');
 for(const command of ['heading','bullet','number','quote','link'])assert.ok(markdownInsertion(command,'conseil').includes('conseil'));
});
test('insertion image neutralise les caractères structurels du texte alternatif',()=>{
 const md=imageMarkdown('/guide-media/test.png','capture ](javascript:evil)\n');assert.equal(md.split('\n').length,3);assert.ok(md.endsWith('](/guide-media/test.png)\n'));assert.ok(!md.includes('capture ]('));
});
import vm from 'node:vm';
function editorHarness(fetch){
 const handlers={};const feedback={textContent:''},output={innerHTML:''},area={value:'**Texte**',selectionStart:0,selectionEnd:9,addEventListener(type,fn){handlers['area-'+type]=fn;},setRangeText(text,start,end){this.value=this.value.slice(0,start)+text+this.value.slice(end);this.selectionStart=this.selectionEnd=start+text.length;},focus(){},dispatchEvent(){handlers['area-input']();}};
 const preview={addEventListener(type,fn){handlers.preview=fn;}},upload={disabled:false,addEventListener(type,fn){handlers.upload=fn;}},bold={dataset:{markdown:'bold'},addEventListener(type,fn){handlers.bold=fn;}};
 const nodes={'textarea':area,'[data-guide-feedback]':feedback,'[data-guide-output]':output,'[data-guide-preview]':preview,'[data-guide-upload]':upload,'[data-guide-file]':{files:[]},'[data-guide-alt]':{value:''}};
 const editor={closest(){return {elements:{csrf:{value:'token'},id:{value:'1'}}};},querySelector(s){return nodes[s];},querySelectorAll(){return [bold];},addEventListener(){}};
 vm.runInNewContext(source.replaceAll('export function','function'),{document:{querySelectorAll:()=>[editor]},fetch,FormData:class{set(){}},Event:class{}});
 return {handlers,feedback,output,area,upload};
}
test('aperçu affiche le rendu serveur et conserve le texte',async()=>{const ui=editorHarness(async()=>({ok:true,json:async()=>({ok:true,html:'<p><strong>Texte</strong></p>'})}));await ui.handlers.preview();assert.match(ui.output.innerHTML,/<strong>/);assert.equal(ui.area.value,'**Texte**');});
test('erreur aperçu annoncée sans perdre la rédaction',async()=>{const ui=editorHarness(async()=>{throw new Error('Hors ligne');});await ui.handlers.preview();assert.equal(ui.feedback.textContent,'Hors ligne');assert.equal(ui.area.value,'**Texte**');});
test('image manquante ou sans alternative : aucun envoi',async()=>{let calls=0;const ui=editorHarness(async()=>{calls++;});await ui.handlers.upload({currentTarget:ui.upload});assert.equal(calls,0);assert.match(ui.feedback.textContent,/texte alternatif/);});
