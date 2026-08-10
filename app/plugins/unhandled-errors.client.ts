import { toast } from 'vue-sonner'

export default defineNuxtPlugin(() => {
  window.addEventListener('unhandledrejection', (event) => {
    event.preventDefault()
    const message = event.reason instanceof Error ? event.reason.message : 'Proses gagal dijalankan'
    toast.error(message)
  })
})
