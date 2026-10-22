import { storageKey, readObtained, filterItems } from './catalog-core.mjs';
const items = JSON.parse(document.querySelector('#catalog-data').textContent);
const form = document.querySelector('#catalog-filters');
const notice = document.querySelector('[data-storage-notice]');
let obtained = new Set();
let timer;
function storageUnavailable() {
    if (notice) {
        notice.hidden = false;
        notice.textContent = 'Le stockage local est indisponible. Votre progression sera conservée uniquement pendant cette visite.';
    }
}
try { obtained = readObtained(localStorage.getItem(storageKey)); } catch { storageUnavailable(); }
function filters() {
    if (!form) return {};
    const data = new FormData(form);
    return { ...Object.fromEntries(data), rewards: data.getAll('reward') };
}
function updateResults() {
    if (!form) return;
    const focused = document.activeElement;
    const current = filters();
    const results = filterItems(items, current, obtained);
    const visible = new Set(results.map(item => item.slug));
    const grid = document.querySelector('[data-grid]');
    const cards = new Map([...grid.children].map(card => [card.dataset.card, card]));
    for (const [slug, card] of cards) card.hidden = !visible.has(slug);
    // Réordonner le DOM garde une navigation clavier cohérente avec le tri visuel.
    for (const item of results) grid.append(cards.get(item.slug));
    if (focused instanceof HTMLElement && !focused.closest('[hidden]')) focused.focus({preventScroll: true});
    document.querySelector('[data-count]').textContent = results.length;
    document.querySelector('[data-results]').textContent = `${results.length} résultat${results.length === 1 ? '' : 's'}`;
    const empty = document.querySelector('[data-empty]');
    empty.hidden = results.length > 0;
    document.querySelector('[data-empty-title]').textContent = items.length ? 'Aucun succès ne correspond à votre recherche.' : 'Aucun succès référencé pour le moment.';
    document.querySelector('[data-empty-description]').textContent = items.length ? 'Essayez un autre mot-clé ou élargissez vos filtres pour poursuivre l’exploration.' : 'Les archives attendent leurs premières découvertes. Revenez bientôt pour explorer les succès de Leveling Fitness.';
    document.querySelector('[data-reset]').hidden = !items.length;
    const params = new URLSearchParams();
    for (const [key, value] of new FormData(form)) if (value && !(key === 'sort' && value === 'name-asc')) params.append(key, value);
    const query = params.toString();
    history.replaceState(null, '', `${location.pathname}${query ? '?' + query : ''}${location.hash}`);
}
function updateProgress() {
    const count = items.filter(item => obtained.has(item.slug)).length;
    const percent = items.length ? Math.round(count / items.length * 100) : 0;
    document.querySelectorAll('[data-progress]').forEach(panel => { panel.hidden = false; });
    document.querySelectorAll('[data-obtained-count]').forEach(el => { el.textContent = count; });
    document.querySelectorAll('[data-progress-percent]').forEach(el => { el.textContent = `${percent} %`; });
    document.querySelectorAll('progress').forEach(el => { el.value = percent; });
    document.querySelectorAll('[data-toggle]').forEach(button => {
        const active = obtained.has(button.dataset.toggle);
        button.hidden = false;
        button.setAttribute('aria-pressed', String(active));
        button.textContent = active ? '✓ Obtenu' : 'Marquer comme obtenu';
        const item = items.find(item => item.slug === button.dataset.toggle);
        button.setAttribute('aria-label', `${active ? 'Marquer comme non obtenu' : 'Marquer comme obtenu'} : ${item?.name ?? ''}`);
    });
    document.querySelectorAll('[data-card]').forEach(card => { card.classList.toggle('is-obtained', obtained.has(card.dataset.card)); });
}
document.querySelectorAll('[data-toggle]').forEach(button => button.addEventListener('click', () => {
    obtained.has(button.dataset.toggle) ? obtained.delete(button.dataset.toggle) : obtained.add(button.dataset.toggle);
    try { localStorage.setItem(storageKey, JSON.stringify([...obtained])); } catch { storageUnavailable(); }
    updateProgress();
    const activeFilter = filters().progress;
    updateResults();
    if (activeFilter && button.closest('[hidden]')) document.querySelector('[name="progress"]')?.focus();
}));
if (form) {
    form.hidden = false;
    const params = new URLSearchParams(location.search);
    for (const field of form.elements) {
        if (!field.name) continue;
        if (field.type === 'checkbox') field.checked = params.getAll(field.name).includes(field.value);
        else if (params.has(field.name)) field.value = params.get(field.name);
    }
    const panel = document.querySelector('.filters-panel');
    const media = matchMedia('(max-width: 650px)');
    panel.open = !media.matches;
    media.addEventListener('change', event => { panel.open = !event.matches; });
    form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); updateResults(); });
    form.addEventListener('input', event => {
        if (event.target.name === 'q') { clearTimeout(timer); timer = setTimeout(updateResults, 250); }
    });
    form.addEventListener('change', event => { if (event.target.name !== 'q') { clearTimeout(timer); updateResults(); } });
    form.addEventListener('reset', () => { clearTimeout(timer); setTimeout(updateResults, 0); });
    document.querySelector('[data-reset]').addEventListener('click', () => { form.reset(); document.querySelector('#search').focus(); });
    updateResults();
}
window.addEventListener('storage', event => {
    if (event.key === storageKey || event.key === null) { obtained = readObtained(event.newValue); updateProgress(); updateResults(); }
});
updateProgress();
