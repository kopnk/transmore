import { toast } from 'vue-sonner'

const FALLBACK_MESSAGE = 'Terjadi kesalahan. Silakan coba lagi.'

function errorMessage(value: unknown): string {
  if (value instanceof Error && value.message.trim()) return value.message
  if (typeof value === 'string' && value.trim()) return value
  return FALLBACK_MESSAGE
}

export default defineNuxtPlugin((nuxtApp) => {
  const recent = new Map<string, number>()
  const notify = (value: unknown) => {
    const message = errorMessage(value)
    const now = Date.now()
    if (now - (recent.get(message) ?? 0) < 2000) return
    recent.set(message, now)
    toast.error(message, { description: 'Jika masalah berlanjut, periksa koneksi lalu coba kembali.' })
  }

  window.addEventListener('unhandledrejection', (event) => {
    notify(event.reason)
  })

  window.addEventListener('error', (event) => notify(event.error ?? event.message))
  window.addEventListener('offline', () => toast.warning('Koneksi internet terputus', { description: 'Sebagian fitur akan menggunakan mode offline.' }))
  window.addEventListener('online', () => toast.success('Koneksi internet tersambung kembali'))

  nuxtApp.vueApp.config.errorHandler = (error, _instance, info) => {
    console.error(`[Vue] ${info}`, error)
    notify(error)
  }
  nuxtApp.hook('app:error', notify)
})
