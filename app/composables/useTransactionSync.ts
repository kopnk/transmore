import dayjs from 'dayjs'
import { db } from '~/db/index.client'
import type { OutboxItem, Transaction } from '~/types'

const fromServer=(row:any):Transaction=>({
  idpengiriman:row.idpengiriman,number:row.number,date:row.date,kebun:row.kebun,divisi:row.divisi,
  vehicle:row.vehicle,odoStart:Number(row.odo_start),odoEnd:Number(row.odo_end),driver:row.driver,
  loadDate:row.load_date,loadWeight:Number(row.load_weight),unloadDate:row.unload_date,
  unloadWeight:Number(row.unload_weight),price:Number(row.price),fee:Number(row.fee),
  receiverPic:row.receiver_pic,notes:row.notes||'',docLink:row.doc_link||'',approved:Boolean(Number(row.approved)),pks:row.pks||'',
  status:row.status,createdBy:row.created_by||'system',createdAt:row.created_at,
  updatedBy:row.updated_by||undefined,updatedAt:row.updated_at||undefined,
})

export const useTransactionSync=()=>{
  const syncing=useState('transaction-syncing',()=>false)
  const pendingCount=useState('transaction-pending-count',()=>0)
  const refreshPending=async()=>{pendingCount.value=await db.outbox.where('resource').equals('transactions').count()}
  const enqueue=async(action:OutboxItem['action'],transaction:Transaction)=>{
    const previous=await db.outbox.where('resource').equals('transactions').toArray()
    const same=previous.find(item=>item.resourceId===transaction.idpengiriman)
    if(same?.id)await db.outbox.delete(same.id)
    await db.outbox.add({createdAt:dayjs().toISOString(),status:'pending',action,resource:'transactions',resourceId:transaction.idpengiriman,payload:{...transaction}})
    await refreshPending()
  }
  const push=async()=>{
    if(!navigator.onLine||syncing.value)return
    syncing.value=true
    try{
      const items=await db.outbox.orderBy('createdAt').toArray()
      for(const item of items){
        if(item.resource!=='transactions'||!item.id)continue
        try{
          await db.outbox.update(item.id,{status:'syncing'})
          if(item.action==='delete')await useApi().request(`/transactions/by-uuid/${item.resourceId}`,{method:'DELETE'})
          else await useApi().request('/transactions/sync',{method:'POST',body:item.payload})
          await db.outbox.delete(item.id)
        }catch{await db.outbox.update(item.id,{status:'failed'});break}
      }
    }finally{syncing.value=false;await refreshPending()}
  }
  const pull=async()=>{
    if(!navigator.onLine)return
    const response=await useApi().request<any[]>('/transactions')
    const pendingIds=new Set((await db.outbox.toArray()).map(item=>item.resourceId))
    const serverRows=(response.data||[]).map(fromServer)
    const localByNumber=new Map((await db.transactions.toArray()).map(row=>[row.number,row]))
    const merged=serverRows.filter(row=>!pendingIds.has(row.idpengiriman)).map(row=>{const local=localByNumber.get(row.number);return local?.id?{...row,id:local.id}:row})
    if(merged.length)await db.transactions.bulkPut(merged)
  }
  const sync=async()=>{await refreshPending();await push();await pull();await refreshPending()}
  return{sync,push,pull,enqueue,syncing,pendingCount,refreshPending}
}
