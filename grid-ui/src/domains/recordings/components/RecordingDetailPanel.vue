<script setup lang="ts">
import { computed } from 'vue'
import { ArrowDownTrayIcon, ArrowPathIcon, MicrophoneIcon, PhoneIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Recording } from '../types/recording'

const props = defineProps<{ record: Recording | null; loading: boolean; audioLoading: boolean; audioUrl: string | null; error: string | null }>()
const emit = defineEmits<{ close: []; download: [] }>()
const size = computed(() => props.record?.content_length == null ? 'Not reported' : new Intl.NumberFormat(undefined, { style: 'unit', unit: 'kilobyte', maximumFractionDigits: 1 }).format(props.record.content_length / 1024))
const duration = computed(() => { const seconds = props.record?.duration_seconds ?? 0; return `${Math.floor(seconds / 60)}m ${seconds % 60}s` })
</script>

<template>
  <CrudSlideOver :title="record?.name ?? 'Recording details'" eyebrow="GridPBX / Recordings" description="Protected playback and approved call metadata." width="medium" @close="emit('close')">
    <div v-if="loading" class="card-surface p-10 text-center text-xs text-slate-400">Loading recording…</div>
    <div v-else-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
    <div v-else-if="record" class="grid gap-5">
      <article class="card-surface p-5"><div class="flex items-start gap-4"><span class="grid size-12 place-items-center rounded-md bg-brand-50 text-brand-600"><MicrophoneIcon class="size-6" /></span><div><h2 class="text-base font-semibold text-slate-800">{{ record.name ?? 'Call recording' }}</h2><p class="mt-1 text-xs text-slate-500">{{ new Date(record.started_at).toLocaleString() }} · {{ duration }}</p></div></div><div class="mt-5 rounded-md border border-slate-100 bg-slate-50 p-4"><div v-if="audioLoading" class="flex items-center gap-2 text-xs text-slate-500"><ArrowPathIcon class="size-4 animate-spin" />Loading protected audio…</div><audio v-else-if="audioUrl" :src="audioUrl" controls preload="metadata" class="h-10 w-full" /><p v-else class="text-xs text-slate-400">No streamable audio is available.</p><button v-if="audioUrl" type="button" :disabled="audioLoading" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-brand-600 disabled:opacity-50" @click="emit('download')"><ArrowDownTrayIcon class="size-4" />Download recording</button></div></article>
      <article class="card-surface p-5"><div class="mb-4 flex items-center gap-2"><PhoneIcon class="size-4 text-brand-500" /><h2 class="text-sm font-semibold text-slate-700">Call parties</h2></div><div class="grid gap-4 sm:grid-cols-2"><div><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Caller</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ record.caller.name ?? 'Unknown' }}</p><p class="text-xs text-slate-500">{{ record.caller.number ?? 'No number' }}</p></div><div><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Callee</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ record.callee.name ?? 'Unknown' }}</p><p class="text-xs text-slate-500">{{ record.callee.number ?? 'No number' }}</p></div></div></article>
      <div class="grid gap-4 sm:grid-cols-2"><article v-for="item in [{ label: 'Direction', value: record.direction ?? 'Unknown' }, { label: 'Content type', value: record.content_type ?? 'Unknown' }, { label: 'Size', value: size }, { label: 'Source', value: record.media_source ?? record.source_type ?? 'Unknown' }]" :key="item.label" class="card-surface p-4"><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">{{ item.label }}</p><p class="mt-2 text-sm font-semibold text-slate-700 capitalize">{{ item.value }}</p></article></div>
      <article class="card-surface p-5"><h2 class="text-sm font-semibold text-slate-700">Relationships</h2><dl class="mt-4 grid gap-3 text-xs"><div class="flex justify-between gap-4"><dt class="text-slate-400">Extension</dt><dd class="font-semibold text-slate-600">{{ record.extension?.display_name ?? 'Unresolved' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Call history</dt><dd class="font-semibold text-slate-600">{{ record.call_detail_record_id ? 'Linked' : 'Unresolved' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Call ID</dt><dd class="max-w-[65%] truncate font-mono text-slate-600">{{ record.call_id ?? 'Unknown' }}</dd></div></dl></article>
      <p class="rounded-md border border-amber-100 bg-amber-50 p-4 text-[11px] text-amber-800">Recording deletion is disabled until retention and external-storage cleanup policies are approved.</p>
    </div>
  </CrudSlideOver>
</template>
