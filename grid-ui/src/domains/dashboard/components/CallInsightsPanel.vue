<script setup lang="ts">
import { computed } from 'vue'
import {
  ChartBarSquareIcon,
  ChevronRightIcon,
  ClockIcon,
  PhoneArrowDownLeftIcon,
} from '@heroicons/vue/24/outline'
import type { CallActivityPoint, CallActivityTrend } from '../schemas/callActivityTrendSchema'
import type { TopCallDestination, TopCallDestinations } from '../schemas/topCallDestinationsSchema'

const props = defineProps<{
  activity: CallActivityTrend | null
  destinations: TopCallDestinations | null
  loading: boolean
  error: string | null
  rangeLabel: string
}>()

const peakPoint = computed<CallActivityPoint | null>(() => {
  const series = props.activity?.series ?? []
  if (series.length === 0) return null

  return series.reduce((peak, point) => (point.total > peak.total ? point : peak))
})

const peakLabel = computed(() => {
  const point = peakPoint.value
  const activity = props.activity
  if (!point || !activity) return 'No projected call period'

  return new Intl.DateTimeFormat(undefined, {
    ...(activity.range === 'today'
      ? { dateStyle: 'medium' as const, timeStyle: 'short' as const }
      : { weekday: 'short' as const, month: 'short' as const, day: 'numeric' as const }),
    timeZone: activity.timezone,
  }).format(new Date(point.start_at))
})

const peakTitle = computed(() =>
  props.activity?.range === 'today' ? 'Peak calling hour' : 'Peak calling day',
)

function destinationLabel(destination: TopCallDestination): string {
  return destination.name || destination.number || 'Unknown destination'
}

function destinationDetail(destination: TopCallDestination): string {
  if (destination.name && destination.number) return destination.number
  return 'No separate destination number'
}

function destinationRoute(destination: TopCallDestination, summary: TopCallDestinations) {
  return {
    name: 'call-history',
    query: {
      started_after: summary.from,
      started_before: summary.to,
      search: destination.number || destination.name || undefined,
    },
  }
}
</script>

<template>
  <section class="card-surface mt-6 overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-violet-50 text-violet-600">
        <ChartBarSquareIcon class="size-4.5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">Call insights</h2>
        <p class="text-[11px] text-heading-description">
          Busiest period and most-called destinations · {{ rangeLabel }}
        </p>
      </div>
    </header>

    <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-red-700">
      {{ error }}
    </div>

    <div v-if="loading && !destinations" class="px-5 py-8 text-center text-xs text-slate-500">
      Loading call insights…
    </div>

    <div v-else class="grid lg:grid-cols-[minmax(240px,0.55fr)_minmax(0,1.45fr)]">
      <article class="border-b border-slate-200/80 p-5 lg:border-r lg:border-b-0">
        <div class="flex items-center gap-2 text-violet-600">
          <ClockIcon class="size-4" />
          <h3 class="text-[10px] font-semibold tracking-wide uppercase">{{ peakTitle }}</h3>
        </div>
        <p class="mt-3 text-sm font-semibold text-slate-800">{{ peakLabel }}</p>
        <template v-if="peakPoint && peakPoint.total > 0">
          <p class="mt-1 text-2xl font-semibold text-slate-800">{{ peakPoint.total }}</p>
          <p class="text-[11px] text-slate-500">
            {{ peakPoint.inbound }} inbound · {{ peakPoint.outbound }} outbound
          </p>
        </template>
        <p v-else class="mt-2 text-[11px] leading-5 text-slate-500">
          No calls were projected in this period.
        </p>
      </article>

      <article>
        <div class="flex items-center gap-2 border-b border-slate-200/80 px-5 py-3">
          <PhoneArrowDownLeftIcon class="size-4 text-sky-600" />
          <h3 class="text-xs font-semibold text-slate-700">Top destinations</h3>
        </div>
        <div
          v-if="destinations && destinations.destinations.length === 0"
          class="px-5 py-6 text-xs text-slate-500"
        >
          No destination activity was projected for this period.
        </div>
        <div v-else-if="destinations" class="divide-y divide-slate-200/80">
          <RouterLink
            v-for="destination in destinations.destinations"
            :key="`${destination.name ?? ''}-${destination.number ?? ''}`"
            :to="destinationRoute(destination, destinations)"
            class="group grid gap-2 px-5 py-3 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-5"
          >
            <div class="min-w-0">
              <p class="truncate text-xs font-semibold text-slate-800">
                {{ destinationLabel(destination) }}
              </p>
              <p class="mt-0.5 truncate text-[10px] text-slate-500">
                {{ destinationDetail(destination) }}
              </p>
            </div>
            <div class="text-[10px] text-slate-500 sm:text-right">
              <p class="font-semibold text-slate-700">{{ destination.total }} calls</p>
              <p>{{ destination.answered }} answered · {{ destination.unanswered }} unanswered</p>
            </div>
            <ChevronRightIcon
              class="size-3.5 text-brand-500 transition group-hover:translate-x-0.5"
            />
          </RouterLink>
        </div>
      </article>
    </div>
  </section>
</template>
