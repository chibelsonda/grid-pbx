<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowDownLeftIcon,
  ArrowPathIcon,
  ArrowUpRightIcon,
  BellAlertIcon,
  BoltIcon,
  ChartBarIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  ClockIcon,
  CloudArrowDownIcon,
  DevicePhoneMobileIcon,
  ExclamationTriangleIcon,
  PhoneIcon,
  QueueListIcon,
  SignalIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import CircularCountBadge from '@/shared/components/CircularCountBadge.vue'
import StatCard from '@/shared/components/StatCard.vue'
import CallGeographyPanel from '../components/CallGeographyPanel.vue'
import CallInsightsPanel from '../components/CallInsightsPanel.vue'
import CallQualityPanel from '../components/CallQualityPanel.vue'
import RecentMissedCallsPanel from '../components/RecentMissedCallsPanel.vue'
import { useDashboardCallActivity } from '../composables/useDashboardCallActivity'
import { useDashboardCallGeography } from '../composables/useDashboardCallGeography'
import { useDashboardCallQuality } from '../composables/useDashboardCallQuality'
import { useDashboardRecentMissedCalls } from '../composables/useDashboardRecentMissedCalls'
import { useDashboardOverview } from '../composables/useDashboardOverview'
import { useDashboardTopDestinations } from '../composables/useDashboardTopDestinations'
import type { CallActivityPoint, CallActivityRange } from '../schemas/callActivityTrendSchema'
import type { DashboardAttentionItem } from '../schemas/dashboardOverviewSchema'

const accounts = useAccountStore()
const dashboard = useDashboardOverview()
const activity = useDashboardCallActivity()
const geography = useDashboardCallGeography()
const quality = useDashboardCallQuality()
const missedCalls = useDashboardRecentMissedCalls()
const topDestinations = useDashboardTopDestinations()
const activityDirection = ref<'' | 'inbound' | 'outbound'>('')
const activityRanges: Array<{ value: CallActivityRange; label: string }> = [
  { value: 'today', label: 'Today' },
  { value: '7d', label: '7 days' },
  { value: '30d', label: '30 days' },
]
const activityRangeLabel = computed(
  () => activityRanges.find((option) => option.value === activity.range.value)?.label ?? '7 days',
)

const stats = computed(() => {
  const overview = dashboard.overview.value
  if (!overview) return []

  return [
    {
      label: 'Calls today',
      value: String(overview.calls_today.total),
      detail: `${overview.calls_today.inbound} inbound · ${overview.calls_today.outbound} outbound`,
      icon: PhoneIcon,
      tone: 'primary' as const,
    },
    {
      label: 'Answer rate',
      value: `${overview.calls_today.answer_rate}%`,
      detail: `${overview.calls_today.answered} answered · ${overview.calls_today.missed} missed`,
      icon: SignalIcon,
      tone: 'success' as const,
    },
    {
      label: 'Registered devices',
      value: `${overview.inventory.devices.registered}/${overview.inventory.devices.total}`,
      detail:
        overview.inventory.devices.unknown_registration > 0
          ? `${overview.inventory.devices.unknown_registration} registration states unknown`
          : 'All registration states observed',
      icon: DevicePhoneMobileIcon,
      tone: 'info' as const,
    },
    {
      label: 'Requires attention',
      value: String(overview.attention.total),
      detail:
        overview.attention.total === 0
          ? 'No projected issues detected'
          : 'Review the operational guidance below',
      icon: BellAlertIcon,
      tone: 'warning' as const,
    },
  ]
})

