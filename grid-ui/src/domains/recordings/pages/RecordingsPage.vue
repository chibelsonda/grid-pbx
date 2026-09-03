<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ClockIcon, MicrophoneIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import AppAlert from '@/shared/components/AppAlert.vue'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormInput from '@/shared/components/FormInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import type { RowAction } from '@/shared/components/rowAction'
import RecordingDetailPanel from '../components/RecordingDetailPanel.vue'
import { useRecordingFilters } from '../composables/useRecordingFilters'
import { useRecordingStore } from '../stores/recordingStore'
import type { Recording } from '../types/recording'

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

function recordingActions(record: Recording): RowAction[] {
  return [
    { id: 'view', label: 'View details', icon: 'view' },
    ...(record.has_audio
      ? [
          { id: 'play', label: 'Play recording', icon: 'play' as const },
          { id: 'download', label: 'Download audio', icon: 'download' as const },
        ]
      : []),
  ]
}

function handleRowAction(actionId: string, record: Recording): void {
  if (actionId === 'download' && accounts.selectedId) {
    void recordings.downloadAudio(accounts.selectedId, record.id)
  } else {
    openDetail(record.id)
  }
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
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-500">GridPBX / Calls</p>
        <h1 class="text-xl font-semibold text-slate-800">Recordings</h1>
        <p class="mt-1 text-xs text-slate-600">
          Metadata projection with protected, audited playback and downloads.
        </p>
      </div>
      <div
        v-if="accounts.selected?.permissions.can_sync_call_detail_records"
        class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end"
      >
        <ProjectionSyncButton
          :synchronizing="recordings.synchronizing"
          :disabled="!accounts.selectedId"
          @sync="synchronize"
        />
        <ProjectionFreshness
          :last-synchronized-at="recordings.sync.last_successful_at"
          :status="recordings.sync.status"
          :detail="`Import window: ${recordings.importWindowDays} days`"
        />
      </div>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
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
              live
              @search="applyFilters"
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

    <AppAlert
      v-if="recordings.error"
      :message="recordings.error"
      tone="error"
      class="mb-4"
      @dismiss="recordings.error = null"
    />

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
              <th scope="col" class="w-12" aria-label="Actions"></th>
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
              <td class="px-3 py-4 text-right">
                <RowActionMenu
                  :label="`Actions for recording ${record.name ?? record.id}`"
                  :actions="recordingActions(record)"
                  @select="handleRowAction($event, record)"
                />
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
