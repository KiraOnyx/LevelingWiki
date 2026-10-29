export const storageKey = 'levelingwiki.obtained.v1';
export const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').replace(/œ/g, 'oe');
export function readObtained(raw) {
    try {
        const parsed = JSON.parse(raw);
        return new Set(Array.isArray(parsed) ? parsed.filter(value => typeof value === 'string') : []);
    } catch { return new Set(); }
}
export function filterItems(items, filters = {}, obtained = new Set(), states = {}) {
    const query = normalize(filters.q).trim();
    const resourceKeys=[...new Set(items.flatMap(item=>Object.keys(item).filter(key=>/^reward_[a-z]+$/.test(key)).map(key=>key.slice(7))))];
    const matches = items.filter(item => {
        const text = [item.name, item.description, item.unlock_conditions, ...item.titles,
            ...resourceKeys.filter(key => item[`reward_${key}`] !== null)
                .map(key => `${item[`reward_${key}`]} ${{xp:'XP', gold:'pièces or', gems:'gemmes', keys:'clés'}[key]||key}`)].join(' ');
        return (!query || query.split(/\s+/).every(word => normalize(text).includes(word)))
            && (!filters.type || item.secret_type === filters.type)
            && (!filters.rarity || (filters.rarity === 'unknown' ? !item.rarity : normalize(item.rarity) === filters.rarity))
            && (!filters.category || String(item.category_id) === filters.category)
            && (!filters.status || (filters.status === 'verified' ? item.verification_status === 'verified' : item.verification_status !== 'verified'))
            && (!filters.progress || (filters.progress === 'obtained' ? obtained.has(item.slug) : filters.progress === 'remaining' ? !obtained.has(item.slug) : (states[item.slug] || 'not_started') === filters.progress))
            && (filters.rewards ?? []).every(reward => reward === 'unknown' ? resourceKeys.some(key=>item[`reward_${key}`]===null)||!item.title_rewards_known : reward === 'titles' ? item.titles.length > 0 : resourceKeys.includes(reward)&&Number(item[`reward_${reward}`]) > 0);
    });
    const collator = new Intl.Collator('fr', { sensitivity: 'base', numeric: true });
    return matches.sort((a, b) => {
        const byName = collator.compare(a.name, b.name) || collator.compare(a.slug, b.slug);
        if (filters.sort === 'name-desc') return -byName;
        if (filters.sort === 'obtained-desc') return (b.obtained_at || '').localeCompare(a.obtained_at || '') || byName;
        if (filters.sort === 'newest') return b.created_at.localeCompare(a.created_at) || byName;
        const requested=/^([a-z]+)-(asc|desc)$/.exec(filters.sort||'');
        const sort=requested&&resourceKeys.includes(requested[1])?['reward_'+requested[1],requested[2]==='asc'?1:-1]:null;
        if (!sort) return byName;
        const [key, direction] = sort;
        if (a[key] === null || b[key] === null) return a[key] === b[key] ? byName : a[key] === null ? 1 : -1;
        // Les BIGINT SQL restent des chaînes : pas de perte de précision dans le tri.
        const left = BigInt(a[key]); const right = BigInt(b[key]);
        return (left < right ? -direction : left > right ? direction : 0) || byName;
    });
}