const inventory = computed(() => {
  const overview = dashboard.overview.value
  if (!overview) return []

  return [
    {
      label: 'People & Extensions',
      value: overview.inventory.extensions.total,
      detail: `${overview.inventory.extensions.enabled} enabled · ${overview.inventory.extensions.disabled} disabled`,
      route: 'extensions',
      icon: UserGroupIcon,
      tone: 'bg-brand-50 text-brand-600',
    },
    {
      label: 'Devices',
      value: overview.inventory.devices.total,
      detail: `${overview.inventory.devices.registered} registered · ${overview.inventory.devices.unregistered} unregistered`,
      route: 'devices',
      icon: DevicePhoneMobileIcon,
      tone: 'bg-sky-50 text-sky-600',
    },
    {
      label: 'Phone Numbers',
      value: overview.inventory.phone_numbers.total,
      detail: `${overview.inventory.phone_numbers.assigned} assigned · ${overview.inventory.phone_numbers.unassigned} unassigned`,
      route: 'phone-numbers',
      icon: PhoneIcon,
      tone: 'bg-violet-50 text-violet-600',
    },
    {
      label: 'Callflows',
      value: overview.inventory.callflows.total,
      detail: `${overview.inventory.callflows.healthy} healthy · ${overview.inventory.callflows.attention} need attention`,
      route: 'call-routing',
      icon: QueueListIcon,
      tone: 'bg-emerald-50 text-emerald-600',
    },
    {
      label: 'Voicemail',
      value: overview.inventory.voicemail.boxes,
      detail: `${overview.inventory.voicemail.new_messages} new messages`,
      route: 'voicemail',
      icon: BellAlertIcon,
      tone: 'bg-fuchsia-50 text-fuchsia-600',
    },
    {
      label: 'Queues',
      value: overview.inventory.queues.total,
      detail: 'Projected call distribution queues',
      route: 'queues',
      icon: QueueListIcon,
      tone: 'bg-amber-50 text-amber-600',
    },
  ]
})

const freshnessLabel = computed(() => {
  const timestamp = dashboard.overview.value?.data_as_of
  if (!timestamp) return 'No successful synchronization yet'

  return `Data as of ${new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(timestamp))}`
})

const syncLabel = computed(() => {
  const status = dashboard.overview.value?.synchronization.status

  return {
    healthy: 'Projection healthy',
    syncing: 'Synchronization running',
    attention: 'Projection needs refresh',
    error: 'Synchronization failed',
    not_started: 'Synchronization not started',
  }[status ?? 'not_started']
})

const syncTone = computed(() => {
  const status = dashboard.overview.value?.synchronization.status
  if (status === 'healthy') return 'border-emerald-100 bg-emerald-50 text-emerald-700'
  if (status === 'syncing') return 'border-sky-100 bg-sky-50 text-sky-700'
  if (status === 'error') return 'border-red-100 bg-red-50 text-red-700'
  return 'border-amber-100 bg-amber-50 text-amber-800'
})

const attentionRoute: Record<string, string> = {
  devices: 'devices',
  'phone-numbers': 'phone-numbers',
  'call-routing': 'call-routing',
  'system-status': 'system-status',
}

const activityBars = computed(() => {
  const trend = activity.activity.value
  if (!trend) return []
  const maximum = Math.max(...trend.series.map((point) => point.total), 1)

  return trend.series.map((point, index) => ({
    ...point,
    height: point.total === 0 ? '2px' : `${Math.max(6, (point.total / maximum) * 100)}%`,
    inboundShare: point.total === 0 ? '0%' : `${(point.inbound / point.total) * 100}%`,
    outboundShare: point.total === 0 ? '0%' : `${(point.outbound / point.total) * 100}%`,
    label: activityLabel(point, index, trend.series.length),
    title: activityTitle(point),
  }))
})

const activityChartMinimumWidth = computed(() => {
  if (activity.range.value === '30d') return 'min-w-[860px]'
  if (activity.range.value === 'today') return 'min-w-[720px]'
  return 'min-w-[520px]'
})

