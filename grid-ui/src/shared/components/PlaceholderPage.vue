<script setup lang="ts">
import { WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'

defineProps<{ title: string; description: string }>()
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1500px]">
      <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / {{ title }}</p>
      <h1 class="text-xl font-semibold tracking-tight text-slate-800">{{ title }}</h1>
      <p class="mt-1 text-xs text-slate-500">{{ description }}</p>
    </div>
  </section>
  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div class="card-surface grid min-h-80 place-items-center p-8 text-center">
      <div>
        <span
          class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-500"
        >
          <WrenchScrewdriverIcon class="size-6" />
        </span>
        <h2 class="mt-4 text-sm font-semibold text-slate-800">Scheduled for a later slice</h2>
        <p class="mx-auto mt-2 max-w-md text-xs leading-5 text-slate-500">
          This module has a bounded place in the new UI and will be connected to its Switch
          projection incrementally.
        </p>
      </div>
    </div>
  </div>
</template>
