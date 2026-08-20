<script setup lang="ts">
import dayjs from 'dayjs'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import type { Kendaraan } from '~/types'

const auth = useAuthStore()
const { db, seed, audit } = useDatabase()
const rows = ref<Kendaraan[]>([])
const show = ref(false)
const editing = ref<number>()
const search = ref('')
const saving = ref(false)
const submitMessage = ref('')
const submitFailed = ref(false)
const form = reactive({ idkendaraan: '', noDt: '', driver: '', namaPemilik: '', tnkb: '', tahun: new Date().getFullYear(), handphone: '', bank: '', rekening: '', alamat: '', status: 'Aktif' as Kendaraan['status'] })

const filtered = computed(() => rows.value.filter(row => `${row.noDt} ${row.driver} ${row.tnkb} ${row.namaPemilik} ${row.handphone}`.toLowerCase().includes(search.value.toLowerCase())))
const permission = computed(() => auth.user?.permissions.kendaraan)
const superadmin = computed(() => auth.user?.role === 'superadmin')
const canCreate = computed(() => (superadmin.value || auth.user?.role === 'admin') && (superadmin.value || permission.value?.create))
const canUpdate = computed(() => superadmin.value || permission.value?.update)
const canDelete = computed(() => superadmin.value || permission.value?.delete)

async function load() {
  await seed()
  rows.value = await db.kendaraan.toArray()
}

function open(row?: Kendaraan) {
  if (row && !canUpdate.value) return toast.error('Tidak memiliki izin update')
  if (!row && !canCreate.value) return toast.error('Tidak memiliki izin create')
  editing.value = row?.id === undefined ? undefined : Number(row.id)
  submitMessage.value = ''
  submitFailed.value = false
  Object.assign(form, row ? {
    idkendaraan: row.idkendaraan,
    noDt: row.noDt,
    driver: row.driver,
    namaPemilik: row.namaPemilik,
    tnkb: row.tnkb,
    tahun: Number(row.tahun),
    handphone: row.handphone,
    bank: row.bank,
    rekening: String(row.rekening),
    alamat: row.alamat,
    status: row.status,
  } : {
    idkendaraan: crypto.randomUUID(), noDt: '', driver: '', namaPemilik: '', tnkb: '', tahun: new Date().getFullYear(),
    handphone: '', bank: '', rekening: '', alamat: '', status: 'Aktif',
  })
  show.value = true
}

async function save() {
  if (saving.value) return
  const isEditing = editing.value !== undefined
  if (isEditing && !canUpdate.value) return toast.error('Tidak memiliki izin update')
  if (!isEditing && !canCreate.value) return toast.error('Tidak memiliki izin create')
  const maxYear = new Date().getFullYear() + 1
  const result = z.object({
    idkendaraan: z.uuid(),
    noDt: z.string().trim().min(1, 'No DT wajib diisi').max(64, 'No DT maksimal 64 karakter'),
    driver: z.string().trim().min(3, 'Nama driver minimal 3 karakter').max(255, 'Nama driver maksimal 255 karakter'),
    namaPemilik: z.string().min(3, 'Nama pemilik minimal 3 karakter'),
    tnkb: z.string().trim().toUpperCase().regex(/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{0,3}$/, 'Format TNKB tidak valid'),
    tahun: z.number().int().min(1900, 'Tahun minimal 1900').max(maxYear, `Tahun maksimal ${maxYear}`),
    handphone: z.string().min(8, 'Handphone minimal 8 digit').regex(/^[0-9+ -]+$/, 'Format handphone tidak valid'),
    bank: z.string().min(2, 'Nama bank minimal 2 karakter'), rekening: z.string().regex(/^\d{6,30}$/, 'Rekening harus 6–30 digit angka'),
    alamat: z.string().min(5, 'Alamat minimal 5 karakter'), status: z.enum(['Aktif', 'Nonaktif']),
  }).safeParse(form)
  if (!result.success) {
    submitFailed.value = true
    submitMessage.value = result.error.issues[0]?.message ?? 'Data tidak valid'
    notifyValidationErrors(result.error)
    return
  }
  const actor = auth.user?.email || 'system'
  const now = dayjs().toISOString()
  saving.value = true
  submitMessage.value = 'Menyimpan data...'
  submitFailed.value = false
  try {
    if (isEditing) {
      const old = await db.kendaraan.get(editing.value!)
      if (!old) throw new Error('Data kendaraan tidak ditemukan')
      await db.kendaraan.update(editing.value!, { ...result.data, createdBy: old.createdBy, createdAt: old.createdAt, updatedBy: actor, updatedAt: now })
    } else {
      await db.kendaraan.add({ ...result.data, createdBy: actor, createdAt: now })
    }
    await audit(isEditing ? 'UPDATE' : 'CREATE', 'Kendaraan', result.data.tnkb)
    await load()
    submitMessage.value = 'Data berhasil disimpan'
    toast.success('Data Kendaraan disimpan')
    setTimeout(() => { show.value = false }, 500)
  } catch (error) {
    submitFailed.value = true
    submitMessage.value = error instanceof Error ? error.message : 'Gagal menyimpan kendaraan'
    toast.error(submitMessage.value)
  } finally {
    saving.value = false
  }
}

