/** Conservative enhancement: device hints are optional, never a requirement. */
export function effectQuality(requested, {small = false, reduced = false, saveData = false, memory, cores} = {}) {
    if (requested === 'off') return 'off';
    if (reduced || saveData) return 'lite';
    if (requested === 'full' || requested === 'lite') return requested;
    return small || (memory > 0 && memory <= 4) || (cores > 0 && cores <= 4) ? 'lite' : 'full';
}

export function installRenderer(root, {mobile, reduce, announce}) {
    const renderer = root.querySelector('#tower-renderer');
    const atmosphere = root.querySelector('#tower-atmosphere');
    const connection = navigator.connection;
    const apply = () => {
        const url = new URL(location.href);
        root.dataset.renderer = renderer.value;
        root.dataset.atmosphere = atmosphere.value;
        root.dataset.effects = effectQuality(atmosphere.value, {
            small:mobile.matches, reduced:reduce.matches || url.searchParams.get('motion') === 'reduce',
            saveData:connection?.saveData, memory:navigator.deviceMemory, cores:navigator.hardwareConcurrency,
        });
        root.querySelectorAll('[data-architecture]').forEach(use => {
            use.setAttribute('href', `#tower-${use.dataset.architecture}${renderer.value === 'relief' ? '-relief' : ''}`);
        });
        const quality = {full:'complète',lite:'légère',off:'désactivée'}[root.dataset.effects];
        root.querySelector('[data-render-info]').textContent = renderer.value === 'flat'
            ? 'Version originale · décor expérimental désactivé'
            : `Relief SVG · ambiance ${quality} · sans animation`;
    };
    const change = () => {
        apply();
        const url = new URL(location.href);
        url.searchParams.set('view',renderer.value);
        if (atmosphere.value !== 'auto') url.searchParams.set('atmosphere',atmosphere.value); else url.searchParams.delete('atmosphere');
        // Same document, same geometry and scroll. Do not retrigger initial descent or add history entries.
        history.replaceState(history.state,'',url);
        announce(renderer.value === 'relief' ? 'Variante 2,5D. Position conservée.' : 'Version 2D originale. Position conservée.');
    };
    const initialAtmosphere = new URL(location.href).searchParams.get('atmosphere');
    if (['full','lite','off'].includes(initialAtmosphere)) atmosphere.value = initialAtmosphere;
    renderer.addEventListener('change',change);
    atmosphere.addEventListener('change',change);
    mobile.addEventListener('change',apply);
    reduce.addEventListener('change',apply);
    connection?.addEventListener?.('change',apply);
    window.addEventListener('popstate', () => {
        const params = new URL(location.href).searchParams;
        renderer.value = params.get('view') === 'flat' ? 'flat' : (params.get('view') === 'relief' || root.dataset.live === 'true' ? 'relief' : 'flat');
        atmosphere.value = ['full','lite','off'].includes(params.get('atmosphere')) ? params.get('atmosphere') : 'auto';
        apply(); // Restore appearance from the URL without touching native scroll restoration.
    });
    apply();
    return apply; // Reapply symbols after a test scenario appends an architectural module.
}
