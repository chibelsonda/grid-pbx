<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  ChevronRightIcon,
  MagnifyingGlassIcon,
  MusicalNoteIcon,
  PlusIcon,
  SpeakerWaveIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import MediaAudioPanel from '../components/MediaAudioPanel.vue'
import MediaDeletePanel from '../components/MediaDeletePanel.vue'
import MediaDetailPanel from '../components/MediaDetailPanel.vue'
import MediaFormPanel from '../components/MediaFormPanel.vue'
import MusicOnHoldPanel from '../components/MusicOnHoldPanel.vue'
import { useMediaStore } from '../stores/mediaStore'
import type { MediaCreate, MediaUpdate } from '../types/media'

const accounts = useAccountStore()
const media = useMediaStore()
const panel = ref<'create' | 'detail' | 'edit' | 'audio' | 'delete' | 'moh' | null>(null)
const canManage = computed(() => accounts.selected?.permissions.can_manage_media ?? false)
const currentMoh = computed(() => media.records.find((record) => record.is_music_on_hold) ?? null)
const audioCount = computed(() => media.records.filter((record) => record.content_length !== null).length)
const freshness = computed(() =>
  media.sync.last_successful_at
    ? `Last synchronized ${new Date(media.sync.last_successful_at).toLocaleString()}`
    : 'Not synchronized yet',
)

watch(
  () => accounts.selectedId,
  (accountId) => {
    panel.value = null
    media.reset()
    if (accountId) void media.load(accountId, 1)
  },
  { immediate: true },
)

async function openDetail(id: string): Promise<void> {
  if (!accounts.selectedId) return
  panel.value = 'detail'
  await media.loadDetail(accounts.selectedId, id)
  if (media.detail?.streamable) await media.loadAudio(accounts.selectedId, id)
}

async function create(input: MediaCreate): Promise<void> {
  if (accounts.selectedId && (await media.create(accounts.selectedId, input))) panel.value = 'detail'
}

async function update(input: MediaUpdate): Promise<void> {
  if (accounts.selectedId && media.detail && (await media.update(accounts.selectedId, media.detail.id, input))) panel.value = 'detail'
}

async function replaceAudio(audio: File): Promise<void> {
  if (accounts.selectedId && media.detail && (await media.replaceAudio(accounts.selectedId, media.detail.id, audio))) panel.value = 'detail'
}

async function remove(): Promise<void> {
  if (accounts.selectedId && media.detail && (await media.remove(accounts.selectedId, media.detail.id))) panel.value = null
}

async function openMoh(): Promise<void> {
  if (!accounts.selectedId) return
  media.clearMutationError()
  await media.loadMohOptions(accounts.selectedId)
  panel.value = 'moh'
}

async function saveMoh(mediaId: string | null): Promise<void> {
  if (accounts.selectedId && (await media.assignMusicOnHold(accounts.selectedId, mediaId))) panel.value = null
}

function closePanel(): void {
  panel.value = null
  media.releaseAudio()
  media.clearMutationError()
}

