import type { CrudPermission, PageKey, Permission } from '~/types'
export const menus: {key:PageKey; label:string; path:string; icon:string; iconColor:string}[] = [
  {key:'dashboard',label:'Dashboard',path:'/',icon:'▦',iconColor:'text-sky-400'},
  {key:'pengiriman',label:'Pengiriman',path:'/pengiriman',icon:'⇄',iconColor:'text-emerald-400'},
  {key:'kendaraan',label:'Kendaraan',path:'/kendaraan',icon:'🚚',iconColor:'text-teal-400'},
  {key:'kebun',label:'Kebun',path:'/kebun',icon:'♧',iconColor:'text-lime-400'},
  {key:'pks',label:'PKS',path:'/pks',icon:'🏭',iconColor:'text-violet-400'},
  {key:'users',label:'Users',path:'/users',icon:'♙',iconColor:'text-pink-400'},
  {key:'rls',label:'RLS Matrix',path:'/rls',icon:'⊞',iconColor:'text-cyan-400'},
  {key:'audit-log',label:'Audit Log',path:'/audit-log',icon:'◴',iconColor:'text-fuchsia-400'}
]
export const permissionMenus: {key:PageKey;label:string}[] = [
  ...menus,
  {key:'profile',label:'Profile'},
  {key:'ubah-password',label:'Ubah Password'}
]
const crud = (create=false,read=true,update=false,remove=false):CrudPermission => ({create,read,update,delete:remove})
const denied = ():CrudPermission => crud(false,false,false,false)
export const superadminPermissions = ():Permission => Object.fromEntries(permissionMenus.map(menu=>[menu.key,crud(true,true,true,true)])) as Permission
export const adminPermissions = ():Permission => ({dashboard:crud(false,true,true),pengiriman:crud(true,true,true,true),kendaraan:crud(true,true,true,true),kebun:crud(true,true,true,true),pks:crud(true,true,true,true),users:crud(true,true,true,true),rls:denied(),'audit-log':crud(),profile:crud(false,true,true),'ubah-password':crud(false,true,true)})
export const driverPermissions = ():Permission => ({dashboard:denied(),pengiriman:crud(true,true,true,false),kendaraan:denied(),kebun:denied(),pks:denied(),users:denied(),rls:denied(),'audit-log':denied(),profile:crud(false,true,true),'ubah-password':crud(false,true,true)})
export const permissionsForRole = (role:'admin'|'driver'|'superadmin'):Permission => role==='superadmin'?superadminPermissions():role==='driver'?driverPermissions():adminPermissions()
export const allPermissions = superadminPermissions
