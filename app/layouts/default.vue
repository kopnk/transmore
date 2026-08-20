<script setup lang="ts">
import {menus} from '~/utils/menu'
const auth=useAuthStore();const route=useRoute();const open=ref(false);const accountOpen=ref(false);const online=ref(true);const backendConnected=ref(false);const checking=ref(false)
const database=useDatabase();const transactionSync=useTransactionSync();const pendingCount=transactionSync.pendingCount;let connectionTimer:ReturnType<typeof setInterval>|undefined
const visibleMenus=computed(()=>menus.filter(menu=>auth.canRead(menu.key)))
const pageTitle=computed(()=>menus.find(menu=>menu.path===route.path)?.label??(route.path==='/profile'?'Profile':route.path==='/ubah-password'?'Ubah Password':'TransMore'))
const connectionStatus=computed(()=>{if(auth.offlineMode||!online.value)return{label:'Offline',color:'bg-amber-400'};if(checking.value||transactionSync.syncing.value)return{label:'Menyinkronkan...',color:'bg-blue-400'};if(!backendConnected.value)return{label:'Backend terputus',color:'bg-red-400'};if(pendingCount.value)return{label:`${pendingCount.value} menunggu sinkron`,color:'bg-amber-400'};return{label:'Online',color:'bg-emerald-400'}})
function openNewShipment(){return navigateTo({path:'/pengiriman',query:{new:String(Date.now())}})}
async function checkConnection(runSync=false){online.value=navigator.onLine;pendingCount.value=await database.db.outbox.count();if(!online.value){backendConnected.value=false;return}checking.value=true;try{await useApi().request('/health');backendConnected.value=true;if(runSync&&auth.loggedIn){await transactionSync.sync();pendingCount.value=await database.db.outbox.count()}}catch{backendConnected.value=false}finally{checking.value=false}}
const handleOnline=async()=>{online.value=true;if(!auth.loggedIn)await auth.restore(true);if(auth.loggedIn){await useLegacyDataMigration().run();await checkConnection(true)}else await checkConnection()}
const handleOffline=()=>{online.value=false;backendConnected.value=false}
onMounted(async()=>{online.value=navigator.onLine;addEventListener('online',handleOnline);addEventListener('offline',handleOffline);await database.seed();if(route.path!=='/login')await auth.restore();if(auth.loggedIn&&online.value)await useLegacyDataMigration().run();await checkConnection(auth.loggedIn);connectionTimer=setInterval(()=>checkConnection(auth.loggedIn&&pendingCount.value>0),15000)})
onBeforeUnmount(()=>{removeEventListener('online',handleOnline);removeEventListener('offline',handleOffline);if(connectionTimer)clearInterval(connectionTimer)})
</script>
<template>
<div v-if="route.path!=='/login'" class="min-h-screen lg:flex">
  <button v-if="open" type="button" class="fixed inset-0 z-50 bg-slate-950/50 lg:hidden" aria-label="Tutup menu" @click="open=false" />
  <aside :class="[open?'translate-x-0':'-translate-x-full','fixed inset-y-0 left-0 z-[60] w-60 bg-slate-950 text-white transition lg:translate-x-0']">
    <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
      <img src="/icon.png" alt="Logo TransMore" class="h-11 w-11 rounded-xl object-contain">
      <div class="min-w-0">
        <div class="font-bold">TransMore</div>
        <div class="text-xs text-slate-400">Operations & Monitoring</div>
      </div>
      <button type="button" class="ml-auto text-lg text-slate-300 transition hover:text-white lg:hidden" @click="open=false" aria-label="Tutup sidebar">✕</button>
    </div>
    <div class="flex h-[calc(100%-5rem)] flex-col overflow-hidden">
      <ClientOnly><nav class="space-y-1 p-4 overflow-y-auto"><NuxtLink v-for="menu in visibleMenus" :key="menu.key" :to="menu.path" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-slate-300 hover:bg-white/10 hover:text-white" active-class="!bg-brand-600 !text-white" @click="open=false"><span :class="['w-5 text-center text-lg', menu.iconColor]">{{menu.icon}}</span>{{menu.label}}</NuxtLink></nav><template #fallback><div class="p-4"><div v-for="i in 5" :key="i" class="mb-2 h-11 animate-pulse rounded-xl bg-white/5"></div></div></template></ClientOnly>
      <div class="mt-auto px-4 pb-4">
        <div class="rounded-xl bg-white/5 p-3 text-xs" :title="`Backend: ${backendConnected?'terhubung':'terputus'} · Antrean: ${pendingCount}`"><span :class="connectionStatus.color" class="mr-2 inline-block h-2 w-2 rounded-full"/>{{connectionStatus.label}}</div>
      </div>
    </div>
  </aside>
  <main class="min-w-0 flex-1 pb-20 lg:ml-60 lg:pb-0">
    <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b bg-white/90 px-4 py-3 backdrop-blur sm:px-8">
      <div class="min-w-0">
        <p class="text-xs font-medium text-slate-400">TransMore</p>
        <h1 class="font-bold text-slate-800 truncate">{{pageTitle}}</h1>
      </div>
      <div class="relative flex shrink-0 items-center justify-end gap-3">
        <div class="hidden text-right sm:block"><div class="truncate text-sm font-semibold">{{auth.user?.name||'Pengguna'}}</div></div>
        <button class="grid h-10 w-10 place-items-center rounded-full bg-brand-100 font-bold uppercase text-brand-700 transition hover:bg-brand-200" aria-label="Buka menu akun" @click="accountOpen=!accountOpen">{{auth.user?.name?.charAt(0)||'U'}}</button>
        <div v-if="accountOpen" class="absolute right-0 top-12 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-2 shadow-xl">
          <NuxtLink v-if="auth.canRead('profile')" to="/profile" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50" @click="accountOpen=false"><span>♙</span> Profile</NuxtLink>
          <NuxtLink v-if="auth.canRead('ubah-password')" to="/ubah-password" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50" @click="accountOpen=false"><span>⌘</span> Ubah Password</NuxtLink>
          <div class="my-1 border-t"></div><button class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50" @click="accountOpen=false;auth.logout()"><span>↪</span> Keluar</button>
        </div>
      </div>
    </header>
    <div class="p-4 sm:p-8"><slot/></div>
  </main>
  <nav :class="auth.canRead('dashboard')?'grid-cols-3':'grid-cols-2'" class="fixed inset-x-0 bottom-0 z-40 grid border-t border-slate-200 bg-white/95 px-2 pb-[max(.5rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur lg:hidden" aria-label="Navigasi utama">
    <NuxtLink v-if="auth.canRead('dashboard')" to="/" class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-xs font-semibold text-slate-500" active-class="!bg-brand-50 !text-brand-700">
      <span class="text-xl leading-none">▦</span>
      <span>Dashboard</span>
    </NuxtLink>
    <button type="button" class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-xs font-semibold text-slate-500" aria-label="Tambah pengiriman" @click="openNewShipment">
      <span class="text-2xl font-semibold leading-none">+</span>
      <span>Pengiriman</span>
    </button>
    <button type="button" class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-xs font-semibold text-slate-500" aria-label="Buka menu" @click="open=true">
      <span class="text-xl leading-none">☰</span>
      <span>Menu</span>
    </button>
  </nav>
</div>
<slot v-else/>
</template>
