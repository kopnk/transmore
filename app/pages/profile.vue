<script setup lang="ts">
import {toast} from 'vue-sonner'
import {z} from 'zod'

const auth=useAuthStore()
const {audit}=useDatabase()
const form=reactive({name:'',email:'',handphone:'',alamat:''})

onMounted(async()=>{
  await auth.restore()
  if(auth.user)Object.assign(form,{name:auth.user.name,email:auth.user.email,handphone:auth.user.handphone,alamat:auth.user.alamat})
})

async function save(){
  const result=z.object({name:z.string().min(3,'Nama minimal 3 karakter'),alamat:z.string().min(5,'Alamat minimal 5 karakter')}).safeParse({name:form.name,alamat:form.alamat})
  if(!result.success){notifyValidationErrors(result.error);return}
  if(!auth.user?.id)return toast.error('Sesi pengguna tidak ditemukan. Silakan login kembali.')
  try{
    await useApi().request('/me',{method:'PUT',body:result.data})
    Object.assign(auth.user,result.data)
    await audit('UPDATE','Profile',auth.user.email)
    toast.success('Profile berhasil diperbarui')
  }catch(error){toast.error(error instanceof Error?error.message:'Profile gagal diperbarui')}
}
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <div class="mb-6"><h2 class="text-2xl font-bold">Profile Saya</h2><p class="text-sm text-slate-500">Perbarui informasi pribadi akun Anda.</p></div>
    <form class="card p-6 sm:p-8" @submit.prevent="save">
      <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="label">Nama</label><input v-model="form.name" class="input"></div>
        <div><label class="label">Email</label><input v-model="form.email" class="input cursor-not-allowed bg-slate-50 text-slate-500" type="email" readonly></div>
        <div><label class="label">Handphone</label><input v-model="form.handphone" class="input cursor-not-allowed bg-slate-50 text-slate-500" inputmode="tel" readonly></div>
        <div class="sm:col-span-2"><label class="label">Alamat</label><textarea v-model="form.alamat" class="input" rows="3"></textarea></div>
      </div>
      <div class="mt-6 flex justify-end"><button class="btn-primary">Update</button></div>
    </form>
  </div>
</template>
