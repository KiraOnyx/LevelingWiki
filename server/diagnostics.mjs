// Never log exception messages, URLs, SQL, request paths or session contents.
const codes=new Set(['XX000','08P01','08006','08001','57P01','ERR_INVALID_URL','ERR_INVALID_ARG_TYPE','28P01','28000','3D000','3F000','42P01','42703','42501','53300','57P03','ECONNREFUSED','ECONNRESET','ENOTFOUND','ETIMEDOUT','ENETUNREACH','SELF_SIGNED_CERT_IN_CHAIN','DEPTH_ZERO_SELF_SIGNED_CERT','UNABLE_TO_VERIFY_LEAF_SIGNATURE','CERT_HAS_EXPIRED','ERR_TLS_CERT_ALTNAME_INVALID']);
// Classify known messages internally; only fixed labels ever leave this function.
export function failureReason(error){
 const message=String(error?.cause?.message??error?.message??'');
 if(/circuit breaker|circuit_breaker/i.test(message))return 'POOLER_CIRCUIT_BREAKER';
 if(/tenant or user not found/i.test(message))return 'POOLER_TENANT_OR_USER_NOT_FOUND';
 if(/password authentication failed|wrong password/i.test(message))return 'PASSWORD_REJECTED';
 if(/password must be a string|client password must be a string/i.test(message))return 'PASSWORD_MISSING_OR_INVALID';
 if(/timeout|timed out/i.test(message))return 'CONNECTION_TIMEOUT';
 if(/connection terminated|connection closed/i.test(message))return 'CONNECTION_CLOSED';
 if(/invalid (?:database )?url|uri malformed/i.test(message))return 'INVALID_DATABASE_URL';
 if(message==='DATABASE_URL is required')return 'DATABASE_URL_MISSING';
 return 'UNCLASSIFIED';
}
function databaseURLValid(value){try{const url=new URL(value);return ['postgres:','postgresql:'].includes(url.protocol)&&Boolean(url.hostname)&&Boolean(url.username)&&Boolean(url.password)&&!url.hash;}catch{return false;}}
export function reportFailure(error,stage,env=process.env){
 const code=error?.cause?.code??error?.code;
 console.error(JSON.stringify({event:'levelingwiki_failure',stage,code:codes.has(code)?code:'UNCLASSIFIED',reason:failureReason(error),database_url_valid:databaseURLValid(env.DATABASE_URL),database_url_present:Boolean(env.DATABASE_URL),session_secret_valid:/^[a-f0-9]{64}$/i.test(env.SESSION_SECRET??''),database_ca_source:'bundled_supabase_and_system'}));
}