function formatSize(bytes: number | null): string {
  if (bytes === null) return '—'
  return new Intl.NumberFormat(undefined, { style: 'unit', unit: 'kilobyte', maximumFractionDigits: 1 }).format(bytes / 1024)
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] flex-col gap-4 lg:flex-row lg:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Media & Music on Hold</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Media & Music on Hold</h1>
        <p class="mt-1 text-xs text-slate-500">Manage Switch-hosted audio through a safe metadata projection.</p>
      </div>
      <div class="flex flex-wrap gap-2 lg:ml-auto">
        <button v-if="canManage" type="button" class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm" @click="openMoh"><SpeakerWaveIcon class="size-4" />Music on hold</button>
        <button type="button" :disabled="!accounts.selectedId || media.synchronizing || !canManage" class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm disabled:opacity-40" @click="accounts.selectedId && media.synchronize(accounts.selectedId)"><ArrowPathIcon class="size-4" :class="media.synchronizing && 'animate-spin'" />Synchronize</button>
        <button v-if="canManage" type="button" class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600" @click="media.clearMutationError(); panel = 'create'"><PlusIcon class="size-4" />Upload media</button>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-3">
      <article class="card-surface flex items-center gap-4 p-4"><span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"><MusicalNoteIcon class="size-5" /></span><div><p class="text-lg font-semibold text-slate-700">{{ media.total }}</p><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Projected media</p></div></article>
      <article class="card-surface flex items-center gap-4 p-4"><span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"><SpeakerWaveIcon class="size-5" /></span><div><p class="text-lg font-semibold text-slate-700">{{ audioCount }}</p><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Audio on page</p></div></article>
      <article class="card-surface flex items-center gap-4 p-4"><span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600"><SpeakerWaveIcon class="size-5" /></span><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700">{{ currentMoh?.name ?? 'Not configured' }}</p><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Account music on hold</p></div></article>
    </div>

    <form class="mb-4 grid gap-3 sm:grid-cols-[minmax(240px,1fr)_180px_auto]" @submit.prevent="accounts.selectedId && media.load(accounts.selectedId, 1)">
      <label class="relative"><span class="sr-only">Search media</span><MagnifyingGlassIcon class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" /><input v-model="media.filters.search" type="search" placeholder="Search name, description, language…" class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs shadow-sm outline-none focus:border-brand-500" /></label>
      <select v-model="media.filters.media_source" aria-label="Media source" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 shadow-sm"><option value="">All sources</option><option value="upload">Upload</option><option value="recording">Recording</option><option value="tts">Text to speech</option></select>
      <button class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 shadow-sm">Apply filters</button>
    </form>
    <div v-if="media.error" class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ media.error }}</div>
    <div class="mb-4 flex justify-end"><span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-500">{{ freshness }}</span></div>

    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-left">
          <thead class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"><tr><th class="px-5 py-3.5">Media</th><th class="px-5 py-3.5">Source</th><th class="px-5 py-3.5">Language</th><th class="px-5 py-3.5">Audio</th><th class="px-5 py-3.5">Usage</th><th class="w-12 px-5 py-3.5"><span class="sr-only">View</span></th></tr></thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="media.loading"><td colspan="6" class="px-5 py-14 text-center text-slate-400">Loading projected media…</td></tr>
            <tr v-else-if="media.records.length === 0"><td colspan="6" class="px-5 py-14 text-center text-slate-400"><MusicalNoteIcon class="mx-auto mb-3 size-8 text-slate-300" />No media is projected for this account.</td></tr>
            <tr v-for="record in media.records" v-else :key="record.id" class="cursor-pointer hover:bg-slate-50/60" @click="openDetail(record.id)">
              <td class="px-5 py-3.5"><p class="font-semibold text-slate-700">{{ record.name }}</p><p class="mt-1 max-w-xs truncate text-[10px] text-slate-400">{{ record.description ?? 'No description' }}</p></td>
              <td class="px-5 py-3.5"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ record.media_source ?? 'unknown' }}</span></td>
              <td class="px-5 py-3.5 text-slate-500">{{ record.language ?? '—' }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ record.content_type ?? 'Unknown' }} · {{ formatSize(record.content_length) }}</td>
              <td class="px-5 py-3.5"><span v-if="record.is_music_on_hold" class="rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-bold text-violet-700">Music on hold</span><span v-else class="text-slate-400">—</span></td>
              <td class="px-5 py-3.5"><button type="button" :aria-label="`View ${record.name}`" class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600" @click.stop="openDetail(record.id)"><ChevronRightIcon class="size-4" /></button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-if="media.lastPage > 1" class="mt-4 flex items-center justify-between text-xs text-slate-500"><button :disabled="media.page <= 1" class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40" @click="accounts.selectedId && media.load(accounts.selectedId, media.page - 1)">Previous</button><span>Page {{ media.page }} of {{ media.lastPage }}</span><button :disabled="media.page >= media.lastPage" class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40" @click="accounts.selectedId && media.load(accounts.selectedId, media.page + 1)">Next</button></div>
  </div>

  <MediaFormPanel v-if="panel === 'create'" mode="create" :saving="media.mutationLoading" :error="media.mutationError" :field-errors="media.fieldErrors" @close="closePanel" @create="create" @update="() => undefined" />
  <MediaDetailPanel v-if="panel === 'detail'" :record="media.detail" :loading="media.detailLoading" :error="media.error" :audio-url="media.audioUrl" :audio-loading="media.audioLoading" :can-manage="canManage" @close="closePanel" @edit="media.clearMutationError(); panel = 'edit'" @replace-audio="media.clearMutationError(); panel = 'audio'" @delete="media.clearMutationError(); panel = 'delete'" />
  <MediaFormPanel v-if="panel === 'edit' && media.detail" mode="edit" :record="media.detail" :saving="media.mutationLoading" :error="media.mutationError" :field-errors="media.fieldErrors" @close="panel = 'detail'" @create="() => undefined" @update="update" />
  <MediaAudioPanel v-if="panel === 'audio' && media.detail" :name="media.detail.name" :saving="media.mutationLoading" :error="media.mutationError" :field-errors="media.fieldErrors" @close="panel = 'detail'" @save="replaceAudio" />
  <MediaDeletePanel v-if="panel === 'delete' && media.detail" :record="media.detail" :saving="media.mutationLoading" :error="media.mutationError" @close="panel = 'detail'" @confirm="remove" />
  <MusicOnHoldPanel v-if="panel === 'moh'" :records="media.mohOptions" :saving="media.mutationLoading" :error="media.mutationError" @close="closePanel" @save="saveMoh" />
</template>
