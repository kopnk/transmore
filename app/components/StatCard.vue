<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  change?: string
  icon: string
  color?: 'blue' | 'green' | 'amber'
}>()
</script>

<template>
  <div class="card p-5">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm text-slate-500">{{ label }}</p>
        <p class="mt-2 whitespace-pre-line text-3xl font-bold leading-tight">{{ value }}</p>
        <p v-if="change" class="mt-2 text-xs font-medium text-emerald-600">↗ {{ change }}</p>
      </div>
      <div
        :class="color === 'blue' ? 'bg-blue-100 text-blue-600' : color === 'amber' ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
        class="grid h-11 w-11 place-items-center rounded-xl text-xl"
      >
        {{ icon }}
      </div>
    </div>
  </div>
</template>
