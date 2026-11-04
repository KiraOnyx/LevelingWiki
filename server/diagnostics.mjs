// Never log exception messages, URLs, SQL, request paths or session contents.
const codes=new Set(['28P01','28000','3D000','3F000','42P01','42703','42501','53300','57P03','ECONNREFUSED','ECONNRESET','ENOTFOUND','ETIMEDOUT','ENETUNREACH','SELF_SIGNED_CERT_IN_CHAIN','DEPTH_ZERO_SELF_SIGNED_CERT','UNABLE_TO_VERIFY_LEAF_SIGNATURE','CERT_HAS_EXPIRED','ERR_TLS_CERT_ALTNAME_INVALID']);
export function reportFailure(error,stage,env=process.env){
 const code=error?.cause?.code??error?.code;
 console.error(JSON.stringify({event:'levelingwiki_failure',stage,code:codes.has(code)?code:'UNCLASSIFIED',database_url_present:Boolean(env.DATABASE_URL),session_secret_valid:/^[a-f0-9]{64}$/i.test(env.SESSION_SECRET??''),database_ca_source:'bundled_supabase_and_system'}));
}
