export default defineNuxtPlugin(()=>{
  if(!('serviceWorker' in navigator))return
  if(import.meta.dev){
    navigator.serviceWorker.getRegistrations().then(registrations=>Promise.all(registrations.map(registration=>registration.unregister())))
    caches.keys().then(keys=>Promise.all(keys.filter(key=>key.startsWith('transmore-')).map(key=>caches.delete(key))))
    return
  }
  navigator.serviceWorker.register('/sw.js')
})
