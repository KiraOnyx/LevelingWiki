document.querySelectorAll('[data-catalog-form]').forEach(form => {
  const preview = form.querySelector('[data-catalog-preview]');
  const render = () => {
    if (!preview) return;
    const fields = form.elements;
    const type = fields.type_id.selectedOptions[0];
    preview.textContent = 'Aperçu membre : ' + (fields.visibility.value === 'unknown' ? '???' : `${fields.quantity.value} ${fields.type_id.value ? type.textContent : fields.description.value}`);
  };
  form.addEventListener('input', render); render();
  form.addEventListener('submit', () => { form.querySelectorAll('button').forEach(b => { b.disabled = true; }); });
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-catalog-form] button').forEach(b => { b.disabled = false; }));
