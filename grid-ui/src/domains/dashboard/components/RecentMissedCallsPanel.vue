<script setup lang="ts">
import { BellAlertIcon, ChevronRightIcon, PhoneIcon } from '@heroicons/vue/24/outline'
import type { RecentMissedCall, RecentMissedCalls } from '../schemas/recentMissedCallsSchema'

defineProps<{
  missedCalls: RecentMissedCalls | null
  loading: boolean
  error: string | null
  rangeLabel: string
}>()

function callerLabel(call: RecentMissedCall): string {
  return call.caller.name || call.caller.number || 'Unknown caller'
}

function callerDetail(call: RecentMissedCall): string {
  if (call.caller.name && call.caller.number) return call.caller.number
  return 'Caller number unavailable'
}

function destinationLabel(call: RecentMissedCall): string {
  return call.destination.name || call.destination.number || 'Unassigned destination'
}

function startedAt(call: RecentMissedCall, timezone: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: timezone,
  }).format(new Date(call.started_at))
}

function detailRoute(call: RecentMissedCall, summary: RecentMissedCalls) {
  return {
    name: 'call-history',
    query: {
      cdr: call.id,
      direction: 'inbound',
      started_after: summary.from,
      started_before: summary.to,
    },
  }
}
</script>

<template>
  <section class="card-surface mt-6 overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-amber-50 text-amber-600">
        <BellAlertIcon class="size-4.5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">Recent missed calls</h2>
        <p class="text-[11px] text-heading-description">Latest inbound unanswered calls · {{ rangeLabel }}</p>
      </div>
      <RouterLink
        v-if="missedCalls"
        :to="{
          name: 'call-history',
          query: {
            direction: 'inbound',
            outcome: 'unanswered',
            started_after: missedCalls.from,
            started_before: missedCalls.to,
          },
        }"
        class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-brand-600 hover:text-brand-700"
      >
        View all {{ missedCalls.total }} <ChevronRightIcon class="size-3.5" />
      </RouterLink>
    </header>

    <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-xs text-red-700">
      {{ error }}
    </div>

    <div v-if="loading && !missedCalls" class="px-5 py-8 text-center text-xs text-slate-500">
      Loading recent missed calls…
    </div>

    <div
      v-else-if="missedCalls && missedCalls.items.length === 0"
      class="flex items-center gap-3 px-5 py-6 text-xs text-emerald-700"
    >
      <PhoneIcon class="size-5" /> No inbound missed calls were projected for this period.
    </div>

    <div v-else-if="missedCalls" class="divide-y divide-slate-200/80">
      <RouterLink
        v-for="call in missedCalls.items"
        :key="call.id"
        :to="detailRoute(call, missedCalls)"
        class="group grid gap-2 px-5 py-3.5 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-center sm:gap-5"
      >
        <div class="min-w-0">
          <p class="truncate text-xs font-semibold text-slate-800">{{ callerLabel(call) }}</p>
          <p class="mt-0.5 truncate text-[10px] text-slate-500">{{ callerDetail(call) }}</p>
        </div>
        <div class="min-w-0">
          <p class="truncate text-[11px] font-medium text-slate-600">
            To {{ destinationLabel(call) }}
          </p>
          <p class="mt-0.5 text-[10px] text-slate-400">
            {{ startedAt(call, missedCalls.timezone) }}
          </p>
        </div>
        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-brand-600">
          Inspect <ChevronRightIcon class="size-3.5 transition group-hover:translate-x-0.5" />
        </span>
      </RouterLink>
    </div>
  </section>
</template>
