<script setup lang="ts">
interface InstallPromptEvent extends Event {
  prompt():Promise<void>
  userChoice:Promise<{outcome:'accepted'|'dismissed'}>
}

const promptEvent=shallowRef<InstallPromptEvent|null>(null)
const visible=ref(false)
const showInstructions=ref(false)
const isIos=ref(false)

const isStandalone=()=>window.matchMedia('(display-mode: standalone)').matches
  || (navigator as Navigator & {standalone?:boolean}).standalone===true

function handlePrompt(event:Event){
  event.preventDefault()
  promptEvent.value=event as InstallPromptEvent
  visible.value=!isStandalone()
}

function handleInstalled(){
  visible.value=false
  promptEvent.value=null
  sessionStorage.removeItem('pwa-install-dismissed')
}

async function install(){
  if(!promptEvent.value){showInstructions.value=true;return}
  await promptEvent.value.prompt()
  const choice=await promptEvent.value.userChoice
  promptEvent.value=null
  if(choice.outcome==='accepted')visible.value=false
  else showInstructions.value=true
}

function dismiss(){
  visible.value=false
  sessionStorage.setItem('pwa-install-dismissed','1')
}

onMounted(()=>{
  isIos.value=/iphone|ipad|ipod/i.test(navigator.userAgent)
  const isMobile=/android|iphone|ipad|ipod/i.test(navigator.userAgent)
  visible.value=isMobile&&!isStandalone()&&!sessionStorage.getItem('pwa-install-dismissed')
  window.addEventListener('beforeinstallprompt',handlePrompt)
  window.addEventListener('appinstalled',handleInstalled)
})

onBeforeUnmount(()=>{
  window.removeEventListener('beforeinstallprompt',handlePrompt)
  window.removeEventListener('appinstalled',handleInstalled)
})
</script>

<template>
  <aside v-if="visible" class="fixed inset-x-3 bottom-20 z-[80] mx-auto max-w-md rounded-2xl border border-teal-100 bg-white p-4 shadow-2xl lg:bottom-4" role="status">
    <div class="flex items-start gap-3">
      <img src="/icon-192.png" alt="" class="h-12 w-12 rounded-xl">
      <div class="min-w-0 flex-1">
        <p class="font-bold text-slate-800">Instal TransMore</p>
        <p class="mt-1 text-sm text-slate-500">Pasang aplikasi agar lebih cepat dibuka dan dapat digunakan sebagai PWA.</p>
        <p v-if="showInstructions" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800">
          {{isIos?'Tekan tombol Bagikan, lalu pilih “Tambahkan ke Layar Utama”.':'Buka menu browser (⋮), lalu pilih “Instal aplikasi” atau “Tambahkan ke layar utama”.'}}
        </p>
        <div class="mt-3 flex gap-2">
          <button type="button" class="btn-primary px-4 py-2 text-sm" @click="install">Instal</button>
          <button type="button" class="btn-secondary px-4 py-2 text-sm" @click="dismiss">Nanti</button>
        </div>
      </div>
    </div>
  </aside>
</template>
