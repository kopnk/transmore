<script setup lang="ts">
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { identifierSchema } from '~/utils/identifier'

definePageMeta({ layout:'default' })
const auth=useAuthStore()
const identifier=ref('')
const password=ref('')
const showPassword=ref(false)
const online=ref(true)
const setOnlineStatus=()=>{online.value=navigator.onLine}
onMounted(()=>{setOnlineStatus();addEventListener('online',setOnlineStatus);addEventListener('offline',setOnlineStatus)})
onBeforeUnmount(()=>{removeEventListener('online',setOnlineStatus);removeEventListener('offline',setOnlineStatus)})

async function submit(){
  const result=z.object({identifier:identifierSchema,password:z.string().min(6)}).safeParse({identifier:identifier.value,password:password.value})
  if(!result.success)return toast.error(result.error.issues[0]?.message ?? 'Data login tidak valid')
  try {
    await auth.login(result.data.identifier,password.value)
    toast.success(auth.offlineMode?'Masuk offline — Pengiriman lokal':'Selamat datang kembali')
    navigateTo('/')
  } catch(error) {
    toast.error(error instanceof Error?error.message:'Login gagal')
  }
}
async function submitOffline(){const result=identifierSchema.safeParse(identifier.value);if(!result.success)return toast.error(result.error.issues[0]?.message??'Identifier tidak valid');try{await auth.loginOffline(result.data);toast.success('Masuk offline — Pengiriman lokal');navigateTo('/pengiriman')}catch(error){toast.error(error instanceof Error?error.message:'Login offline gagal')}}
</script>

<template>
  <main class="relative flex min-h-[100dvh] items-center justify-center overflow-hidden bg-gradient-to-br from-blue-800 via-blue-600 to-teal-700 px-4 py-14 sm:px-5 sm:py-16">
    <div class="absolute left-5 top-4 font-bold text-white sm:left-10 sm:top-6">TransMore</div>

    <form class="w-full max-w-md rounded-2xl border border-white/30 bg-white p-5 shadow-2xl sm:p-7" @submit.prevent="submit">
      <div class="mb-5 sm:mb-6">
        <p class="text-xs font-bold uppercase tracking-wide text-brand-600">Selamat Datang</p>
        <h1 class="mt-1.5 text-2xl font-bold text-slate-900 sm:text-3xl">Masuk ke akun Anda</h1>
      </div>

      <label class="label">Username</label>
      <input v-model="identifier" class="input mb-4" type="text" inputmode="email" autocomplete="username" placeholder="nama@email.com atau 08123456789">

      <label class="label">Password</label>
      <div class="relative mb-5">
        <input v-model="password" class="input pr-12" :type="showPassword?'text':'password'" autocomplete="current-password">
        <button type="button" class="absolute inset-y-0 right-0 grid w-12 place-items-center text-slate-400 transition hover:text-brand-600" :aria-label="showPassword?'Sembunyikan password':'Tampilkan password'" @click="showPassword=!showPassword">
          <svg v-if="!showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
          <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 3 18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 4.2A10.7 10.7 0 0 1 12 4c6.5 0 10 8 10 8a18 18 0 0 1-2.1 3.2M6.6 6.6C3.6 8.7 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 4.1-.9"/></svg>
        </button>
      </div>

      <button class="btn-primary w-full" type="submit">Masuk</button>
      <button v-if="!online" class="btn-secondary mt-3 w-full" type="button" @click="submitOffline">Masuk Offline</button>
      <p v-if="!online" class="mt-3 text-center text-xs text-amber-600">Mode offline hanya mengizinkan operasional Pengiriman lokal.</p>
    </form>

    <p class="absolute bottom-6 left-6 text-xs text-blue-100 sm:bottom-8 sm:left-10">© 2026 TransMore Operations</p>
  </main>
</template>

<style scoped>
main > p:last-child {
  bottom: 1rem;
  left: 1.25rem;
}

@media (min-width: 640px) {
  main > p:last-child {
    bottom: 1.5rem;
    left: 2.5rem;
  }
}
</style>
