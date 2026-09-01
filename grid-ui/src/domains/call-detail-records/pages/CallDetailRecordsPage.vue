<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowDownLeftIcon,
  ArrowUpRightIcon,
  ChevronRightIcon,
  ClockIcon,
  PhoneIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormInput from '@/shared/components/FormInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import CallDetailRecordPanel from '../components/CallDetailRecordPanel.vue'
import { useCallDetailRecordFilters } from '../composables/useCallDetailRecordFilters'
import {
  callDetailRecordDrilldownSchema,
  type CallDetailRecordDrilldown,
} from '../schemas/callDetailRecordDrilldownSchema'
import { useCallDetailRecordStore } from '../stores/callDetailRecordStore'

const accounts = useAccountStore()
const calls = useCallDetailRecordStore()
const route = useRoute()
const router = useRouter()
const { validate, validationErrors } = useCallDetailRecordFilters(() => calls.filters)
const panelOpen = computed(
  () => calls.detailLoading || calls.detail !== null || calls.detailError !== null,
)
const answeredOnPage = computed(() => calls.records.filter((record) => record.answered).length)
const inboundOnPage = computed(
  () => calls.records.filter((record) => record.direction === 'inbound').length,
)
const totalDurationOnPage = computed(() =>
  calls.records.reduce((total, record) => total + record.duration_seconds, 0),
)
const dashboardPeriod = computed<CallDetailRecordDrilldown | null>(() => {
  const result = callDetailRecordDrilldownSchema.safeParse({
    started_after: calls.filters.started_after,
    started_before: calls.filters.started_before,
    ...(calls.filters.direction ? { direction: calls.filters.direction } : {}),
    ...(calls.filters.outcome ? { outcome: calls.filters.outcome } : {}),
    ...(calls.filters.search ? { search: calls.filters.search } : {}),
    ...(calls.filters.duration_min ? { duration_min: calls.filters.duration_min } : {}),
    ...(calls.filters.duration_max ? { duration_max: calls.filters.duration_max } : {}),
  })

  return result.success ? result.data : null
})
const dashboardPeriodLabel = computed(() => {
  if (!dashboardPeriod.value) return ''

  const formatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    ...(accounts.selected?.timezone ? { timeZone: accounts.selected.timezone } : {}),
  })

  return `${formatter.format(new Date(dashboardPeriod.value.started_after))} – ${formatter.format(new Date(dashboardPeriod.value.started_before))}`
})

function routeString(value: unknown): string | undefined {
  return typeof value === 'string' ? value : undefined
}

function parseRouteDrilldown(): CallDetailRecordDrilldown | null {
  const result = callDetailRecordDrilldownSchema.safeParse({
    started_after: routeString(route.query.started_after),
    started_before: routeString(route.query.started_before),
    ...(routeString(route.query.direction)
      ? { direction: routeString(route.query.direction) }
      : {}),
    ...(routeString(route.query.outcome) ? { outcome: routeString(route.query.outcome) } : {}),
    ...(routeString(route.query.search) ? { search: routeString(route.query.search) } : {}),
    ...(routeString(route.query.duration_min)
      ? { duration_min: routeString(route.query.duration_min) }
      : {}),
    ...(routeString(route.query.duration_max)
      ? { duration_max: routeString(route.query.duration_max) }
      : {}),
  })

  return result.success ? result.data : null
}

