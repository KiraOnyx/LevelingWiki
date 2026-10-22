import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { filterItems, readObtained, normalize } from '../public/assets/js/catalog-core.mjs';
const items = JSON.parse(readFileSync(new URL('./fixtures.json', import.meta.url)));
const slugs = (filters, obtained) => filterItems(items, filters, obtained).map(item => item.slug);
test('recherche insensible à la casse et aux accents', () => {
    assert.equal(normalize('ÉPIQUE'), 'epique');
    assert.deepEqual(slugs({q:'ELAN'}), ['elan-du-matin-test']);
    assert.deepEqual(slugs({q:'vallée'}), ['boussole-test']);
    assert.deepEqual(slugs({q:'eclaireur'}), ['elan-du-matin-test']);
    assert.deepEqual(slugs({q:'premier pas'}), ['elan-du-matin-test']);
    assert.deepEqual(slugs({q:'15 gemmes'}), ['elan-du-matin-test']);
});
test('filtres combinés et récompenses positives', () => {
    assert.deepEqual(slugs({type:'secret', rarity:'epique', category:'1', status:'verified', rewards:['xp','gold','gems','titles']}), ['elan-du-matin-test']);
    assert.deepEqual(slugs({type:'normal', rewards:['keys']}), ['boussole-test']);
    assert.deepEqual(slugs({rarity:'unknown'}), ['archive-inconnue-test']);
    assert.equal(slugs({status:'unverified'}).length, 2);
    assert.equal(slugs({rewards:['xp']}).includes('zero-test'), false);
});
test('les six tris conservent les inconnues à la fin', () => {
    assert.deepEqual(slugs({sort:'name-asc'}), ['archive-inconnue-test','boussole-test','elan-du-matin-test','zero-test']);
    assert.deepEqual(slugs({sort:'name-desc'}), ['zero-test','elan-du-matin-test','boussole-test','archive-inconnue-test']);
    assert.deepEqual(slugs({sort:'xp-desc'}), ['elan-du-matin-test','boussole-test','zero-test','archive-inconnue-test']);
    assert.deepEqual(slugs({sort:'xp-asc'}), ['zero-test','boussole-test','elan-du-matin-test','archive-inconnue-test']);
    assert.deepEqual(slugs({sort:'gold-desc'}), ['elan-du-matin-test','boussole-test','zero-test','archive-inconnue-test']);
    assert.deepEqual(slugs({sort:'newest'}), ['zero-test','archive-inconnue-test','boussole-test','elan-du-matin-test']);
});
test('zéro résultat et catalogue vide', () => {
    assert.deepEqual(slugs({q:'introuvable'}), []);
    assert.deepEqual(slugs({type:'normal', rarity:'epique'}), []);
    assert.deepEqual(filterItems([], {q:'test'}), []);
});
test('suivi personnel et désérialisation robuste', () => {
    const obtained = readObtained('["elan-du-matin-test",3,null,"elan-du-matin-test"]');
    assert.equal(obtained.size, 1);
    assert.deepEqual(slugs({progress:'obtained'}, obtained), ['elan-du-matin-test']);
    assert.equal(slugs({progress:'remaining'}, obtained).length, 3);
    for (const raw of [null, 'oops', '{}', 'null']) assert.equal(readObtained(raw).size, 0);
});
