import crypto from 'node:crypto';
import bcrypt from 'bcryptjs';
let cachedTag,cachedHash;
export function ownerPasswordVersion(env){
 if(!env.OWNER_PASSWORD)return null;
 if(typeof env.OWNER_PASSWORD!=='string'||env.OWNER_PASSWORD.length<12||Buffer.byteLength(env.OWNER_PASSWORD)>72||env.OWNER_PASSWORD.includes('\0'))throw Error('Invalid owner password configuration');
 return crypto.createHmac('sha256',env.SESSION_SECRET).update('owner-password:').update(env.OWNER_PASSWORD).digest('hex');
}
export async function verifyOwnerPassword(password,env){
 const tag=ownerPasswordVersion(env);if(!tag)return false;
 if(typeof password!=='string'||Buffer.byteLength(password)>72||password.includes('\0'))return false;
 if(cachedTag!==tag){cachedTag=tag;cachedHash=bcrypt.hash(env.OWNER_PASSWORD,12);}
 return bcrypt.compare(password,await cachedHash);
}