watch(
  () => ({
    accountId: accounts.selectedId,
    recordId: routeString(route.query.cdr),
    startedAfter: routeString(route.query.started_after),
    startedBefore: routeString(route.query.started_before),
    direction: routeString(route.query.direction),
    outcome: routeString(route.query.outcome),
    search: routeString(route.query.search),
    durationMin: routeString(route.query.duration_min),
    durationMax: routeString(route.query.duration_max),
  }),
  (current, previous) => {
    const accountChanged = current.accountId !== previous?.accountId
    const drilldownChanged =
      current.startedAfter !== previous?.startedAfter ||
      current.startedBefore !== previous?.startedBefore ||
      current.direction !== previous?.direction ||
      current.outcome !== previous?.outcome ||
      current.search !== previous?.search ||
      current.durationMin !== previous?.durationMin ||
      current.durationMax !== previous?.durationMax

    if (accountChanged) calls.reset()

    if (accountChanged || drilldownChanged) {
      const drilldown = parseRouteDrilldown()
      calls.filters.started_after = drilldown?.started_after ?? ''
      calls.filters.started_before = drilldown?.started_before ?? ''
      calls.filters.direction = drilldown?.direction ?? ''
      calls.filters.outcome = drilldown?.outcome ?? ''
      calls.filters.search = drilldown?.search ?? ''
      calls.filters.duration_min = drilldown?.duration_min ?? ''
      calls.filters.duration_max = drilldown?.duration_max ?? ''

      if (current.accountId) void calls.load(current.accountId, 1)
    }

    if (current.accountId && current.recordId && current.recordId !== calls.detail?.id) {
      void calls.loadDetail(current.accountId, current.recordId)
    }
  },
  { immediate: true },
)

function applyFilters(): void {
  if (!validate() || !accounts.selectedId) return

  if (dashboardPeriod.value) {
    const query = { ...route.query }
    if (calls.filters.direction) query.direction = calls.filters.direction
    else delete query.direction
    if (calls.filters.outcome) query.outcome = calls.filters.outcome
    else delete query.outcome
    if (calls.filters.search) query.search = calls.filters.search
    else delete query.search
    if (calls.filters.duration_min) query.duration_min = calls.filters.duration_min
    else delete query.duration_min
    if (calls.filters.duration_max) query.duration_max = calls.filters.duration_max
    else delete query.duration_max

    if (
      routeString(route.query.direction) !== calls.filters.direction ||
      routeString(route.query.outcome) !== calls.filters.outcome ||
      routeString(route.query.search) !== calls.filters.search ||
      routeString(route.query.duration_min) !== calls.filters.duration_min ||
      routeString(route.query.duration_max) !== calls.filters.duration_max
    ) {
      void router.replace({ query })
      return
    }
  }

  void calls.load(accounts.selectedId, 1)
}

function clearFilters(): void {
  calls.clearFilters()
  const query = { ...route.query }
  delete query.started_after
  delete query.started_before
  delete query.direction
  delete query.outcome
  delete query.search
  delete query.duration_min
  delete query.duration_max

  if (
    route.query.started_after ||
    route.query.started_before ||
    route.query.direction ||
    route.query.outcome ||
    route.query.search ||
    route.query.duration_min ||
    route.query.duration_max
  ) {
    void router.replace({ query })
    return
  }

  applyFilters()
}

function clearDashboardPeriod(): void {
  calls.filters.started_after = ''
  calls.filters.started_before = ''
  calls.filters.direction = ''
  calls.filters.outcome = ''
  calls.filters.search = ''
  calls.filters.duration_min = ''
  calls.filters.duration_max = ''

  const query = { ...route.query }
  delete query.started_after
  delete query.started_before
  delete query.direction
  delete query.outcome
  delete query.search
  delete query.duration_min
  delete query.duration_max
  void router.replace({ query })
}

function synchronize(): void {
  if (accounts.selectedId) void calls.synchronize(accounts.selectedId)
}

function openDetail(id: string): void {
  void router.replace({ query: { ...route.query, cdr: id } })
}

function closeDetail(): void {
  calls.closeDetail()
  const query = { ...route.query }
  delete query.cdr
  void router.replace({ query })
}

function fieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}

