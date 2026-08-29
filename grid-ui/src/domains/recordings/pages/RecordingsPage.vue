<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowPathIcon,
  ChevronRightIcon,
  ClockIcon,
  MicrophoneIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormInput from '@/shared/components/FormInput.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import RecordingDetailPanel from '../components/RecordingDetailPanel.vue'
import { useRecordingFilters } from '../composables/useRecordingFilters'
import { useRecordingStore } from '../stores/recordingStore'

const accounts = useAccountStore()
const recordings = useRecordingStore()
const route = useRoute()
const router = useRouter()
const { validate, validationErrors } = useRecordingFilters(() => recordings.filters)
const panelOpen = computed(
  () => recordings.detailLoading || recordings.detail !== null || recordings.detailError !== null,
)
const onPageDuration = computed(() =>
  recordings.records.reduce((sum, record) => sum + record.duration_seconds, 0),
)

watch(
  [() => accounts.selectedId, () => route.query.recording],
  ([accountId, recordingId], previous) => {
    const accountChanged = accountId !== previous?.[0]

    if (accountChanged) {
      recordings.reset()
      if (accountId) void recordings.load(accountId)
    }

    if (accountId && typeof recordingId === 'string' && recordingId !== recordings.detail?.id) {
      void recordings.loadDetail(accountId, recordingId)
    }
  },
  { immediate: true },
)

function applyFilters(): void {
  if (validate() && accounts.selectedId) void recordings.load(accounts.selectedId, 1)
}

function clearFilters(): void {
  recordings.clearFilters()
  applyFilters()
}

function openDetail(id: string): void {
  void router.replace({ query: { ...route.query, recording: id } })
}

function closeDetail(): void {
  recordings.closeDetail()
  const query = { ...route.query }
  delete query.recording
  void router.replace({ query })
}

function synchronize(): void {
  if (accounts.selectedId) void recordings.synchronize(accounts.selectedId)
}

function fieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}

