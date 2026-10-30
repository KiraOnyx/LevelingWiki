// Native disclosures do not trap focus or hide the rest of the page.
document.querySelectorAll('[data-admin-disclosure]').forEach(panel=>{
  const summary=panel.querySelector(':scope > summary');
  const close=()=>{panel.querySelectorAll('form').forEach(form=>form.reset());panel.open=false;summary.focus();};
  let cancel=panel.querySelector('[data-disclosure-cancel]');
  if(!cancel){cancel=document.createElement('button');cancel.type='button';cancel.className='secondary-button';cancel.textContent='Annuler';(panel.querySelector('.discord-role-actions')||panel).append(cancel);}
  cancel.addEventListener('click',event=>{event.preventDefault();close();});
  panel.addEventListener('toggle',()=>{if(panel.open)panel.querySelector('input:not([type=hidden]),select,textarea')?.focus({preventScroll:true});});
  panel.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();}});
});
const editor=document.querySelector('[data-admin-editor]');
if(editor){editor.setAttribute('tabindex','-1');editor.focus({preventScroll:true});}

// Without JS, the explicit confirmation checkbox remains required.
document.querySelectorAll('.catalog-cycle .check-label,.catalog-delete .check-label').forEach(label=>{label.hidden=true;label.querySelector('input').required=false;});

// Keep table labels readable when rows stack on small screens.
document.querySelectorAll('.table-scroll table').forEach(table=>{const headings=[...table.querySelectorAll('thead th')].map(h=>h.textContent.trim());if(!headings.length)return;table.classList.add('responsive-admin-table');table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,i)=>cell.dataset.column=headings[i]||''));});
