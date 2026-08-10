import { spawn } from 'node:child_process'
import process from 'node:process'

const children=[]
const start=(command,args)=>{
  const child=spawn(command,args,{cwd:process.cwd(),stdio:'inherit',windowsHide:true})
  children.push(child)
  child.on('error',error=>{console.error(`[dev] Gagal menjalankan ${command}:`,error.message);shutdown(1)})
  return child
}
const shutdown=(code=0)=>{
  for(const child of children)if(!child.killed)child.kill()
  process.exit(code)
}

const php=start('php',['-S','127.0.0.1:8000','-t','backend/public'])
php.on('exit',code=>{if(code&&code!==1){console.error(`[dev] Backend PHP berhenti dengan kode ${code}`);shutdown(code)}})
const nuxt=start(process.execPath,['node_modules/nuxt/bin/nuxt.mjs','dev'])
nuxt.on('exit',code=>shutdown(code??0))
process.on('SIGINT',()=>shutdown(0))
process.on('SIGTERM',()=>shutdown(0))
