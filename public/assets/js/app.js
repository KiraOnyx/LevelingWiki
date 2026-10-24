import { filterItems } from './catalog-core.mjs?v=6';
const items=JSON.parse(document.querySelector('#catalog-data')?.textContent||'[]');
const account=JSON.parse(document.querySelector('#account-progress')?.textContent||'null');
const obtained=new Set(account?.obtained||[]);
const form=document.querySelector('#catalog-filters');
const search=document.querySelector('#search');
const personal=!!document.querySelector('[data-personal-dashboard]');
let timer;
function updateResults(){
    if(!form)return;
    const scrollPosition={x:window.scrollX,y:window.scrollY};
    const data=new FormData(form);const filters={...Object.fromEntries(data),rewards:data.getAll('reward')};
    const results=filterItems(items,filters,obtained);const visible=new Set(results.map(i=>i.slug));
    const grid=document.querySelector('[data-grid]');const cards=new Map([...grid.children].map(card=>[card.dataset.card,card]));
    for(const [slug,card] of cards)card.hidden=!visible.has(slug);
    for(const item of results)grid.append(cards.get(item.slug));
    document.querySelector('[data-count]').textContent=results.length;
    document.querySelector('[data-results]').textContent=results.length+' résultat'+(results.length===1?'':'s');
    document.querySelector('[data-empty]').hidden=!!results.length;
    document.querySelector('[data-empty-title]').textContent=personal&&filters.progress==='obtained'&&!obtained.size?'Votre légende commence ici.':!items.length?'Aucun succès publié pour le moment.':'Aucun exploit ne correspond à votre recherche.';
    document.querySelector('[data-empty-description]').textContent=personal&&filters.progress==='obtained'&&!obtained.size?'Explorez le Codex pour découvrir vos premiers exploits.':'Ajustez vos filtres ou explorez une autre catégorie.';
    document.querySelector('[data-reset]').hidden=!items.length;
    document.querySelectorAll('[data-collection-tab]').forEach(tab=>{if(tab.dataset.collectionTab===filters.progress)tab.setAttribute('aria-current','page');else tab.removeAttribute('aria-current');});
    const params=new URLSearchParams();for(const [key,value] of data)if(value&&!(key==='sort'&&value==='name-asc'))params.append(key,value);
    history.replaceState(null,'',location.pathname+(params.size?'?'+params:'')+location.hash);
    // Keep the current reading position, allowing natural clamping on shorter results.
    window.scrollTo({left:scrollPosition.x,top:Math.min(scrollPosition.y,Math.max(0,document.documentElement.scrollHeight-window.innerHeight)),behavior:'instant'});
}
if(form){
    form.hidden=false;const params=new URLSearchParams(location.search);
    for(const field of form.elements){if(!field.name)continue;if(field.type==='checkbox')field.checked=params.getAll(field.name).includes(field.value);else if(params.has(field.name))field.value=params.get(field.name);}
    if(personal)form.elements.progress.value=params.get('progress')==='remaining'?'remaining':'obtained';
    const panel=document.querySelector('.filters-panel'),container=document.querySelector('.catalogue');
    let compact;
    const adaptFilters=()=>{const next=container.clientWidth<650;if(next!==compact){panel.open=!next;compact=next;}};
    adaptFilters();new ResizeObserver(adaptFilters).observe(container);
    document.querySelectorAll('[data-collection-tab]').forEach(tab=>tab.addEventListener('click',event=>{event.preventDefault();form.elements.progress.value=tab.dataset.collectionTab;updateResults();}));
    search?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(updateResults,250);});
    form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);updateResults();});
    form.addEventListener('change',()=>{clearTimeout(timer);updateResults();});
    form.addEventListener('reset',()=>{clearTimeout(timer);if(search)search.value='';setTimeout(()=>{if(personal)form.elements.progress.value='obtained';updateResults();},0);});
    document.querySelector('[data-reset]').addEventListener('click',()=>{form.reset();search?.focus();});
    updateResults();
}
document.addEventListener('progress-saved',event=>{
    const item=items.find(i=>Number(i.id)===event.detail.achievement_id);if(!item)return;
    event.detail.progress==='obtained'?obtained.add(item.slug):obtained.delete(item.slug);
    if(Array.isArray(event.detail.obtained)){obtained.clear();event.detail.obtained.forEach(slug=>obtained.add(slug));}
    const {summary,rewards,categories}=event.detail;
    if(summary){
        document.querySelectorAll('[data-summary]').forEach(el=>el.textContent=summary[el.dataset.summary]);
        document.querySelectorAll('[data-summary-percent]').forEach(el=>el.textContent=String(summary.percent).replace('.',',')+' %');
        document.querySelectorAll('[data-summary-progress]').forEach(el=>el.value=summary.percent);
        document.querySelectorAll('[data-completion]').forEach(el=>el.hidden=!(summary.total>0&&summary.remaining===0));
    }
    if(rewards)document.querySelectorAll('[data-estimate]').forEach(el=>{const reward=rewards[el.dataset.estimate];el.textContent=reward.known;const note=document.querySelector('[data-estimate-note="'+el.dataset.estimate+'"]');if(note)note.textContent=Number(reward.unknown)>0?'Partiel · '+reward.unknown+' récompense(s) inconnue(s)':'Valeurs documentées';});
    if(categories)document.querySelectorAll('[data-category-progress]').forEach(el=>{const category=categories[el.dataset.categoryProgress];if(category){el.querySelector('strong').textContent=category.obtained+' / '+category.total;el.querySelector('progress').value=category.obtained;}});
    const activeCard=document.activeElement?.closest('[data-card]');
    document.querySelectorAll('[data-detail-state]').forEach(el=>el.textContent=event.detail.progress==='obtained'?'Marqué comme obtenu':'Non obtenu');
    updateResults();
    if(activeCard?.hidden)document.querySelector('[data-collection-tab][aria-current="page"], .filters-panel summary')?.focus({preventScroll:true});
});
