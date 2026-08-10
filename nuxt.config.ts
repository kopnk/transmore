export default defineNuxtConfig({
  ssr: false,
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],
  runtimeConfig: {
    public: { apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api' }
  },
  routeRules: {
    '/api/**': { proxy: `${process.env.PHP_API_URL || 'http://127.0.0.1:8000'}/api/**` }
  },
  css: ['~/assets/css/main.css'],
  app: {
    head: {
      title: 'TransMore Operations',
      htmlAttrs: { lang: 'id' },
      meta: [
        { name: 'theme-color', content: '#0f766e' },
        { name: 'description', content: 'Sistem operasional transportasi offline-first' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' }
      ],
      link: [{ rel: 'manifest', href: '/manifest.webmanifest' }, { rel: 'icon', href: '/icon.svg' }]
    }
  },
  typescript: { strict: true, typeCheck: true }
})
