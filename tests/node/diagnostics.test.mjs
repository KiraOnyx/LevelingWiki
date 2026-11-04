import test from 'node:test';import assert from 'node:assert/strict';
import {reportFailure,failureReason} from '../../server/diagnostics.mjs';
test('production diagnostics expose only allowlisted error codes and configuration flags',()=>{
 const saved=console.error,logs=[];console.error=value=>logs.push(value);
 try{reportFailure({message:'private-password',cause:{code:'28P01',detail:'private-data'}},'database_connect',{DATABASE_URL:'postgres://private-password',SESSION_SECRET:'secret',DATABASE_CA_CERT:'private-cert'});reportFailure({code:'secret-token'},'application',{});}finally{console.error=saved;}
 assert.equal(JSON.parse(logs[0]).code,'28P01');assert.equal(JSON.parse(logs[0]).session_secret_valid,false);assert.equal(JSON.parse(logs[1]).code,'UNCLASSIFIED');assert.doesNotMatch(logs.join(''),/private-password|private-data|private-cert|secret-token/);
});

test('pooler and connection failures have safe fixed diagnostic labels',()=>{
 for(const [message,expected]of [
 ['Circuit breaker open: Too many authentication errors','POOLER_CIRCUIT_BREAKER'],
 ['Tenant or user not found','POOLER_TENANT_OR_USER_NOT_FOUND'],
 ['password authentication failed for user private-user','PASSWORD_REJECTED'],
 ['SASL: client password must be a string','PASSWORD_MISSING_OR_INVALID'],
 ['Connection terminated due to connection timeout','CONNECTION_TIMEOUT'],
 ['Invalid URL: postgres://private:secret@host','INVALID_DATABASE_URL'],
 ['unexpected private data','UNCLASSIFIED']])assert.equal(failureReason({cause:{message}}),expected);
});