watch(
  () => accounts.selectedId,
  async (accountId) => {
    if (!accountId) {
      dashboard.reset()
      activity.reset()
      geography.reset()
      quality.reset()
      missedCalls.reset()
      topDestinations.reset()

      return
    }

    const scrollPosition = window.scrollY
    await Promise.all([
      dashboard.load(accountId),
      activity.load(accountId),
      geography.load(accountId, activity.range.value),
      quality.load(accountId, activity.range.value),
      missedCalls.load(accountId, activity.range.value),
      topDestinations.load(accountId, activity.range.value),
    ])

    if (accounts.selectedId === accountId) window.scrollTo({ top: scrollPosition })
  },
  { immediate: true },
)

function reload(): void {
  if (!accounts.selectedId) return
  void dashboard.load(accounts.selectedId)
  void activity.load(accounts.selectedId)
  void geography.load(accounts.selectedId, activity.range.value)
  void quality.load(accounts.selectedId, activity.range.value)
  void missedCalls.load(accounts.selectedId, activity.range.value)
  void topDestinations.load(accounts.selectedId, activity.range.value)
}

function setActivityRange(range: CallActivityRange): void {
  if (accounts.selectedId && range !== activity.range.value) {
    void activity.load(accounts.selectedId, range)
    void geography.load(accounts.selectedId, range)
    void quality.load(accounts.selectedId, range)
    void missedCalls.load(accounts.selectedId, range)
    void topDestinations.load(accounts.selectedId, range)
  }
}

function formatDuration(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60

  return minutes > 0 ? `${minutes}m ${remainder}s` : `${remainder}s`
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function attentionTone(item: DashboardAttentionItem): string {
  if (item.severity === 'danger') return 'border-red-100 bg-red-50 text-red-700'
  if (item.severity === 'warning') return 'border-amber-100 bg-amber-50 text-amber-800'
  return 'border-sky-100 bg-sky-50 text-sky-700'
}

function activityLabel(point: CallActivityPoint, index: number, count: number): string {
  const trend = activity.activity.value
  if (!trend) return ''

  if (trend.range === 'today') {
    if (index % 4 !== 0 && index !== count - 1) return ''
    return new Intl.DateTimeFormat(undefined, {
      hour: 'numeric',
      timeZone: trend.timezone,
    }).format(new Date(point.start_at))
  }

  if (trend.range === '30d' && index % 5 !== 0 && index !== count - 1) return ''

  return new Intl.DateTimeFormat(undefined, {
    month: trend.range === '30d' ? 'short' : undefined,
    day: 'numeric',
    weekday: trend.range === '7d' ? 'short' : undefined,
    timeZone: trend.timezone,
  }).format(new Date(point.start_at))
}

function activityTitle(point: CallActivityPoint): string {
  const trend = activity.activity.value
  if (!trend) return ''
  const period = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    ...(trend.granularity === 'hour' ? { timeStyle: 'short' as const } : {}),
    timeZone: trend.timezone,
  }).format(new Date(point.start_at))

  return `${period}: ${point.total} calls, ${point.inbound} inbound, ${point.outbound} outbound`
}

function activityDrilldownRoute(point: CallActivityPoint) {
  return {
    name: 'call-history',
    query: {
      started_after: point.start_at,
      started_before: point.end_at,
      ...(activityDirection.value ? { direction: activityDirection.value } : {}),
    },
  }
}

