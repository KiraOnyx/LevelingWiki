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
