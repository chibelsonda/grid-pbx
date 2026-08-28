<script setup lang="ts">
import { computed } from 'vue'
import { ArrowPathIcon, MusicalNoteIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Media } from '../types/media'

const props = defineProps<{
  record: Media | null
  loading: boolean
  error: string | null
  audioUrl: string | null
  audioLoading: boolean
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; edit: []; replaceAudio: []; delete: [] }>()
const formattedSize = computed(() => {
  const bytes = props.record?.content_length
  if (bytes === null || bytes === undefined) return 'Not reported'
  return new Intl.NumberFormat(undefined, { style: 'unit', unit: 'kilobyte', maximumFractionDigits: 1 }).format(bytes / 1024)
})
</script>

<template>
  <CrudSlideOver :title="record?.name ?? 'Media details'" eyebrow="GridPBX / Media" description="Projected metadata, protected audio, and known dependencies." width="medium" @close="emit('close')">
    <div v-if="loading" class="card-surface p-10 text-center text-xs text-slate-400">Loading media details…</div>
    <div v-else-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
    <div v-else-if="record" class="grid gap-5">
      <article class="card-surface p-5">
        <div class="flex items-start gap-4">
          <span class="grid size-12 place-items-center rounded-md bg-brand-50 text-brand-600"><MusicalNoteIcon class="size-6" /></span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-base font-semibold text-slate-800">{{ record.name }}</h2>
              <span v-if="record.is_music_on_hold" class="rounded-full bg-violet-50 px-2.5 py-1 text-[9px] font-bold text-violet-700">MUSIC ON HOLD</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ record.description ?? 'No description' }}</p>
          </div>
        </div>
        <div class="mt-5 rounded-md border border-slate-100 bg-slate-50 p-4">
          <div v-if="audioLoading" class="flex items-center gap-2 text-xs text-slate-500"><ArrowPathIcon class="size-4 animate-spin" />Loading protected audio…</div>
          <audio v-else-if="audioUrl" :src="audioUrl" controls preload="metadata" class="h-10 w-full" />
          <p v-else class="text-xs text-slate-400">No streamable audio is available.</p>
        </div>
      </article>
      <div class="grid gap-4 sm:grid-cols-2">
        <article class="card-surface p-4"><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Source</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ record.media_source ?? 'Unknown' }}</p></article>
        <article class="card-surface p-4"><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Language</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ record.language ?? 'Not set' }}</p></article>
        <article class="card-surface p-4"><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Content type</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ record.content_type ?? 'Not reported' }}</p></article>
        <article class="card-surface p-4"><p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Size</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ formattedSize }}</p></article>
      </div>
      <article v-if="record.dependencies" class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Known dependencies</h2>
        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
          <div class="rounded-md bg-violet-50 p-3"><p class="text-lg font-semibold text-violet-700">{{ record.dependencies.music_on_hold }}</p><p class="text-[9px] font-bold text-violet-500 uppercase">Hold setting</p></div>
          <div class="rounded-md bg-blue-50 p-3"><p class="text-lg font-semibold text-blue-700">{{ record.dependencies.voicemail_greetings }}</p><p class="text-[9px] font-bold text-blue-500 uppercase">Greetings</p></div>
          <div class="rounded-md bg-amber-50 p-3"><p class="text-lg font-semibold text-amber-700">{{ record.dependencies.callflows }}</p><p class="text-[9px] font-bold text-amber-500 uppercase">Callflows</p></div>
        </div>
      </article>
      <div v-if="canManage" class="grid gap-3 border-t border-slate-200 pt-5 sm:grid-cols-3">
        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600" @click="emit('edit')"><PencilSquareIcon class="size-4" />Edit</button>
        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-600" @click="emit('replaceAudio')"><ArrowPathIcon class="size-4" />Replace audio</button>
        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-100 bg-red-50 text-xs font-semibold text-danger" @click="emit('delete')"><TrashIcon class="size-4" />Delete</button>
      </div>
    </div>
  </CrudSlideOver>
</template>