function activityDrilldownLabel(point: CallActivityPoint): string {
  const scope = activityDirection.value || 'all'
  const count = activityDirection.value ? point[activityDirection.value] : point.total

  return `${activityTitle(point)}. View ${count} ${scope} calls in Call History.`
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Dashboard</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">
          {{ accounts.selected?.name ?? 'Operational dashboard' }}
        </h1>
        <p class="mt-1 text-xs text-slate-500">
          Account health, projected inventory, and today's call activity.
        </p>
      </div>
      <button
        type="button"
        :disabled="!accounts.selectedId || dashboard.loading.value || activity.loading.value"
        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600 disabled:opacity-50 sm:ml-auto"
        @click="reload"
      >
        <ArrowPathIcon
          class="size-4"
          :class="(dashboard.loading.value || activity.loading.value) && 'animate-spin'"
        />
        Refresh overview
      </button>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div
      v-if="dashboard.error.value"
      class="mb-5 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-red-700"
    >
      {{ dashboard.error.value }}
    </div>

    <div
      v-if="dashboard.loading.value && !dashboard.overview.value"
      class="card-surface grid min-h-72 place-items-center p-8 text-center text-xs text-slate-500"
    >
      <div>
        <ArrowPathIcon class="mx-auto size-8 animate-spin text-brand-500" />
        <p class="mt-3">Loading the operational overview…</p>
      </div>
    </div>

    <div
      v-else-if="!accounts.selectedId"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <CloudArrowDownIcon class="mx-auto size-10 text-slate-400" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Select a Switch account</h2>
        <p class="mt-2 text-xs text-slate-500">
          The dashboard is scoped to the active account and its authorized projections.
        </p>
      </div>
    </div>

    <template v-else-if="dashboard.overview.value">
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold"
          :class="syncTone"
        >
          <ArrowPathIcon
            v-if="dashboard.overview.value.synchronization.status === 'syncing'"
            class="size-3.5 animate-spin"
          />
          <CheckCircleIcon
            v-else-if="dashboard.overview.value.synchronization.status === 'healthy'"
            class="size-3.5"
          />
          <ExclamationTriangleIcon v-else class="size-3.5" />
          {{ syncLabel }}
        </span>
        <span class="text-[11px] font-medium text-slate-500">{{ freshnessLabel }}</span>
        <span class="text-[11px] text-slate-400">
          · {{ dashboard.overview.value.account.timezone }}
        </span>
      </div>

      <section
        class="card-surface mb-5 overflow-hidden border-brand-100 bg-gradient-to-r from-brand-50/90 via-white to-white"
        aria-labelledby="dashboard-quick-actions"
      >
        <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center">
          <div class="flex items-center gap-3">
            <BoltIcon class="size-5 shrink-0 text-brand-600" aria-hidden="true" />
            <div>
              <h2 id="dashboard-quick-actions" class="text-sm font-semibold text-slate-800">
                Quick actions
              </h2>
              <p class="mt-0.5 text-[11px] text-slate-500">Start a common account workflow.</p>
            </div>
          </div>
          <div class="grid gap-2 sm:grid-cols-3 lg:ml-auto lg:flex">
            <RouterLink
              v-if="accounts.selected?.permissions.can_manage_extensions"
              :to="{ name: 'extensions', query: { create: '1' } }"
              class="inline-flex h-9 items-center justify-center rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
            >
              Create extension
            </RouterLink>
            <RouterLink
              v-if="accounts.selected?.permissions.can_manage_devices"
              :to="{ name: 'device-create' }"
              class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
            >
              Add device
            </RouterLink>
            <RouterLink
              v-if="accounts.selected?.permissions.can_manage_call_routing"
              :to="{ name: 'call-routing', query: { create: '1' } }"
              class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
            >
              Create callflow
            </RouterLink>
          </div>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
      </section>

      <section class="card-surface mt-6 overflow-hidden">
        <header
          class="flex flex-col gap-4 border-b border-slate-200/80 px-5 py-4 lg:flex-row lg:items-center"
        >
          <div class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
              <ChartBarIcon class="size-4.5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-800">Call activity trend</h2>
              <p class="text-[11px] text-slate-500">
                Inbound and outbound calls in the account timezone
              </p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2 lg:ml-auto">
            <FormSelect
              v-model="activityDirection"
              aria-label="Call history drill-down direction"
              class="h-9 min-w-40 bg-white text-[11px]"
            >
              <option value="">Open all calls</option>
              <option value="inbound">Open inbound calls</option>
              <option value="outbound">Open outbound calls</option>
            </FormSelect>
            <div
              class="inline-flex w-fit rounded-md border border-slate-200 bg-slate-50 p-1"
              aria-label="Call activity range"
            >
              <button
                v-for="option in activityRanges"
                :key="option.value"
                type="button"
                :aria-pressed="activity.range.value === option.value"
                :disabled="activity.loading.value"
                class="h-7 rounded px-3 text-[11px] font-semibold transition disabled:opacity-50"
                :class="
                  activity.range.value === option.value
                    ? 'bg-white text-brand-600 shadow-sm ring-1 ring-slate-200'
                    : 'text-slate-500 hover:text-slate-700'
                "
                @click="setActivityRange(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>
        </header>

        <div
          v-if="activity.error.value"
          class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-red-700"
        >
          {{ activity.error.value }}
        </div>

        <div
          v-if="activity.loading.value && !activity.activity.value"
          class="grid min-h-72 place-items-center text-xs text-slate-500"
        >
          <div class="text-center">
            <ArrowPathIcon class="mx-auto size-7 animate-spin text-brand-500" />
            <p class="mt-2">Loading call activity…</p>
          </div>
        </div>

        <template v-else-if="activity.activity.value">
          <div
            class="grid border-b border-slate-200/80 sm:grid-cols-4 sm:divide-x sm:divide-slate-200/80"
          >
            <div class="px-5 py-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Total calls
              </p>
              <p class="mt-1 text-lg font-semibold text-slate-800">
                {{ activity.activity.value.totals.total }}
              </p>
            </div>
            <div class="px-5 py-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Inbound
              </p>
              <p class="mt-1 text-lg font-semibold text-sky-600">
                {{ activity.activity.value.totals.inbound }}
              </p>
            </div>
            <div class="px-5 py-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Outbound
              </p>
              <p class="mt-1 text-lg font-semibold text-violet-600">
                {{ activity.activity.value.totals.outbound }}
              </p>
            </div>
            <div class="px-5 py-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Answer rate
              </p>
              <p class="mt-1 text-lg font-semibold text-emerald-600">
                {{ activity.activity.value.totals.answer_rate }}%
              </p>
            </div>
          </div>

          <div class="overflow-x-auto px-5 pt-5 pb-4">
            <div :class="activityChartMinimumWidth">
              <div class="relative h-52 border-b border-slate-200">
                <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                  <span
                    v-for="line in 5"
                    :key="line"
                    class="border-t border-dashed border-slate-100"
                  />
                </div>
                <div class="absolute inset-0 flex items-end gap-2 px-1">
                  <RouterLink
                    v-for="bar in activityBars"
                    :key="bar.start_at"
                    :to="activityDrilldownRoute(bar)"
                    :aria-label="activityDrilldownLabel(bar)"
                    class="group relative flex h-full min-w-0 flex-1 flex-col justify-end rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                  >
                    <div
                      class="relative mx-auto flex w-full max-w-8 flex-col-reverse overflow-hidden rounded-t bg-slate-200 transition group-hover:brightness-95"
                      :style="{ height: bar.height }"
                      :title="bar.title"
                    >
                      <span
                        class="w-full shrink-0 bg-sky-500"
                        :style="{ height: bar.inboundShare }"
                      />
                      <span
                        class="w-full shrink-0 bg-violet-500"
                        :style="{ height: bar.outboundShare }"
                      />
                    </div>
                  </RouterLink>
                </div>
              </div>
              <div class="mt-2 flex gap-2 px-1">
                <span
                  v-for="bar in activityBars"
                  :key="`${bar.start_at}-label`"
                  class="min-w-0 flex-1 text-center text-[9px] font-medium text-slate-400"
                >
                  {{ bar.label }}
                </span>
              </div>
            </div>
          </div>

          <footer class="flex flex-wrap items-center gap-4 border-t border-slate-200/80 px-5 py-3">
            <span class="inline-flex items-center gap-1.5 text-[10px] font-medium text-slate-500">
              <span class="size-2 rounded-sm bg-sky-500" /> Inbound
            </span>
            <span class="inline-flex items-center gap-1.5 text-[10px] font-medium text-slate-500">
              <span class="size-2 rounded-sm bg-violet-500" /> Outbound
            </span>
            <span class="ml-auto text-[10px] text-slate-400">
              {{ activity.activity.value.timezone }} · Started-call buckets
            </span>
          </footer>
        </template>
      </section>

      <RecentMissedCallsPanel
        :missed-calls="missedCalls.missedCalls.value"
        :loading="missedCalls.loading.value"
        :error="missedCalls.error.value"
        :range-label="activityRangeLabel"
      />

      <CallInsightsPanel
        :activity="activity.activity.value"
        :destinations="topDestinations.destinations.value"
        :loading="topDestinations.loading.value"
        :error="topDestinations.error.value"
        :range-label="activityRangeLabel"
      />

      <CallQualityPanel
        :quality="quality.quality.value"
        :loading="quality.loading.value"
        :error="quality.error.value"
        :range-label="activityRangeLabel"
      />

      <CallGeographyPanel
        :geography="geography.geography.value"
        :loading="geography.loading.value"
        :error="geography.error.value"
        :range-label="activityRangeLabel"
      />

      <section class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
              <PhoneIcon class="size-4.5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-800">Today's call activity</h2>
              <p class="text-[11px] text-slate-500">Calculated in the account timezone</p>
            </div>
            <RouterLink
              :to="{ name: 'call-history' }"
              class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-brand-600 hover:text-brand-700"
            >
              Call history <ChevronRightIcon class="size-3.5" />
            </RouterLink>
          </header>
          <div
            class="grid divide-y divide-slate-200/80 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4"
          >
            <div class="p-5">
              <ArrowDownLeftIcon class="size-5 text-sky-500" />
              <p class="mt-3 text-2xl font-semibold text-slate-800">
                {{ dashboard.overview.value.calls_today.inbound }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Inbound
              </p>
            </div>
            <div class="p-5">
              <ArrowUpRightIcon class="size-5 text-violet-500" />
              <p class="mt-3 text-2xl font-semibold text-slate-800">
                {{ dashboard.overview.value.calls_today.outbound }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Outbound
              </p>
            </div>
            <div class="p-5">
              <BellAlertIcon class="size-5 text-amber-500" />
              <p class="mt-3 text-2xl font-semibold text-slate-800">
                {{ dashboard.overview.value.calls_today.missed }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Missed
              </p>
            </div>
            <div class="p-5">
              <ClockIcon class="size-5 text-emerald-500" />
              <p class="mt-3 text-2xl font-semibold text-slate-800">
                {{ formatDuration(dashboard.overview.value.calls_today.average_duration_seconds) }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Average answered
              </p>
            </div>
          </div>
        </article>

        <article class="card-surface p-5">
          <div class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-md bg-sky-50 text-sky-600">
              <CloudArrowDownIcon class="size-4.5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-800">Projection health</h2>
              <p class="text-[11px] text-slate-500">
                {{ dashboard.overview.value.synchronization.checkpoints.total }} resource
                checkpoints
              </p>
            </div>
          </div>
          <dl class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Healthy
              </dt>
              <dd class="mt-1 text-lg font-semibold text-emerald-700">
                {{ dashboard.overview.value.synchronization.checkpoints.healthy }}
              </dd>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Running
              </dt>
              <dd class="mt-1 text-lg font-semibold text-sky-700">
                {{ dashboard.overview.value.synchronization.active_runs }}
              </dd>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Stale
              </dt>
              <dd class="mt-1 text-lg font-semibold text-amber-700">
                {{ dashboard.overview.value.synchronization.checkpoints.stale }}
              </dd>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Failed
              </dt>
              <dd class="mt-1 text-lg font-semibold text-red-700">
                {{ dashboard.overview.value.synchronization.checkpoints.error }}
              </dd>
            </div>
          </dl>
          <RouterLink
            :to="{ name: 'system-status' }"
            class="mt-4 inline-flex items-center gap-1 text-[11px] font-semibold text-brand-600 hover:text-brand-700"
          >
            Inspect system status <ChevronRightIcon class="size-3.5" />
          </RouterLink>
        </article>
      </section>

      <section class="mt-6">
        <div class="mb-3 flex items-end justify-between gap-4">
          <div>
            <p class="eyebrow">Projected resources</p>
            <h2 class="mt-1 text-sm font-semibold text-slate-800">Account inventory</h2>
          </div>
          <p class="text-[11px] text-slate-500">Counts include the complete account projection.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <RouterLink
            v-for="item in inventory"
            :key="item.label"
            :to="{ name: item.route }"
            class="card-surface group flex items-center gap-4 p-4 transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md"
          >
            <span class="grid size-10 shrink-0 place-items-center rounded-md" :class="item.tone">
              <component :is="item.icon" class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex items-baseline gap-2">
                <p class="text-lg font-semibold text-slate-800">{{ item.value }}</p>
                <p class="truncate text-xs font-semibold text-slate-700">{{ item.label }}</p>
              </div>
              <p class="mt-1 truncate text-[11px] text-slate-500">{{ item.detail }}</p>
            </div>
            <ChevronRightIcon class="size-4 shrink-0 text-slate-300 group-hover:text-brand-500" />
          </RouterLink>
        </div>
      </section>

      <section class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-amber-50 text-amber-600">
              <ExclamationTriangleIcon class="size-4.5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-800">Needs attention</h2>
              <p class="text-[11px] text-slate-500">
                Safe guidance from projected operational state
              </p>
            </div>
          </header>
          <div
            v-if="dashboard.overview.value.attention.items.length === 0"
            class="flex items-center gap-3 p-5 text-xs text-emerald-700"
          >
            <CheckCircleIcon class="size-5" /> No projected issues require attention.
          </div>
          <div v-else class="divide-y divide-slate-200/80">
            <RouterLink
              v-for="item in dashboard.overview.value.attention.items"
              :key="item.code"
              :to="{ name: attentionRoute[item.resource] ?? 'system-status' }"
              class="group flex items-start gap-4 p-5 hover:bg-slate-50/80"
            >
              <CircularCountBadge
                :count="item.count"
                :label="`${item.count} ${item.label}`"
                :class="attentionTone(item)"
              />
              <div class="min-w-0 flex-1">
                <h3 class="text-xs font-semibold text-slate-800">{{ item.label }}</h3>
                <p class="mt-1 text-[11px] leading-5 text-slate-600">{{ item.message }}</p>
                <p class="mt-1 text-[11px] leading-5 text-slate-500">{{ item.guidance }}</p>
              </div>
              <ChevronRightIcon
                class="mt-1 size-4 shrink-0 text-slate-300 group-hover:text-brand-500"
              />
            </RouterLink>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-200/80 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-800">Recent synchronization</h2>
            <p class="mt-1 text-[11px] text-slate-500">Latest safe projection activity</p>
          </header>
          <div
            v-if="dashboard.overview.value.synchronization.recent_runs.length === 0"
            class="p-5 text-xs text-slate-500"
          >
            No synchronization runs have been recorded.
          </div>
          <div v-else class="divide-y divide-slate-200/80">
            <div
              v-for="run in dashboard.overview.value.synchronization.recent_runs"
              :key="run.id"
              class="flex items-center gap-3 px-5 py-3.5"
            >
              <span
                class="size-2 rounded-full"
                :class="{
                  'bg-emerald-500': run.status === 'succeeded',
                  'bg-sky-500': ['queued', 'running'].includes(run.status),
                  'bg-red-500': run.status === 'failed',
                }"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-slate-700">
                  {{ humanize(run.resource) }}
                </p>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  {{ humanize(run.status) }} · {{ run.processed_count }} processed
                </p>
              </div>
            </div>
          </div>
        </article>
      </section>
    </template>
  </div>
</template>
