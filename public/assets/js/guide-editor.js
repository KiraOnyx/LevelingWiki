export function markdownInsertion(command,selection='') {
    const value=selection||'Texte';
    return ({heading:`\n### ${value}\n`,bold:`**${value}**`,italic:`*${value}*`,bullet:`\n- ${value}\n`,number:`\n1. ${value}\n`,quote:`\n> ${value}\n`,link:`[${selection||'Libellé'}](https://example.com)`})[command]||selection;
}
export function imageMarkdown(path,alt){return `\n![${alt.replace(/[\[\]\\\r\n]/g,' ')}](${path})\n`;}
if(typeof document!=='undefined')document.querySelectorAll('[data-guide-editor]').forEach(editor=>{
    const form=editor.closest('form'),area=editor.querySelector('textarea'),feedback=editor.querySelector('[data-guide-feedback]'),output=editor.querySelector('[data-guide-output]');
    let selection=[0,0],previewSequence=0;
    area.addEventListener('select',()=>selection=[area.selectionStart,area.selectionEnd]);
    area.addEventListener('input',()=>{selection=[area.selectionStart,area.selectionEnd];feedback.textContent='Aperçu à actualiser.';previewSequence++;});
    function insert(text){area.setRangeText(text,...selection,'end');selection=[area.selectionStart,area.selectionEnd];area.focus();area.dispatchEvent(new Event('input',{bubbles:true}));}
    async function request(url,data){const response=await fetch(url,{method:'POST',body:data,headers:{Accept:'application/json'}});let result;try{result=await response.json();}catch{throw new Error('Réponse indisponible. Votre texte est conservé.');}if(!response.ok||!result.ok)throw new Error(result.message||'Opération impossible.');return result;}
    function payload(){const data=new FormData();data.set('csrf',form.elements.csrf.value);data.set('achievement_id',form.elements.id.value);return data;}
    async function preview(){const sequence=++previewSequence,data=payload();data.set('markdown',area.value);feedback.textContent='Chargement de l’aperçu…';try{const result=await request('/admin/achievements/guide-preview',data);if(sequence!==previewSequence)return;output.innerHTML=result.html||'<p>Aucun guide : cette section sera masquée sur la fiche.</p>';feedback.textContent='Aperçu actualisé.';}catch(error){if(sequence===previewSequence)feedback.textContent=error.message;}}
    editor.querySelectorAll('[data-markdown]').forEach(button=>button.addEventListener('click',()=>insert(markdownInsertion(button.dataset.markdown,area.value.slice(...selection)))));
    editor.querySelector('[data-guide-preview]').addEventListener('click',preview);
    editor.addEventListener('click',event=>{const button=event.target.closest('[data-guide-insert]');if(button)insert(imageMarkdown(button.dataset.guideInsert,button.dataset.alt));});
    editor.querySelector('[data-guide-upload]')?.addEventListener('click',async event=>{
        const button=event.currentTarget,file=editor.querySelector('[data-guide-file]').files[0],alt=editor.querySelector('[data-guide-alt]').value.trim();
        if(!file||!alt){feedback.textContent='Sélectionnez une image et renseignez son texte alternatif.';return;}
        const data=payload();data.set('image',file);data.set('alt',alt);button.disabled=true;feedback.textContent='Envoi de l’image…';
        try{const result=await request('/admin/achievements/guide-image',data);insert(imageMarkdown(result.path,result.alt));const li=document.createElement('li'),img=document.createElement('img'),reuse=document.createElement('button');img.src=result.path;img.alt=result.alt;reuse.type='button';reuse.className='secondary-button';reuse.dataset.guideInsert=result.path;reuse.dataset.alt=result.alt;reuse.textContent=`Insérer : ${result.alt}`;li.append(img,reuse);editor.querySelector('[data-guide-images]').append(li);await preview();}catch(error){feedback.textContent=error.message;}finally{button.disabled=false;}
    });
});
