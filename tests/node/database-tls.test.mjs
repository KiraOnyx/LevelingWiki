import test from 'node:test';
import assert from 'node:assert/strict';
import {X509Certificate} from 'node:crypto';
import {createSecureContext,rootCertificates} from 'node:tls';
import {databaseTLS,supabaseCA} from '../../server/database-tls.mjs';
test('bundled Supabase CA is valid and TLS verification remains mandatory',()=>{
 const cert=new X509Certificate(supabaseCA);
 assert.equal(cert.ca,true);assert.ok(cert.verify(cert.publicKey));
 assert.ok(Date.now()>Date.parse(cert.validFrom));assert.ok(Date.now()<Date.parse(cert.validTo));
 const options=databaseTLS();assert.equal(options.rejectUnauthorized,true);
 assert.ok(options.ca.includes(supabaseCA));assert.ok(rootCertificates.every(root=>options.ca.includes(root)));
 assert.doesNotThrow(()=>createSecureContext(options));
});
