import test from 'node:test';import assert from 'node:assert/strict';
import {reportFailure} from '../../server/diagnostics.mjs';
test('production diagnostics expose only allowlisted error codes and configuration flags',()=>{
 const saved=console.error,logs=[];console.error=value=>logs.push(value);
 try{reportFailure({message:'private-password',cause:{code:'28P01',detail:'private-data'}},'database_connect',{DATABASE_URL:'postgres://private-password',SESSION_SECRET:'secret',DATABASE_CA_CERT:'private-cert'});reportFailure({code:'secret-token'},'application',{});}finally{console.error=saved;}
 assert.equal(JSON.parse(logs[0]).code,'28P01');assert.equal(JSON.parse(logs[0]).session_secret_valid,false);assert.equal(JSON.parse(logs[1]).code,'UNCLASSIFIED');assert.doesNotMatch(logs.join(''),/private-password|private-data|private-cert|secret-token/);
});
