import { db } from '~/db/index.client'

export const useLegacyDataMigration=()=>{
  const running=useState('legacy-data-migration-running',()=>false)
  const run=async()=>{
    if(!import.meta.client||!navigator.onLine||running.value)return
    const auth=useAuthStore()
    if(!auth.loggedIn)return
    running.value=true
    try{
      const [kendaraan,pks,kebun]=await Promise.all([
        db.kendaraan.toArray(),db.pks.toArray(),db.kebun.toArray()
      ])
      if(kendaraan.length||pks.length||kebun.length){
        await useApi().request('/bootstrap/import',{method:'POST',body:{kendaraan,pks,kebun}})
      }
      // Hapus data legacy hanya setelah backend mengonfirmasi import berhasil.
      await db.transaction('rw',db.users,db.kendaraan,db.pks,db.kebun,db.auditLogs,async()=>{
        await Promise.all([db.users.clear(),db.kendaraan.clear(),db.pks.clear(),db.kebun.clear(),db.auditLogs.clear()])
      })
    }finally{running.value=false}
  }
  return{run,running}
}
