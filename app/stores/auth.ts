import type { User } from '~/types'
import type { PageKey } from '~/types'
import { permissionsForRole } from '~/utils/menu'
export const useAuthStore = defineStore('auth',()=>{
  const user=ref<User|null>(null)
  const hydrated=ref(false)
  const offlineMode=ref(false)
  const loggedIn=computed(()=>!!user.value)
  const canRead=(page:PageKey)=>user.value?.role==='superadmin'||user.value?.permissions?.[page]?.read===true
  function clearSession(){user.value=null;hydrated.value=true}
  async function restore(force=false){
    if(!import.meta.client||(hydrated.value&&!force))return
    try{const response=await useApi().request<User>('/me');user.value=response.user||null;offlineMode.value=false}catch(error){const status=(error as {status?:number})?.status??0;user.value=(!navigator.onLine||status===0||status>=500)?await useOfflineAuth().restore():null;offlineMode.value=!!user.value}
    hydrated.value=true
  }
  async function login(identifier:string,password:string){
    if(!navigator.onLine)throw new Error('Backend tidak tersedia. Gunakan tombol Masuk Offline.')
    const response=await useApi().request<User>('/login',{method:'POST',body:{identifier,password}})
    user.value=response.user||null;hydrated.value=true;offlineMode.value=false
    if(!user.value)throw new Error('Respons login tidak valid')
    if(response.offlineGrant)await useOfflineAuth().save(response.offlineGrant)
    await useLegacyDataMigration().run()
  }
  async function loginOffline(identifier:string){user.value=await useOfflineAuth().restore(identifier);hydrated.value=true;offlineMode.value=!!user.value;if(!user.value)throw new Error('Akses offline belum tersedia atau sudah kedaluwarsa. Login online terlebih dahulu.')}
  async function logout(){
    try{if(navigator.onLine)await useApi().request('/logout',{method:'POST'})}finally{await useOfflineAuth().clear();offlineMode.value=false;clearSession()}
    return navigateTo('/login')
  }
  return {user,hydrated,loggedIn,offlineMode,canRead,restore,login,loginOffline,logout,clearSession}
})
