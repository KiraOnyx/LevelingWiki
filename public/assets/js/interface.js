// Shared, keyboard-accessible confirmation; text is never interpreted as HTML.
function confirmAction(message) {
    return new Promise(resolve => {
        const previous=document.activeElement, dialog=document.createElement('dialog');
        dialog.className='confirmation-dialog';dialog.setAttribute('aria-label','Confirmer cette action');
        const heading=document.createElement('h2');heading.textContent='Confirmer cette action';
        const text=document.createElement('p');text.textContent=message;
        const actions=document.createElement('div');actions.className='dialog-actions';
        const cancel=document.createElement('button');cancel.className='secondary-button';cancel.textContent='Conserver';
        const accept=document.createElement('button');accept.className='primary-button';accept.textContent='Confirmer';
        const finish=value=>{dialog.close();dialog.remove();previous?.focus();resolve(value);};
        cancel.addEventListener('click',()=>finish(false));accept.addEventListener('click',()=>finish(true));
        dialog.addEventListener('cancel',event=>{event.preventDefault();finish(false);});
        actions.append(cancel,accept);dialog.append(heading,text,actions);document.body.append(dialog);dialog.showModal();cancel.focus();
    });
}
document.querySelectorAll('form[method="post"]').forEach(form => {
    if(form.hasAttribute('data-quick-status'))return;
    let busy=false;
    const unlock=()=>{busy=false;form.removeAttribute('aria-busy');form.querySelectorAll('button').forEach(button=>button.disabled=false);};
    window.addEventListener('pageshow',unlock);
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;busy=true;
        const submitter=event.submitter, data=new FormData(form);
        const action=data.get('action')||submitter?.value;
        const inline=form.hasAttribute('data-inline-progress');
        if((action==='delete'||(inline&&action==='not_started')) && !await confirmAction(action==='delete'?'Supprimer définitivement cet élément ?':'Annuler votre déclaration d’obtention ? Le succès restera consultable dans le Codex.')) {unlock();return;}
        if ((form.dataset.confirm || action==='archive' || data.get('archived')==='1') && !await confirmAction(form.dataset.confirm || 'Archiver cet élément ? Il sera retiré des listes actives ; ses références seront conservées.')) {unlock();return;}
        form.setAttribute('aria-busy','true');
        if(!inline){
            // Native submission after confirmation; preserve clicked button name/value.
            if(submitter?.name){const field=document.createElement('input');field.type='hidden';field.name=submitter.name;field.value=submitter.value;form.append(field);}
            form.querySelectorAll('button').forEach(button=>button.disabled=true);
            HTMLFormElement.prototype.submit.call(form);return;
        }
        if(action==='not_started')data.set('confirm','1');
        const feedback=form.querySelector('.form-feedback');feedback.classList.remove('error');feedback.textContent='Enregistrement…';
        form.querySelectorAll('button').forEach(button=>button.disabled=true);
        try{
            const response=await fetch(form.getAttribute('action'),{method:'POST',body:data,headers:{Accept:'application/json'},credentials:'same-origin'});
            if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))throw new Error();
            const result=await response.json();if(!result.ok)throw new Error();
            form.dataset.saved=result.progress;const done=result.progress==='obtained';form.querySelector('[name="action"]').value=done?'not_started':'obtained';
            const button=form.querySelector('button');button.textContent=done?'Annuler la déclaration':'Marquer comme obtenu';button.setAttribute('aria-label',button.textContent);
            const state=form.querySelector('[data-progress-state]');if(state)state.hidden=!done;
            const card=form.closest('[data-card]');
            if(card){
                const date=card.querySelector('[data-declaration]');
                if(date){date.hidden=!done;if(done&&result.obtained_at){const time=date.querySelector('time');time.dateTime=result.obtained_at.replace(' ','T')+'Z';time.textContent=new Date(time.dateTime).toLocaleString('fr-FR',{timeZone:'UTC'})+' UTC';}}
                card.classList.remove('progress-confirmed');requestAnimationFrame(()=>card.classList.add('progress-confirmed'));
            }
            const tracking=form.closest('.codex-tracking');
            if(tracking){const date=tracking.querySelector('[data-declaration]');if(date){date.hidden=!done;if(done&&result.obtained_at){const time=date.querySelector('time');time.dateTime=result.obtained_at.replace(' ','T')+'Z';time.textContent=new Date(time.dateTime).toLocaleString('fr-FR',{timeZone:'UTC'})+' UTC';}}}
            if(card){card.classList.toggle('is-obtained',done);const badge=card.querySelector('[data-personal-badge]');if(badge){badge.textContent=done?'✓ Obtenu':'';badge.hidden=!done;}}
            feedback.textContent='Progression enregistrée.';document.dispatchEvent(new CustomEvent('progress-saved',{detail:result}));
        }catch{feedback.classList.add('error');feedback.textContent='Enregistrement non confirmé. Vérifiez votre connexion ou reconnectez-vous, puis réessayez.';}
        finally{unlock();}
    });
    if(form.hasAttribute('data-inline-progress')){const confirmation=form.querySelector('[data-undo-confirm]');if(confirmation){confirmation.hidden=true;confirmation.querySelector('input').required=false;}}
});
document.querySelectorAll('.admin-nav a').forEach(link=>{if(new URL(link.href).pathname===location.pathname)link.setAttribute('aria-current','page');});
const context=document.querySelector('[data-context-filters]');
if(context){
    const rows=[...document.querySelectorAll('[data-filter-row]')];
    const incoming=new URLSearchParams(location.search);for(const field of context.elements)if(field.name&&incoming.has(field.name))field.value=incoming.get(field.name);
    const filter=()=>{const data=new FormData(context);let count=0;rows.forEach(row=>{row.hidden=[...data].some(([key,value])=>value&&(key==='q'?!row.textContent.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().includes(String(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()):row.dataset[key]!==value));if(!row.hidden)count++;});document.querySelector('[data-context-count]').textContent=count+' résultat'+(count===1?'':'s');};
    let searchTimer;context.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(filter,250);});context.addEventListener('change',filter);document.addEventListener('achievement-status-saved',filter);context.addEventListener('reset',()=>setTimeout(filter,0));context.addEventListener('submit',event=>event.preventDefault());
}

