<script setup lang="ts">
import {
  ChartBarIcon,
  ChevronRightIcon,
  ClockIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import type { CallDurationBand, CallQuality } from '../schemas/callQualitySchema'

defineProps<{
  quality: CallQuality | null
  loading: boolean
  error: string | null
  rangeLabel: string
}>()

function formatSeconds(seconds: number | null): string {
  if (seconds === null) return '—'
  if (seconds < 60) return `${seconds}s`

  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return remainder === 0 ? `${minutes}m` : `${minutes}m ${remainder}s`
}

function baseQuery(quality: CallQuality) {
  return {
    started_after: quality.from,
    started_before: quality.to,
  }
}

function answerTimeRoute(quality: CallQuality) {
  return {
    name: 'call-history',
    query: { ...baseQuery(quality), direction: 'inbound', outcome: 'answered' },
  }
}

function potentialAbandonmentRoute(quality: CallQuality) {
  return {
    name: 'call-history',
    query: {
      ...baseQuery(quality),
      direction: 'inbound',
      outcome: 'unanswered',
      duration_max: String(quality.potential_abandonment.threshold_seconds),
    },
  }
}

function durationRoute(quality: CallQuality, band: CallDurationBand) {
  return {
    name: 'call-history',
    query: {
      ...baseQuery(quality),
      duration_min: String(band.minimum_seconds),
      ...(band.maximum_seconds === null ? {} : { duration_max: String(band.maximum_seconds) }),
    },
  }
}
</script>

<template>
  <section class="card-surface mt-6 overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-emerald-50 text-emerald-600">
        <ChartBarIcon class="size-4.5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">Call quality indicators</h2>
        <p class="text-[11px] text-heading-description">
          Answer behavior and call-duration distribution · {{ rangeLabel }}
        </p>
      </div>
    </header>

    <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-red-700">
      {{ error }}
    </div>

    <div v-if="loading && !quality" class="px-5 py-8 text-center text-xs text-slate-500">
      Loading call-quality indicators…
    </div>

    <template v-else-if="quality">
      <div
        class="grid border-b border-slate-200/80 lg:grid-cols-2 lg:divide-x lg:divide-slate-200/80"
      >
        <RouterLink
          :to="answerTimeRoute(quality)"
          class="group flex items-start gap-4 p-5 transition hover:bg-slate-50"
        >
          <span class="grid size-10 shrink-0 place-items-center rounded-md bg-sky-50 text-sky-600">
            <ClockIcon class="size-5" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Average pre-answer time
            </p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
              {{ formatSeconds(quality.answer_time.average_pre_answer_seconds) }}
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
              {{ quality.answer_time.answered_inbound_calls }} answered inbound calls
            </p>
          </div>
          <ChevronRightIcon class="mt-3 size-4 text-brand-500 group-hover:translate-x-0.5" />
        </RouterLink>

        <RouterLink
          :to="potentialAbandonmentRoute(quality)"
          class="group flex items-start gap-4 border-t border-slate-200/80 p-5 transition hover:bg-slate-50 lg:border-t-0"
        >
          <span
            class="grid size-10 shrink-0 place-items-center rounded-md bg-amber-50 text-amber-600"
          >
            <ExclamationTriangleIcon class="size-5" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Potential abandonment
            </p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
              {{ quality.potential_abandonment.rate }}%
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
              {{ quality.potential_abandonment.potential_calls }} short unanswered inbound calls
            </p>
          </div>
          <ChevronRightIcon class="mt-3 size-4 text-brand-500 group-hover:translate-x-0.5" />
        </RouterLink>
      </div>

      <article class="p-5">
        <div class="flex flex-wrap items-end justify-between gap-2">
          <div>
            <h3 class="text-xs font-semibold text-slate-700">Call duration distribution</h3>
            <p class="mt-1 text-[10px] text-heading-description">
              {{ quality.duration_distribution.total_calls }} calls across total-duration bands
            </p>
          </div>
          <p class="text-[10px] text-slate-400">Select a band to inspect matching calls.</p>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <RouterLink
            v-for="band in quality.duration_distribution.bands"
            :key="band.key"
            :to="durationRoute(quality, band)"
            class="group rounded-md border border-slate-200 bg-slate-50/60 p-3 transition hover:border-brand-200 hover:bg-brand-50/40"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="text-[11px] font-semibold text-slate-700">{{ band.label }}</p>
              <ChevronRightIcon class="size-3.5 text-slate-400 group-hover:text-brand-500" />
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-200">
              <span
                class="block h-full rounded-full bg-brand-500"
                :style="{ width: `${band.percentage}%` }"
              />
            </div>
            <p class="mt-2 text-[10px] text-slate-500">
              <span class="font-semibold text-slate-700">{{ band.count }}</span>
              · {{ band.percentage }}%
            </p>
          </RouterLink>
        </div>
      </article>

      <footer class="border-t border-slate-200/80 bg-slate-50/60 px-5 py-3">
        <p class="text-[10px] leading-4 text-slate-500">
          {{ quality.answer_time.disclosure }}
          {{ quality.potential_abandonment.disclosure }}
        </p>
      </footer>
    </template>
  </section>
</template>