function formatDuration(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return minutes > 0 ? `${minutes}m ${remainder}s` : `${remainder}s`
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-500">GridPBX / Calls</p>
        <h1 class="text-xl font-semibold text-slate-800">Recordings</h1>
        <p class="mt-1 text-xs text-slate-600">
          Metadata projection with protected, audited playback and downloads.
        </p>
      </div>
      <button
        v-if="accounts.selected?.permissions.can_sync_call_detail_records"
        type="button"
        :disabled="!accounts.selectedId || recordings.synchronizing"
        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:opacity-50 sm:ml-auto"
        @click="synchronize"
      >
        <ArrowPathIcon class="size-4" :class="recordings.synchronizing && 'animate-spin'" />
        {{
          recordings.synchronizing
            ? 'Synchronizing…'
            : `Sync last ${recordings.importWindowDays} days`
        }}
      </button>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
          <MicrophoneIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ recordings.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Projected recordings
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600">
          <ClockIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">
            {{ formatDuration(onPageDuration) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Duration on page
          </p>
        </div>
      </article>
    </div>

    <form class="mb-4 grid gap-3" novalidate @submit.prevent="applyFilters">
      <div class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_170px_170px_auto]">
        <div>
          <label class="relative block">
            <span class="sr-only">Search recordings</span>
            <SearchInput
              v-model="recordings.filters.search"
              label="Search recordings"
              placeholder="Search caller, callee, call, or name…"
              input-class="h-10 bg-white text-xs shadow-sm"
              :error="fieldError('search')"
            />
          </label>
          <p v-if="fieldError('search')" class="mt-1 text-[10px] text-danger">
            {{ fieldError('search') }}
          </p>
        </div>
        <FormSelect
          v-model="recordings.filters.direction"
          aria-label="Recording direction"
          :aria-invalid="Boolean(fieldError('direction'))"
        >
          <option value="">All directions</option>
          <option value="inbound">Inbound</option>
          <option value="outbound">Outbound</option>
        </FormSelect>
        <FormSelect
          v-model="recordings.filters.has_audio"
          aria-label="Audio availability"
          :aria-invalid="Boolean(fieldError('has_audio'))"
        >
          <option value="1">With audio</option>
          <option value="">All records</option>
          <option value="0">Without audio</option>
        </FormSelect>
        <button
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
        >
          Apply filters
        </button>
      </div>

      <DisclosureCard title="Advanced filters">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <FormInput
            v-model="recordings.filters.started_from"
            label="Start date"
            type="date"
            :error="fieldError('started_from')"
          />
          <FormInput
            v-model="recordings.filters.started_to"
            label="End date"
            type="date"
            :error="fieldError('started_to')"
          />
          <FormInput
            v-model="recordings.filters.duration_min"
            label="Minimum seconds"
            type="number"
            min="0"
            max="86400"
            :error="fieldError('duration_min')"
          />
          <FormInput
            v-model="recordings.filters.duration_max"
            label="Maximum seconds"
            type="number"
            min="0"
            max="86400"
            :error="fieldError('duration_max')"
          />
        </div>
        <div class="mt-4 flex justify-end">
          <button
            type="button"
            class="text-xs font-semibold text-slate-600 hover:text-brand-600"
            @click="clearFilters"
          >
            Clear all filters
          </button>
        </div>
      </DisclosureCard>
    </form>

    <div
      v-if="recordings.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ recordings.error }}
    </div>

    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-left">
          <thead
            class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
          >
            <tr>
              <th class="px-5 py-3.5">Started</th>
              <th class="px-5 py-3.5">Direction</th>
              <th class="px-5 py-3.5">Caller</th>
              <th class="px-5 py-3.5">Callee</th>
              <th class="px-5 py-3.5">Duration</th>
              <th class="w-12"><span class="sr-only">View</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="recordings.loading">
              <td colspan="6" class="px-5 py-14 text-center text-slate-500">Loading recordings…</td>
            </tr>
            <tr v-else-if="!recordings.records.length">
              <td colspan="6" class="px-5 py-14 text-center text-slate-500">
                No projected recordings match these filters.
              </td>
            </tr>
            <tr
              v-for="record in recordings.records"
              v-else
              :key="record.id"
              class="cursor-pointer hover:bg-slate-50"
              @click="openDetail(record.id)"
            >
              <td class="px-5 py-4 text-slate-600">
                {{ new Date(record.started_at).toLocaleString() }}
              </td>
              <td class="px-5 py-4 text-slate-600 capitalize">
                {{ record.direction ?? 'Unknown' }}
              </td>
              <td class="px-5 py-4">
                <p class="font-semibold text-slate-700">
                  {{ record.caller.name ?? record.caller.number ?? 'Unknown' }}
                </p>
                <p class="text-[10px] text-slate-500">{{ record.caller.number }}</p>
              </td>
              <td class="px-5 py-4">
                <p class="font-semibold text-slate-700">
                  {{ record.callee.name ?? record.callee.number ?? 'Unknown' }}
                </p>
                <p class="text-[10px] text-slate-500">{{ record.callee.number }}</p>
              </td>
              <td class="px-5 py-4 text-slate-600">
                {{ formatDuration(record.duration_seconds) }}
              </td>
              <td class="px-5 py-4">
                <button
                  type="button"
                  :aria-label="`View recording ${record.name ?? record.id}`"
                  class="grid size-8 place-items-center rounded text-slate-500 hover:bg-brand-50 hover:text-brand-600"
                  @click.stop="openDetail(record.id)"
                >
                  <ChevronRightIcon class="size-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="recordings.lastPage > 1"
      class="mt-4 flex items-center justify-between text-xs text-slate-600"
    >
      <button
        type="button"
        :disabled="recordings.page <= 1"
        class="rounded-md border border-slate-300 bg-white px-4 py-2 disabled:opacity-40"
        @click="accounts.selectedId && recordings.load(accounts.selectedId, recordings.page - 1)"
      >
        Previous
      </button>
      <span>Page {{ recordings.page }} of {{ recordings.lastPage }}</span>
      <button
        type="button"
        :disabled="recordings.page >= recordings.lastPage"
        class="rounded-md border border-slate-300 bg-white px-4 py-2 disabled:opacity-40"
        @click="accounts.selectedId && recordings.load(accounts.selectedId, recordings.page + 1)"
      >
        Next
      </button>
    </div>
  </div>

  <RecordingDetailPanel
    v-if="panelOpen"
    :record="recordings.detail"
    :loading="recordings.detailLoading"
    :audio-loading="recordings.audioLoading"
    :audio-url="recordings.audioUrl"
    :error="recordings.detailError"
    @close="closeDetail"
    @download="
      accounts.selectedId &&
      recordings.detail &&
      recordings.downloadAudio(accounts.selectedId, recordings.detail.id)
    "
  />
</template>