async function remove(row: Kendaraan) {
  if (!canDelete.value) return toast.error('Tidak memiliki izin delete')
  if (!confirm(`Hapus ${row.tnkb}?`)) return
  try {
    await db.kendaraan.delete(Number(row.id))
    await audit('DELETE', 'Kendaraan', row.tnkb)
    await load()
    toast.success('Data Kendaraan dihapus')
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Gagal menghapus kendaraan')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6 flex justify-between">
      <div><h2 class="text-2xl font-bold">Data Kendaraan</h2><p class="text-sm text-slate-500">Kelola kendaraan dan informasi pemilik.</p></div>
      <button v-if="canCreate" class="btn-primary" @click="open()">＋ Kendaraan</button>
    </div>
    <div class="card overflow-hidden">
      <div class="border-b p-4"><input v-model="search" class="input max-w-sm" placeholder="Cari No DT, driver, TNKB, pemilik, atau handphone..."></div>
      <div class="overflow-x-auto"><table class="min-w-[1550px] w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-400"><tr><th v-for="h in ['No','Nama Pemilik','TNKB','No DT','Driver','Handphone','Rekening','Alamat','Status','Aksi']" :key="h" class="px-5 py-3">{{ h }}</th></tr></thead>
        <tbody><tr v-for="(row, idx) in filtered" :key="row.id" class="border-t align-top hover:bg-slate-50">
          <td class="max-w-52 break-all px-5 py-4 font-mono text-xs">{{ idx + 1 }}</td><td class="px-5 py-4 font-semibold">{{ row.namaPemilik }}</td>
          <td class="px-5 py-4 font-bold"><div>{{ row.tnkb }}</div><div class="text-xs text-slate-500">{{ row.tahun }}</div></td><td class="px-5 py-4 font-semibold">{{ row.noDt }}</td><td class="px-5 py-4">{{ row.driver }}</td><td class="px-5 py-4">{{ row.handphone }}</td>
          <td class="px-5 py-4"><div class="font-mono">{{ row.rekening }}</div><div class="text-xs text-slate-500">{{ row.bank }}</div></td><td class="px-5 py-4">{{ row.alamat }}</td>
          <td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs">{{ row.status }}</span></td>
          <td class="px-5 py-4"><div class="mb-3 whitespace-nowrap text-xs text-slate-400"><p><b>Created:</b> {{ row.createdBy }}</p><p>{{ dayjs(row.createdAt).format('DD/MM/YYYY HH:mm:ss') }}</p><template v-if="row.updatedAt"><p class="mt-2"><b>Updated:</b> {{ row.updatedBy }}</p><p>{{ dayjs(row.updatedAt).format('DD/MM/YYYY HH:mm:ss') }}</p></template></div><div class="flex gap-3"><button v-if="canUpdate" class="font-medium text-ocean-600" @click="open(row)">Update</button><button v-if="canDelete" class="font-medium text-red-500" @click="remove(row)">Delete</button><span v-if="!canUpdate&&!canDelete" class="text-xs text-slate-400">Hanya lihat</span></div></td>
        </tr><tr v-if="!filtered.length"><td colspan="10" class="p-10 text-center text-slate-400">Belum ada data Kendaraan</td></tr></tbody>
      </table></div>
    </div>
    <div v-if="show" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50 p-4">
      <form class="card w-full max-w-3xl p-6" @submit.prevent="save">
        <h3 class="mb-5 text-xl font-bold">{{ editing !== undefined ? 'Update' : 'Tambah' }} Kendaraan</h3>
        <div class="grid gap-4 sm:grid-cols-2"><input v-model="form.idkendaraan" type="hidden"><input v-model="form.namaPemilik" class="input" placeholder="Nama pemilik"><input v-model.trim="form.tnkb" class="input uppercase" placeholder="BM 1234 XX"><input v-model.trim="form.noDt" class="input" placeholder="No DT"><input v-model.trim="form.driver" class="input" placeholder="Nama driver"><input v-model.number="form.tahun" type="number" class="input" placeholder="Tahun"><input v-model="form.handphone" class="input" placeholder="Handphone"><input v-model="form.bank" class="input" placeholder="Bank"><input v-model="form.rekening" class="input" inputmode="numeric" placeholder="Nomor rekening"><select v-model="form.status" class="input"><option>Aktif</option><option>Nonaktif</option></select><textarea v-model="form.alamat" class="input sm:col-span-2" placeholder="Alamat"></textarea></div>
        <p v-if="submitMessage" class="mt-4 rounded-lg px-4 py-3 text-sm" :class="submitFailed ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">{{ submitMessage }}</p>
        <div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-secondary" :disabled="saving" @click="show=false">Batal</button><button type="submit" class="btn-primary" :disabled="saving">{{ saving ? (editing !== undefined ? 'Mengupdate...' : 'Menyimpan...') : (editing !== undefined ? 'Update' : 'Simpan') }}</button></div>
      </form>
    </div>
  </div>
</template>
