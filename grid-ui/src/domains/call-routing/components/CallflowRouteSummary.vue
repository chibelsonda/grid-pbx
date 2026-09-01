<script setup lang="ts">
import { ArrowPathRoundedSquareIcon, HashtagIcon, LinkIcon } from '@heroicons/vue/24/outline'

defineProps<{
  nodeCount: number
  maxDepth: number
  moduleCount: number
  statusLabel: string
  statusDetail: string
}>()
</script>

<template>
  <div class="flex flex-wrap items-center gap-2" aria-label="Route summary">
    <div
      class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5"
    >
      <HashtagIcon class="size-3.5 text-blue-600" />
      <span class="text-xs font-semibold text-slate-700">{{ nodeCount }}</span>
      <span class="text-[9px] font-bold tracking-wide text-slate-500 uppercase">Nodes</span>
    </div>
    <div
      class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5"
    >
      <LinkIcon class="size-3.5 text-violet-600" />
      <span class="text-xs font-semibold text-slate-700">{{ maxDepth }}</span>
      <span class="text-[9px] font-bold tracking-wide text-slate-500 uppercase">Max depth</span>
    </div>
    <div
      class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5"
    >
      <ArrowPathRoundedSquareIcon class="size-3.5 text-emerald-600" />
      <span class="text-xs font-semibold text-slate-700">{{ moduleCount }}</span>
      <span class="text-[9px] font-bold tracking-wide text-slate-500 uppercase">Modules</span>
    </div>
    <div
      class="inline-flex min-h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1"
    >
      <ArrowPathRoundedSquareIcon class="size-3.5 shrink-0 text-brand-600" />
      <span class="text-[10px] font-semibold text-slate-700">{{ statusLabel }}</span>
      <span class="text-[9px] text-slate-500">{{ statusDetail }}</span>
    </div>
  </div>
</template>
