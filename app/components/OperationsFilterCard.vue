<script setup lang="ts">
import type { FilterOption, OperationsFilters } from '~/types'

defineProps<{
  filters:OperationsFilters
  owners:FilterOption[]
  vehicles:string[]
  kebuns:string[]
  divisions:string[]
  pks:string[]
  drivers:FilterOption[]
  receivers:string[]
}>()
defineEmits<{reset:[]}>()
</script>

<template>
  <section class="card p-5">
    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
      <div><label class="label">Awal</label><input v-model="filters.startDate" type="date" class="input" :max="filters.endDate"></div>
      <div><label class="label">Akhir</label><input v-model="filters.endDate" type="date" class="input" :min="filters.startDate"></div>
      <div><label class="label">Kendaraan</label><select v-model="filters.owner" class="input"><option value="">Semua Kendaraan</option><option v-for="option in owners" :key="option.value" :value="option.value">{{option.label}}</option></select></div>
      <div><label class="label">TNKB</label><select v-model="filters.tnkb" class="input"><option value="">Semua TNKB</option><option v-for="value in vehicles" :key="value" :value="value">{{value}}</option></select></div>
      <div><label class="label">Kebun</label><select v-model="filters.kebun" class="input"><option value="">Semua Kebun</option><option v-for="value in kebuns" :key="value" :value="value">{{value}}</option></select></div>
      <div><label class="label">Divisi</label><select v-model="filters.divisi" class="input"><option value="">Semua Divisi</option><option v-for="value in divisions" :key="value" :value="value">{{value}}</option></select></div>
      <div><label class="label">PKS</label><select v-model="filters.pks" class="input"><option value="">Semua PKS</option><option v-for="value in pks" :key="value" :value="value">{{value}}</option></select></div>
      <div><label class="label">Driver</label><select v-model="filters.driver" class="input"><option value="">Semua Driver</option><option v-for="option in drivers" :key="option.value" :value="option.value">{{option.label}}</option></select></div>
      <div><label class="label">Penerima</label><select v-model="filters.receiverPic" class="input"><option value="">Semua Penerima</option><option v-for="value in receivers" :key="value" :value="value">{{value}}</option></select></div>
      <button type="button" class="btn-secondary h-[50px] w-full" @click="$emit('reset')">Reset</button>
    </div>
  </section>
</template>