function formatDuration(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return minutes > 0 ? `${minutes}m ${remainder}s` : `${remainder}s`
}

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Unknown'
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Call History</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Call History</h1>
        <p class="mt-1 text-xs text-slate-500">
          Searchable MySQL projection of approved Switch call-leg metadata.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <ProjectionSyncButton
          v-if="accounts.selected?.permissions.can_sync_call_detail_records"
          :synchronizing="calls.synchronizing"
          :disabled="!accounts.selectedId || calls.synchronizing"
          @sync="synchronize"
        />
        <ProjectionFreshness
          :last-synchronized-at="calls.sync.last_successful_at"
          :status="calls.sync.status"
          :detail="`Import window: ${calls.importWindowDays} days`"
        />
      </div>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
          <PhoneIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ calls.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected call legs
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600">
          <PhoneIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ answeredOnPage }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Answered on page
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-blue-50 text-blue-600">
          <ArrowDownLeftIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ inboundOnPage }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Inbound on page
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600">
          <ClockIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">
            {{ formatDuration(totalDurationOnPage) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Duration on page
          </p>
        </div>
      </article>
    </div>

    <aside
      v-if="dashboardPeriod"
      class="mb-4 flex flex-col gap-3 rounded-md border border-brand-200 bg-brand-50/60 px-4 py-3 sm:flex-row sm:items-center"
      aria-label="Active dashboard call period"
    >
      <div>
        <p class="text-xs font-semibold text-brand-700">Dashboard period</p>
        <p class="mt-0.5 text-[11px] text-slate-600">
          {{ dashboardPeriodLabel }}
          <span class="font-semibold">
            ·
            {{ dashboardPeriod.direction ? humanize(dashboardPeriod.direction) : 'All directions' }}
          </span>
          <span v-if="dashboardPeriod.search" class="font-semibold">
            · Search: {{ dashboardPeriod.search }}
          </span>
          <span v-if="dashboardPeriod.outcome" class="font-semibold">
            · {{ humanize(dashboardPeriod.outcome) }}
          </span>
          <span
            v-if="dashboardPeriod.duration_min || dashboardPeriod.duration_max"
            class="font-semibold"
          >
            · Duration {{ dashboardPeriod.duration_min ?? '0' }}–{{
              dashboardPeriod.duration_max ?? '86400'
            }}s
          </span>
        </p>
        <p class="mt-0.5 text-[10px] text-slate-500">
          Includes calls starting at the first time and before the second time.
        </p>
      </div>
      <button
        type="button"
        class="rounded-md border border-brand-200 bg-white px-3 py-2 text-xs font-semibold text-brand-700 shadow-sm hover:bg-brand-50 sm:ml-auto"
        @click="clearDashboardPeriod"
      >
        Clear dashboard period
      </button>
    </aside>

    <form class="mb-4 grid gap-3" novalidate @submit.prevent="applyFilters">
      <div class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_170px_170px_auto]">
        <div>
          <label class="relative block">
            <span class="sr-only">Search call history</span>
            <SearchInput
              v-model="calls.filters.search"
              label="Search call history"
              placeholder="Search caller, callee, call, or interaction…"
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
          v-model="calls.filters.direction"
          aria-label="Call direction"
          :aria-invalid="Boolean(fieldError('direction'))"
        >
          <option value="">All directions</option>
          <option value="inbound">Inbound</option>
          <option value="outbound">Outbound</option>
        </FormSelect>
        <FormSelect
          v-model="calls.filters.outcome"
          aria-label="Call outcome"
          :aria-invalid="Boolean(fieldError('outcome'))"
        >
          <option value="">All outcomes</option>
          <option value="answered">Answered</option>
          <option value="unanswered">Unanswered</option>
        </FormSelect>
        <button
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50"
        >
          Apply filters
        </button>
      </div>
      <DisclosureCard title="Advanced filters">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <FormInput
            v-model="calls.filters.started_from"
            label="Start date"
            type="date"
            :error="fieldError('started_from')"
          />
          <FormInput
            v-model="calls.filters.started_to"
            label="End date"
            type="date"
            :error="fieldError('started_to')"
          />
          <FormInput
            v-model="calls.filters.duration_min"
            label="Minimum seconds"
            type="number"
            min="0"
            max="86400"
            :error="fieldError('duration_min')"
          />
          <FormInput
            v-model="calls.filters.duration_max"
            label="Maximum seconds"
            type="number"
            min="0"
            max="86400"
            :error="fieldError('duration_max')"
          />
          <FormInput
            v-model="calls.filters.hangup_cause"
            label="Hangup cause"
            placeholder="NORMAL_CLEARING"
            :error="fieldError('hangup_cause')"
          />
        </div>
        <div class="mt-4 flex justify-end">
          <button
            type="button"
            class="text-xs font-semibold text-slate-500 hover:text-brand-600"
            @click="clearFilters"
          >
            Clear all filters
          </button>
        </div>
      </DisclosureCard>
    </form>

    <div
      v-if="calls.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
    >
      {{ calls.error }}
    </div>
    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-left">
          <thead
            class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
          >
            <tr>
              <th class="px-5 py-3.5">Started</th>
              <th class="px-5 py-3.5">Direction</th>
              <th class="px-5 py-3.5">Caller</th>
              <th class="px-5 py-3.5">Callee</th>
              <th class="px-5 py-3.5">Duration</th>
              <th class="px-5 py-3.5">Outcome</th>
              <th class="w-12 px-5 py-3.5"><span class="sr-only">View</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="calls.loading">
              <td colspan="7" class="px-5 py-14 text-center text-slate-400">
                Loading projected call history…
              </td>
            </tr>
            <tr v-else-if="calls.records.length === 0">
              <td colspan="7" class="px-5 py-14 text-center text-slate-400">
                <PhoneIcon class="mx-auto mb-3 size-8 text-slate-400" />No call records match the
                current filters.<br />
                <span class="mt-1 inline-block text-[11px]"
                  >Synchronize the approved import window to refresh this projection.</span
                >
              </td>
            </tr>
            <tr
              v-for="record in calls.records"
              v-else
              :key="record.id"
              class="cursor-pointer hover:bg-slate-50/60"
              @click="openDetail(record.id)"
            >
              <td class="px-5 py-3.5 text-slate-500">
                {{ new Date(record.started_at).toLocaleString() }}
              </td>
              <td class="px-5 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 font-semibold"
                  :class="record.direction === 'inbound' ? 'text-emerald-600' : 'text-blue-600'"
                >
                  <ArrowDownLeftIcon v-if="record.direction === 'inbound'" class="size-4" />
                  <ArrowUpRightIcon v-else class="size-4" />{{ humanize(record.direction) }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <p class="font-semibold text-slate-700">
                  {{ record.caller.name ?? 'Unknown caller' }}
                </p>
                <p class="mt-1 font-mono text-[10px] text-slate-400">
                  {{ record.caller.number ?? '—' }}
                </p>
              </td>
              <td class="px-5 py-3.5">
                <p class="font-semibold text-slate-700">
                  {{
                    record.callee.name ?? record.extension?.display_name ?? 'Unknown destination'
                  }}
                </p>
                <p class="mt-1 font-mono text-[10px] text-slate-400">
                  {{ record.callee.number ?? '—' }}
                </p>
              </td>
              <td class="px-5 py-3.5 font-semibold text-slate-600">
                {{ formatDuration(record.duration_seconds) }}
              </td>
              <td class="px-5 py-3.5">
                <span
                  class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                  :class="
                    record.answered
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-amber-50 text-amber-700'
                  "
                  >{{ record.answered ? 'Answered' : 'Unanswered' }}</span
                >
              </td>
              <td class="px-5 py-3.5">
                <button
                  type="button"
                  :aria-label="`View call from ${record.caller.number ?? 'unknown caller'}`"
                  class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600"
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
      v-if="calls.lastPage > 1"
      class="mt-4 flex items-center justify-between text-xs text-slate-500"
    >
      <button
        :disabled="calls.page <= 1"
        class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40"
        @click="accounts.selectedId && calls.load(accounts.selectedId, calls.page - 1)"
      >
        Previous
      </button>
      <span>Page {{ calls.page }} of {{ calls.lastPage }}</span>
      <button
        :disabled="calls.page >= calls.lastPage"
        class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40"
        @click="accounts.selectedId && calls.load(accounts.selectedId, calls.page + 1)"
      >
        Next
      </button>
    </div>
  </div>

  <CallDetailRecordPanel
    v-if="panelOpen"
    :record="calls.detail"
    :loading="calls.detailLoading"
    :error="calls.detailError"
    @close="closeDetail"
  />
</template>
