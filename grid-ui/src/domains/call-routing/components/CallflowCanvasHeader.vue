<script setup lang="ts">
import { LockClosedIcon } from '@heroicons/vue/24/outline'

defineProps<{
  description: string
}>()
</script>

<template>
  <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
    <div>
      <h3 class="text-xs font-semibold text-slate-700">Visual route map</h3>
      <p class="mt-0.5 text-[10px] text-slate-500">{{ description }}</p>
    </div>
    <div class="ml-auto flex flex-wrap items-center gap-2 text-[9px] font-semibold">
      <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-700">
        Schedule match
      </span>
      <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-blue-700">
        Menu key
      </span>
      <span class="rounded-full border border-slate-300 bg-white px-2 py-1 text-slate-600">
        Default
      </span>
      <span
        class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700"
      >
        <LockClosedIcon class="size-3" /> Preserved
      </span>
      <slot name="controls" />
    </div>
  </header>
</template>