// Serialize quick edits: counter snapshots and filtered rows stay in response order.
let quickQueue=Promise.resolve();
document.querySelectorAll('[data-quick-status]').forEach(form=>{
    const select=form.querySelector('select'), previous=form.querySelector('[name="previous"]');
    form.querySelector('[data-quick-submit]').hidden=true;
    let busy=false;
    const save=()=>{
        if(busy||select.value===previous.value)return;
        busy=true;
        const data=new FormData(form);
        select.disabled=true;form.setAttribute('aria-busy','true');
        const feedback=document.querySelector('[data-quick-feedback]');
        feedback.textContent='Enregistrement…';feedback.classList.remove('error');
        quickQueue=quickQueue.then(async()=>{
            try{
                const response=await fetch(form.getAttribute('action'),{method:'POST',body:data,headers:{Accept:'application/json'},credentials:'same-origin'});
                if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('Session expirée ou accès refusé. Rechargez la page pour vérifier vos accès.');
                const result=await response.json();
                if(!response.ok||!result.ok)throw new Error(result.message||'Modification refusée. Rechargez la page.');
                previous.value=result.value;select.value=result.value;
                if(result.field==='publication_status')form.closest('[data-filter-row]').dataset.publication=result.value;
                document.querySelectorAll('[data-admin-stat]').forEach(el=>el.textContent=result.stats[Number(el.dataset.adminStat)]);
                document.dispatchEvent(new CustomEvent('achievement-status-saved'));
                feedback.textContent='Statut enregistré. Les résultats et compteurs sont à jour.';
                if(form.closest('[data-filter-row]').hidden)context.querySelector('select')?.focus({preventScroll:true});
            }catch(error){
                select.value=previous.value;feedback.classList.add('error');
                feedback.textContent=error instanceof TypeError?'Enregistrement non confirmé. Vérifiez votre connexion puis rechargez la liste avant de réessayer.':error.message;
            }finally{busy=false;select.disabled=false;form.removeAttribute('aria-busy');}
        });
    };
    select.addEventListener('change',save);
    form.addEventListener('submit',event=>{event.preventDefault();save();});
});

// Measure actual header height, including wrapped navigation and safe areas.
const stickyHeader=document.querySelector('.site-header');
if(stickyHeader){
    const measureNavigation=()=>document.documentElement.style.setProperty('--navigation-height',stickyHeader.getBoundingClientRect().height+'px');
    measureNavigation();new ResizeObserver(measureNavigation).observe(stickyHeader);
}
