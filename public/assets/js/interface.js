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
    let busy=false;
    const unlock=()=>{busy=false;form.removeAttribute('aria-busy');form.querySelectorAll('button').forEach(button=>button.disabled=false);};
    window.addEventListener('pageshow',unlock);
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;busy=true;
        const submitter=event.submitter, data=new FormData(form);
        const action=data.get('action')||submitter?.value;
        const inline=form.hasAttribute('data-inline-progress');
        if((action==='delete'||(inline&&action==='not_started')) && !await confirmAction(action==='delete'?'Supprimer définitivement cet élément ?':'Annuler votre déclaration d’obtention ? Le succès restera consultable dans le Codex.')) {unlock();return;}
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
            const card=form.closest('[data-card]');if(card){card.classList.toggle('is-obtained',done);card.querySelector('[data-personal-badge]').textContent=done?'✓ Obtenu':'À débloquer';}
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
    const filter=()=>{const data=new FormData(context);let count=0;rows.forEach(row=>{row.hidden=[...data].some(([key,value])=>value&&row.dataset[key]!==value);if(!row.hidden)count++;});document.querySelector('[data-context-count]').textContent=count+' résultat'+(count===1?'':'s');};
    context.addEventListener('change',filter);context.addEventListener('reset',()=>setTimeout(filter,0));context.addEventListener('submit',event=>event.preventDefault());
}
