import test from 'node:test';
import assert from 'node:assert/strict';
import {effectQuality,installRenderer} from '../public/assets/js/tower-renderer.mjs';

test('ambiance auto : petits écrans et indices matériels modestes réduisent les effets',()=>{
    assert.equal(effectQuality('auto'), 'full');
    for (const hints of [{small:true},{memory:2},{memory:4},{cores:2},{cores:4},{saveData:true},{reduced:true}]) {
        assert.equal(effectQuality('auto',hints), 'lite');
    }
    assert.equal(effectQuality('auto',{memory:8,cores:8}), 'full');
});
test('préférences de mouvement et économie de données prioritaires même en mode complet',()=>{
    assert.equal(effectQuality('full',{reduced:true}), 'lite');
    assert.equal(effectQuality('full',{saveData:true}), 'lite');
    assert.equal(effectQuality('full',{small:true}), 'full');
    assert.equal(effectQuality('off',{reduced:true}), 'off');
    assert.equal(effectQuality('lite'), 'lite');
});
test('comparaison sans navigation : URL, ancre et scénario conservés ; tous les modules mis à jour',()=>{
    const old={location:globalThis.location,history:globalThis.history,window:globalThis.window};
    const location = {href:'http://localhost:8080/prototype/tour-de-guilde?motion=reduce#division-v1'};
    globalThis.location=location;
    const events={}; globalThis.window={addEventListener(type,fn){events[type]=fn;}};
    let replacements=0;
    globalThis.history={state:{test:true},replaceState(state,unused,url){assert.deepEqual(state,{test:true});replacements++;location.href=String(url);}};
    try {
        const control=value=>({value,events:{},addEventListener(type,fn){this.events[type]=fn;}});
        const renderer=control('flat'), atmosphere=control('auto'), info={};
        const symbols=['floor','base','crown'].map(kind=>({dataset:{architecture:kind},setAttribute(name,value){this[name]=value;}}));
        const root={dataset:{},querySelector(selector){return selector==='#tower-renderer'?renderer:selector==='#tower-atmosphere'?atmosphere:info;},querySelectorAll(){return symbols;}};
        const media={matches:false,addEventListener(){}};
        const apply=installRenderer(root,{mobile:media,reduce:media,announce(){}});
        renderer.value='relief';renderer.events.change();
        assert.equal(root.dataset.renderer,'relief');
        assert.equal(root.dataset.effects,'lite');
        assert.equal(symbols[0].href,'#tower-floor-relief');
        assert.match(location.href,/view=relief/);
        assert.match(location.href,/motion=reduce/);
        assert.match(location.href,/#division-v1$/);
        symbols.push({dataset:{architecture:'floor'},setAttribute(name,value){this[name]=value;}});apply();
        assert.equal(symbols.at(-1).href,'#tower-floor-relief');
        renderer.value='flat';renderer.events.change();
        assert.equal(symbols[0].href,'#tower-floor');
        assert.match(location.href,/view=flat/);
        assert.equal(replacements,2);
        location.href='http://localhost:8080/prototype/tour-de-guilde?view=relief&atmosphere=off#level-1';
        events.popstate();
        assert.equal(root.dataset.renderer,'relief');
        assert.equal(root.dataset.effects,'off');
        assert.equal(replacements,2);
    } finally {
        if(old.window===undefined)delete globalThis.window;else globalThis.window=old.window;
        if(old.location===undefined)delete globalThis.location;else globalThis.location=old.location;
        if(old.history===undefined)delete globalThis.history;else globalThis.history=old.history;
    }
});
