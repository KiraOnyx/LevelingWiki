import test from 'node:test';
import assert from 'node:assert/strict';
import {levelPosition,placeLabel} from '../public/assets/js/tower-geometry.mjs';

test('interpolation exacte entre paliers irréguliers, indépendamment de leur ordre',()=>{
    const anchors=[{level:50,y:100},{level:1,y:1060},{level:40,y:420},{level:5,y:740}];
    assert.equal(levelPosition(43,anchors),324);
    assert.equal(levelPosition(45,anchors),260);
    assert.equal(levelPosition(49,anchors),132);
    assert.equal(levelPosition(3,anchors),900);
    assert.equal(levelPosition(1,anchors),1060);
});
test('niveau supérieur à 100 avec ancre dédiée sans inventer de récompense',()=>{
    assert.equal(levelPosition(135,[{level:100,y:372},{level:135,y:52}]),52);
    assert.equal(levelPosition(110,[{level:100,y:372},{level:135,y:52}]),372-320*10/35);
});
test('100 bulles proches : aucun recouvrement avec les cartes ou les autres bulles',()=>{
    const occupied=[{start:100,end:320},{start:500,end:720}];
    for(let i=0;i<100;i++) {
        const y=placeLabel(430+i%3,64,occupied);
        assert.ok(y>=0);
        for(const block of occupied) assert.ok(y+64+12<=block.start || y>=block.end+12);
        occupied.push({start:y,end:y+64});
    }
});
test('les bulles au pied et au sommet restent dans des coordonnées positives',()=>{
    assert.equal(placeLabel(0,64,[]),0);
    assert.equal(placeLabel(1000,64,[]),968);
    assert.equal(placeLabel(130,64,[{start:100,end:200}]),24);
});
