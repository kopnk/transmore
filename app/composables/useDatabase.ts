import dayjs from 'dayjs'
import { db as offlineDb } from '~/db/index.client'
import type { AuditLog, Kebun, Kendaraan, Pks, User } from '~/types'

type Resource = 'users'|'kendaraan'|'pks'|'kebun'|'audit-logs'

const camelize=(row:any)=>({
  ...row,
  namaPemilik:row.namaPemilik??row.nama_pemilik,
  mustChangePassword:Boolean(row.mustChangePassword??row.must_change_password),
  createdBy:row.createdBy??row.created_by,
  createdAt:row.createdAt??row.created_at,
  updatedBy:row.updatedBy??row.updated_by,
  updatedAt:row.updatedAt??row.updated_at,
})

function repository<T extends {id?:number}>(resource:Resource){
  const assertBackendWritable=()=>{if(useAuthStore().offlineMode)throw new Error('Mode offline: hanya Pengiriman yang dapat diubah')}
  const list=async():Promise<T[]>=>{
    const response=await useApi().request<any[]>(`/${resource}`)
    return (response.data||[]).map(camelize) as T[]
  }
  return {
    toArray:list,
    reverse:()=>({toArray:list}),
    get:async(id:number)=>(await list()).find(row=>row.id===id),
    add:async(payload:any)=>{
      assertBackendWritable()
      const response=await useApi().request<any>(`/${resource}`,{method:'POST',body:payload})
      return response.data
    },
    update:async(id:number,payload:any)=>{assertBackendWritable();await useApi().request(`/${resource}/${id}`,{method:'PUT',body:payload});return 1},
    delete:async(id:number)=>{assertBackendWritable();await useApi().request(`/${resource}/${id}`,{method:'DELETE'})},
    count:async()=>(await list()).length,
    where:(field:string)=>({
      anyOf:(values:unknown[])=>({delete:async()=>{assertBackendWritable();const rows=await list();for(const row of rows)if(values.includes((row as any)[field])&&row.id)await useApi().request(`/${resource}/${row.id}`,{method:'DELETE'})}}),
      equals:(value:unknown)=>({
        toArray:async()=>(await list()).filter((row:any)=>row[field]===value),
        first:async()=>(await list()).find((row:any)=>row[field]===value),
        count:async()=>(await list()).filter((row:any)=>row[field]===value).length,
      })
    })
  }
}

export const useDatabase=()=>{
  const ready=useState('db-ready',()=>true)
  const seed=async()=>{ready.value=true}
  const db={
    transactions:offlineDb.transactions,
    outbox:offlineDb.outbox,
    users:repository<User>('users'),
    kendaraan:repository<Kendaraan>('kendaraan'),
    pks:repository<Pks>('pks'),
    kebun:repository<Kebun>('kebun'),
    auditLogs:repository<AuditLog>('audit-logs'),
  }
  const getAccessVia=()=>{
    if(typeof navigator==='undefined')return'unknown'
    return `${navigator.userAgent||'unknown'} (${navigator.onLine?'online':'offline'})`
  }
  const audit=async(action:string,module:string,detail:string)=>{
    if(!navigator.onLine)return
    try{await useApi().request('/audit-logs',{method:'POST',body:{action,module,detail,via:getAccessVia(),createdAt:dayjs().toISOString()}})}catch{/* audit tidak boleh menggagalkan operasi utama */}
  }
  return{db,ready,seed,audit,offlineDb}
}
