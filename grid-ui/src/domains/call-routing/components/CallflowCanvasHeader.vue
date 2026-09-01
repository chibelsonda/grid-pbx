<script setup lang="ts">
import { LockClosedIcon } from '@heroicons/vue/24/outline'
</script>

<template>
  <header
    data-callflow-canvas-overlay
    class="pointer-events-none absolute inset-x-0 top-0 z-10 flex flex-wrap items-center justify-end gap-3 px-4 py-3 sm:px-6 lg:px-8"
  >
    <div class="pointer-events-auto flex flex-wrap items-center gap-2 text-[9px] font-semibold">
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
