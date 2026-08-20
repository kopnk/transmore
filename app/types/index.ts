export type PageKey = 'dashboard'|'users'|'kendaraan'|'pks'|'kebun'|'rls'|'audit-log'|'pengiriman'|'profile'|'ubah-password'
export type CrudAction = 'create'|'read'|'update'|'delete'
export type CrudPermission = Record<CrudAction, boolean>
export type Permission = Record<PageKey, CrudPermission>
export type UserRole = 'admin'|'driver'|'superadmin'
export interface User { id?:number; iduser:string; email:string; name:string; handphone:string; alamat:string; role:UserRole; status:'Aktif'|'Nonaktif'; permissions:Permission; mustChangePassword?:boolean; createdBy:string; createdAt:string; updatedBy?:string; updatedAt?:string }
export interface Kendaraan { id?:number; idkendaraan:string; noDt:string; driver:string; namaPemilik:string; tnkb:string; tahun:number; handphone:string; bank:string; rekening:string; alamat:string; status:'Aktif'|'Nonaktif'; createdBy:string; createdAt:string; updatedBy?:string; updatedAt?:string }
export interface Pks { id?:number; idpks:string; nama:string; pic:string; handphone:string; alamat:string; status?:'Aktif'|'Nonaktif'; createdBy:string; createdAt:string; updatedBy?:string; updatedAt?:string }
export interface Kebun { id?:number; idkebun:string; nama:string; pic:string; handphone:string; alamat:string; status:'Aktif'|'Nonaktif'; createdBy:string; createdAt:string; updatedBy?:string; updatedAt?:string }
export interface Transaction {
  id?: number
  idpengiriman: string
  number: string
  date: string
  kebun: string
  divisi: string
  vehicle: string
  odoStart: number
  odoEnd: number
  driver: string
  loadDate: string
  loadWeight: number
  unloadDate: string
  unloadWeight: number
  price: number
  fee: number
  receiverPic: string
  notes: string
  docLink?: string
  approved: boolean
  pks?: string
  status: 'Draft'|'Proses'|'Selesai'
  createdBy: string
  createdAt: string
  updatedBy?: string
  updatedAt?: string
}
export interface AuditLog { id?: number; action: string; module: string; detail: string; actor: string; via: string; createdAt: string }
export interface OutboxItem {
  id?: number
  createdAt: string
  status: 'pending' | 'syncing' | 'failed'
  action: 'create' | 'update' | 'delete'
  resource: string
  resourceId?: string
  payload: Record<string, unknown>
}
export interface OfflineGrantRecord { id:'current'; token:string; publicKey:string; expiresAt:string }
export interface OperationsFilters { startDate:string; endDate:string; owner:string; tnkb:string; kebun:string; divisi:string; pks:string; driver:string; receiverPic:string }
export interface FilterOption { label:string; value:string }
