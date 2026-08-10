<script setup lang="ts">
import dayjs from 'dayjs'
import { toast } from 'vue-sonner'

const { db, seed } = useDatabase()
const rows = ref<any[]>([])
const search = ref('')
const selected = ref<number[]>([])
const deleting = ref(false)
const filtered = computed(() => rows.value.filter(row => JSON.stringify(row).toLowerCase().includes(search.value.toLowerCase())))
const allVisibleSelected = computed(() => filtered.value.length > 0 && filtered.value.every(row => selected.value.includes(row.id!)))

async function load() {
  await seed()
  rows.value = await db.auditLogs.reverse().toArray()
  selected.value = []
}
function toggleAllSelected() {
  selected.value = allVisibleSelected.value ? [] : filtered.value.map(row => row.id!).filter(Boolean)
}
async function deleteSelected() {
  const total = selected.value.length
  if (!total || !confirm(`Hapus ${total} catatan audit terpilih? Tindakan ini tidak dapat dibatalkan.`)) return
  deleting.value = true
  try {
    await db.auditLogs.where('id').anyOf(selected.value).delete()
    await load()
    toast.success(`${total} catatan audit berhasil dihapus`)
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Catatan audit gagal dihapus')
  } finally {
    deleting.value = false
  }
}
onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6"><h2 class="text-2xl font-bold">Audit Log</h2><p class="text-sm text-slate-500">Riwayat perubahan data dan hak akses.</p></div>
    <div class="card overflow-hidden">
      <div class="flex flex-col gap-4 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
        <input v-model="search" class="input max-w-sm" placeholder="Cari aktivitas...">
        <button v-if="selected.length" class="btn-secondary text-red-600" :disabled="deleting" @click="deleteSelected">{{ deleting ? 'Menghapus...' : `Hapus ${selected.length} terpilih` }}</button>
      </div>
      <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-400"><tr><th class="px-6 py-3"><input type="checkbox" :checked="allVisibleSelected" @change="toggleAllSelected"></th><th v-for="header in ['Waktu Lokal','Aktor','Modul','Aksi','Detail','Via']" :key="header" class="px-6 py-3">{{ header }}</th></tr></thead>
        <tbody><tr v-for="row in filtered" :key="row.id" class="border-t"><td class="px-6 py-4"><input v-model="selected" type="checkbox" :value="row.id"></td><td class="whitespace-nowrap px-6 py-4">{{ dayjs(row.createdAt).format('DD/MM/YYYY HH:mm:ss') }}</td><td class="px-6 py-4">{{ row.actor }}</td><td class="px-6 py-4 font-medium">{{ row.module }}</td><td class="px-6 py-4"><span class="rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ row.action }}</span></td><td class="px-6 py-4 text-slate-500">{{ row.detail }}</td><td class="px-6 py-4 text-xs text-slate-600">{{ row.via || 'unknown' }}</td></tr><tr v-if="!filtered.length"><td colspan="7" class="p-10 text-center text-slate-400">Belum ada aktivitas tercatat</td></tr></tbody>
      </table></div>
    </div>
  </div>
</template>
