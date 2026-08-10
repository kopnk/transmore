import Dexie, { type EntityTable } from 'dexie'
import type {
  AuditLog,
  Kebun,
  Kendaraan,
  OutboxItem,
  OfflineGrantRecord,
  Pks,
  Transaction,
  User,
  UserRole
} from '~/types'
import { permissionsForRole } from '~/utils/menu'

class TransMoreDB extends Dexie {
  users!:EntityTable<User,'id'>;
  kendaraan!:EntityTable<Kendaraan,'id'>;
  pks!:EntityTable<Pks,'id'>;
  kebun!:EntityTable<Kebun,'id'>;
  transactions!:EntityTable<Transaction,'id'>;
  auditLogs!:EntityTable<AuditLog,'id'>;
  outbox!:EntityTable<OutboxItem,'id'>
  offlineAuth!:EntityTable<OfflineGrantRecord,'id'>
  constructor(){
    super('TransMoreDB')
    const userSchema='++id,&iduser,&email,role,status,createdAt,updatedAt'
    this.version(1).stores({users:'++id,&email,role,status,createdAt',kendaraan:'++id,&code,status,createdAt',pks:'++id,&code,status,createdAt',kebun:'++id,&code,status,createdAt',transactions:'++id,&number,date,status,createdAt',auditLogs:'++id,module,action,actor,createdAt'})
    this.version(2).stores({users:userSchema}).upgrade(async tx=>{const users=await tx.table('users').toArray();for(const[index,user]of users.entries())await tx.table('users').update(user.id,{iduser:user.iduser||`USR-${String(index+1).padStart(4,'0')}`,handphone:user.handphone||'',alamat:user.alamat||'',role:user.role==='Administrator'?'admin':user.role==='Operator'?'driver':user.role,createdBy:user.createdBy||'system@transmore.id'})})
    this.version(3).stores({users:userSchema}).upgrade(async tx=>{const users=await tx.table('users').toArray();for(const user of users)if(!/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(user.iduser||''))await tx.table('users').update(user.id,{iduser:crypto.randomUUID()})})
    this.version(4).stores({users:userSchema}).upgrade(async tx=>{const users=await tx.table('users').toArray();for(const user of users){const source=user.permissions||{};const permissions=Object.fromEntries(Object.entries(source).map(([key,value])=>[key,typeof value==='boolean'?{create:value,read:value,update:value,delete:value}:value]));await tx.table('users').update(user.id,{permissions})}})
    this.version(5).stores({users:userSchema}).upgrade(async tx=>{const users=await tx.table('users').toArray();for(const user of users)await tx.table('users').update(user.id,{permissions:permissionsForRole(user.role as UserRole)})})
    this.version(6).stores({users:userSchema})
    this.version(7).stores({users:userSchema}).upgrade(async tx=>{const drivers=await tx.table('users').where('role').equals('sopir').toArray();for(const user of drivers)await tx.table('users').update(user.id,{role:'driver',permissions:permissionsForRole('driver')})})
    this.version(8).stores({users:userSchema}).upgrade(async tx=>{await tx.table('users').toCollection().modify({mustChangePassword:false})})
    this.version(9).stores({pks:'++id,&idpks,nama,createdAt'}).upgrade(async tx=>{const rows=await tx.table('pks').toArray();for(const row of rows)await tx.table('pks').update(row.id,{idpks:crypto.randomUUID(),nama:row.nama||row.name||'',pic:row.pic||'',handphone:row.handphone||'',alamat:row.alamat||row.description||'',createdBy:row.createdBy||'database-migration',createdAt:row.createdAt||new Date().toISOString()})})
    this.version(10).stores({pks:'++id,&idpks,nama,status,createdAt'}).upgrade(async tx=>{await tx.table('pks').toCollection().modify((row)=>{row.status=row.status||'Aktif'})})
    this.version(11).stores({kebun:'++id,&idkebun,nama,status,createdAt'}).upgrade(async tx=>{const rows=await tx.table('kebun').toArray();for(const row of rows)await tx.table('kebun').update(row.id,{idkebun:crypto.randomUUID(),nama:row.nama||row.name||'',pic:row.pic||'',handphone:row.handphone||'',alamat:row.alamat||row.description||'',status:row.status||'Aktif',createdBy:row.createdBy||'database-migration',createdAt:row.createdAt||new Date().toISOString()})})
    this.version(12).stores({kendaraan:'++id,&idkendaraan,&tnkb,namaPemilik,status,createdAt'}).upgrade(async tx=>{const rows=await tx.table('kendaraan').toArray();for(const row of rows)await tx.table('kendaraan').update(row.id,{idkendaraan:crypto.randomUUID(),namaPemilik:row.namaPemilik||row.name||'',tnkb:row.tnkb||row.code||'',tahun:row.tahun||new Date().getFullYear(),handphone:row.handphone||'',bank:row.bank||'',rekening:row.rekening||'',alamat:row.alamat||row.description||'',status:row.status||'Aktif',createdBy:row.createdBy||'database-migration',createdAt:row.createdAt||new Date().toISOString()})})
    this.version(13).stores({users:userSchema}).upgrade(async tx=>{const users=await tx.table('users').toArray();for(const user of users){const previous=user.permissions as any;const permissions={...previous,pengiriman:previous?.pengiriman||previous?.transaksi||permissionsForRole(user.role).pengiriman};delete permissions.transaksi;await tx.table('users').update(user.id,{permissions})}})
    this.version(14).stores({transactions:'++id,&number,date,status,createdAt'}).upgrade(async tx=>{await tx.table('transactions').clear()})
    this.version(15).stores({transactions:'++id,&number,date,status,createdAt'}).upgrade(async tx=>{const rows=await tx.table('auditLogs').toArray();for(const row of rows)if(!('via' in row)||!row.via)await tx.table('auditLogs').update(row.id,{via:'unknown'})})
    this.version(16).stores({outbox:'++id,createdAt,status,resource'})
    // Tabel legacy dipertahankan sementara hanya untuk memindahkan data lama ke MySQL.
    // Aplikasi tidak lagi membaca tabel ini sebagai sumber data operasional.
    this.version(17).stores({users:userSchema,kendaraan:'++id,&idkendaraan,&tnkb,namaPemilik,status,createdAt',pks:'++id,&idpks,nama,status,createdAt',kebun:'++id,&idkebun,nama,status,createdAt',auditLogs:'++id,module,action,actor,createdAt'})
    this.version(18).stores({users:userSchema,kendaraan:'++id,&idkendaraan,&tnkb,namaPemilik,status,createdAt',pks:'++id,&idpks,nama,status,createdAt',kebun:'++id,&idkebun,nama,status,createdAt',auditLogs:'++id,module,action,actor,createdAt'})
    this.version(19).stores({users:userSchema,kendaraan:'++id,&idkendaraan,&tnkb,namaPemilik,status,createdAt',pks:'++id,&idpks,nama,status,createdAt',kebun:'++id,&idkebun,nama,status,createdAt',auditLogs:'++id,module,action,actor,createdAt',offlineAuth:'&id,expiresAt'})
  }
}
export const db=new TransMoreDB()
