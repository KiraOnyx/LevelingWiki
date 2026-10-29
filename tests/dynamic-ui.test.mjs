import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../public/assets/js/app.js',import.meta.url),'utf8').replace(/^import .*\n/,'');
test('progression : totaux serveur sans reload, précision des récompenses et catégories',()=>{
 let listener;const obtained={dataset:{summary:'obtained'},textContent:'0'},percent={},progress={},completion={},reward={dataset:{estimate:'xp'},textContent:'0'},note={},categoryCount={},categoryProgress={},categoryFill={setAttribute(k,v){this[k]=v;}},detail={};
 const category={dataset:{categoryProgress:'1'},querySelector:s=>s==='strong'?categoryCount:s==='[data-category-fill]'?categoryFill:categoryProgress};
 const groups={'[data-summary]':[obtained],'[data-summary-percent]':[percent],'[data-summary-progress]':[progress],'[data-completion]':[completion],'[data-estimate]':[reward],'[data-category-progress]':[category],'[data-detail-state]':[detail]};
 const document={querySelector:s=>s==='#catalog-data'?{textContent:'[{"id":1,"slug":"test"}]'}:s==='[data-estimate-note="xp"]'?note:null,querySelectorAll:s=>groups[s]||[],addEventListener:(type,fn)=>listener=fn};
 vm.runInNewContext(source,{document,Set,JSON,Number,String,Array});
 listener({detail:{achievement_id:1,progress:'obtained',obtained:['test'],summary:{obtained:1,total:2,remaining:1,percent:50},rewards:{xp:{known:'18446744073709551615',unknown:1}},categories:{1:{obtained:1,total:2,percent:50}}}});
 assert.equal(obtained.textContent,1);assert.equal(percent.textContent,'50 %');assert.equal(progress.value,50);assert.equal(completion.hidden,true);assert.equal(reward.textContent,'18446744073709551615');assert.match(note.textContent,/Partiel/);assert.equal(categoryCount.textContent,'1 / 2 · 50 %');assert.equal(categoryProgress.value,1);assert.equal(categoryFill.width,50);assert.equal(detail.textContent,'Marqué comme obtenu');
 listener({detail:{achievement_id:1,progress:'not_started',obtained:[],summary:{obtained:0,total:2,remaining:2,percent:0},rewards:{xp:{known:'0',unknown:0}},categories:{1:{obtained:0,total:2}}}});
 assert.equal(obtained.textContent,0);assert.equal(reward.textContent,'0');assert.equal(note.textContent,'Valeurs documentées');assert.equal(detail.textContent,'Non obtenu');
});
