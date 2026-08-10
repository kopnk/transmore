import { db } from '~/db/index.client'
import type { OfflineGrantRecord, User } from '~/types'
import { normalizePhone } from '~/utils/identifier'

interface GrantPayload { iss:string; mode:string; iat:number; exp:number; user:User }

const decodeBase64Url=(value:string)=>{const normalized=value.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-value.length%4)%4);return Uint8Array.from(atob(normalized),character=>character.charCodeAt(0))}
const importPublicKey=async(value:string)=>crypto.subtle.importKey('raw',decodeBase64Url(value),{name:'Ed25519'},false,['verify'])
const verify=async(record:OfflineGrantRecord):Promise<User|null>=>{const[payloadPart,signaturePart]=record.token.split('.');if(!payloadPart||!signaturePart)return null;const key=await importPublicKey(record.publicKey);const valid=await crypto.subtle.verify('Ed25519',key,decodeBase64Url(signaturePart),new TextEncoder().encode(payloadPart));if(!valid)return null;const payload=JSON.parse(new TextDecoder().decode(decodeBase64Url(payloadPart))) as GrantPayload;if(payload.iss!=='transmore-backend'||payload.mode!=='offline-operational'||payload.exp*1000<=Date.now())return null;return payload.user}

export const useOfflineAuth=()=>({
  save:async(grant:Omit<OfflineGrantRecord,'id'>)=>{const record:OfflineGrantRecord={id:'current',...grant};if(!await verify(record))throw new Error('Grant offline dari backend tidak valid');await db.offlineAuth.put(record)},
  restore:async(identifier?:string)=>{const record=await db.offlineAuth.get('current');if(!record)return null;try{const user=await verify(record);const value=identifier?.trim();const matches=!value||user?.email.toLowerCase()===value.toLowerCase()||normalizePhone(user?.handphone||'')===normalizePhone(value);if(!user||!matches){if(!user)await db.offlineAuth.delete('current');return null}return user}catch{await db.offlineAuth.delete('current');return null}},
  clear:async()=>db.offlineAuth.delete('current'),
})
