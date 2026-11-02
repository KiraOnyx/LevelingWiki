document.querySelectorAll('[data-catalog-form]').forEach(form => {
  const preview = form.querySelector('[data-catalog-preview]');
  const render = () => {
    if (!preview) return;
    const fields = form.elements;
    const type = fields.type_id.selectedOptions[0];
    preview.textContent = 'Aperçu membre : ' + (fields.visibility.value === 'unknown' ? '???' : `${fields.quantity.value} ${fields.type_id.value ? type.textContent : fields.description.value}`);
  };
  form.addEventListener('input', render); form.addEventListener('reset',()=>requestAnimationFrame(render)); render();
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-catalog-form] button').forEach(b => { b.disabled = false; }));

// Fetch only the already-authorized list page. Keep the form and caret in place.
document.querySelectorAll('[data-server-filters]').forEach(form=>{
  const results=document.querySelector('[data-server-results]');
  const count=document.querySelector('[data-server-count]');
  const feedback=form.querySelector('[data-filter-feedback]');
  let timer,controller,revision=0;
  form.querySelector('[data-filter-submit]').hidden=true;
  const invalidate=()=>{clearTimeout(timer);controller?.abort();revision++;};
  const load=async()=>{
    const current=revision;controller=new AbortController();
    const url=new URL(form.getAttribute('action'),location.href);url.search=new URLSearchParams(new FormData(form)).toString();
    results.setAttribute('aria-busy','true');feedback.textContent='Recherche…';
    try{
      const response=await fetch(url,{signal:controller.signal,credentials:'same-origin',headers:{Accept:'text/html'}});
      if(!response.ok||response.redirected)throw new Error('session');
      const page=new DOMParser().parseFromString(await response.text(),'text/html');
      const next=page.querySelector('[data-server-results]'),nextCount=page.querySelector('[data-server-count]');
      if(!next||!nextCount)throw new Error('response');
      if(current!==revision)return;
      results.replaceChildren(...next.childNodes);count.textContent=nextCount.textContent;
      installPostForms(results);
      results.querySelectorAll('.catalog-cycle .check-label,.catalog-delete .check-label').forEach(label=>{label.hidden=true;label.querySelector('input').required=false;});
      history.replaceState(null,'',url.pathname+url.search);feedback.textContent=count.textContent;
    }catch(error){if(current===revision&&error.name!=='AbortError')feedback.textContent='Recherche indisponible. Réessayez ou rechargez la page pour vous reconnecter.';}
    finally{if(current===revision)results.removeAttribute('aria-busy');}
  };
  form.addEventListener('input',()=>{invalidate();timer=setTimeout(load,200);});
  form.addEventListener('change',()=>{invalidate();load();});
  form.addEventListener('submit',event=>{event.preventDefault();invalidate();load();});
  form.querySelector('[data-filter-reset]').addEventListener('click',event=>{event.preventDefault();invalidate();form.elements.q.value='';form.elements.status.value='active';form.elements.sort.value='asc';load();});
});
