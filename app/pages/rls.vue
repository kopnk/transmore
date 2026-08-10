<script setup lang="ts">
import { toast } from 'vue-sonner'
import type { CrudAction, PageKey, Permission, User } from '~/types'
import { permissionMenus } from '~/utils/menu'

const users=ref<User[]>([])
const selectedId=ref<number>()
const draft=ref<Permission|null>(null)
const saving=ref(false)
const loading=ref(false)
const loadError=ref('')
const actions:{key:CrudAction;label:string}[]=[{key:'create',label:'Create'},{key:'read',label:'Read'},{key:'update',label:'Update'},{key:'delete',label:'Delete'}]
const selectedUser=computed(()=>users.value.find(user=>user.id===selectedId.value))
const auth=useAuthStore()
const canUpdate=computed(()=>auth.user?.role==='superadmin'||auth.user?.permissions?.rls?.update===true)
const dirty=computed(()=>!!selectedUser.value&&!!draft.value&&JSON.stringify(draft.value)!==JSON.stringify(selectedUser.value.permissions))
const allowed=(menu:PageKey,action:CrudAction)=>selectedUser.value?.role==='superadmin'||draft.value?.[menu]?.[action]===true
const clonePermissions=(permissions:Permission)=>JSON.parse(JSON.stringify(permissions)) as Permission

function loadDraft(){draft.value=selectedUser.value?clonePermissions(selectedUser.value.permissions):null}
function cancelChanges(){if(!dirty.value)return;if(!confirm('Batalkan perubahan hak akses yang belum disimpan?'))return;loadDraft();toast.info('Perubahan RLS dibatalkan')}
async function load(){
  loading.value=true;loadError.value=''
  try{
    const response=await useApi().request<User[]>('/rls/users')
    users.value=response.data||[]
    selectedId.value=users.value.find(user=>user.role!=='superadmin')?.id??users.value[0]?.id
    loadDraft()
  }catch(error){loadError.value=error instanceof Error?error.message:'Daftar pengguna gagal dimuat';toast.error(loadError.value)}
  finally{loading.value=false}
}
function toggle(menu:PageKey,action:CrudAction){
  if(!draft.value||!canUpdate.value||selectedUser.value?.role==='superadmin')return
  draft.value[menu][action]=!draft.value[menu][action]
}
async function save(){
  const user=selectedUser.value
  if(!user?.id||!draft.value||!dirty.value)return
  saving.value=true
  try{
    await useApi().request(`/users/${user.id}/permissions`,{method:'PUT',body:{permissions:draft.value}})
    user.permissions=clonePermissions(draft.value)
    toast.success('Matrix RLS berhasil disimpan ke MySQL')
  }catch(error){toast.error(error instanceof Error?error.message:'Matrix gagal disimpan')}
  finally{saving.value=false}
}
watch(selectedId,loadDraft)
onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6"><h2 class="text-2xl font-bold">RLS Access Matrix</h2><p class="text-sm text-slate-500">Atur hak akses pengguna yang disimpan di tabel user_permissions.</p></div>
    <section class="card mb-5 p-5">
      <label class="label">Pilih Pengguna</label>
      <select v-model="selectedId" class="input max-w-xl"><option v-for="user in users" :key="user.id" :value="user.id">{{user.name}} — {{user.email}} ({{user.role}})</option></select>
      <div v-if="selectedUser" class="mt-4 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm"><p><span class="text-slate-400">ID User:</span> <span class="font-mono text-xs">{{selectedUser.iduser}}</span></p><p><span class="text-slate-400">Status:</span> {{selectedUser.status}}</p></div>
      <div class="mt-5 flex justify-end gap-3"><button v-if="dirty" type="button" class="btn-secondary" :disabled="saving" @click="cancelChanges">Batal</button><button type="button" class="btn-primary" :disabled="!dirty||saving||!canUpdate||selectedUser?.role==='superadmin'" @click="save">{{saving?'Menyimpan...':'Simpan'}}</button></div>
    </section>
    <section class="card overflow-hidden"><div class="overflow-x-auto"><table class="min-w-[620px] w-full text-center text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-6 py-4 text-left">Menu</th><th v-for="action in actions" :key="action.key" class="px-6 py-4">{{action.label}}</th></tr></thead><tbody><tr v-for="menu in permissionMenus" :key="menu.key" class="border-t hover:bg-slate-50"><td class="px-6 py-4 text-left"><div class="font-semibold text-slate-700">{{menu.label}}</div><div class="font-mono text-xs text-slate-400">{{menu.key}}</div></td><td v-for="action in actions" :key="action.key" class="px-6 py-4"><input v-if="selectedUser" type="checkbox" class="h-5 w-5 rounded accent-blue-600 disabled:opacity-50" :checked="allowed(menu.key,action.key)" :disabled="selectedUser.role==='superadmin'||!canUpdate" @change="toggle(menu.key,action.key)"></td></tr></tbody></table></div></section>
  </div>
</template>
