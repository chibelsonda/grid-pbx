<script setup lang="ts">
import { computed } from 'vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import type { AgentStatistics } from '../types/queue'

const props = defineProps<{
  statistics: AgentStatistics | null
  loading: boolean
  refreshing: boolean
  error: string | null
}>()

defineEmits<{ refresh: [] }>()

const observedAt = computed(() =>
  props.statistics
    ? new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
      }).format(new Date(props.statistics.observed_at))
    : null,
)

function percentage(value: number | null): string {
  return value === null ? '—' : `${value}%`
}
</script>

<template>
  <section class="card-surface mb-4 overflow-hidden" aria-labelledby="agent-statistics-title">
    <header
      class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"
    >
      <div class="min-w-0 flex-1">
        <h2 id="agent-statistics-title" class="text-sm font-semibold text-slate-700">
          Live agent performance
        </h2>
        <p class="mt-0.5 text-[11px] text-slate-500">
          Account-scoped call totals from the Switch statistics window. Caller, call, Queue, and raw
          agent identifiers are not exposed.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="observedAt" class="text-[11px] text-slate-500">Checked {{ observedAt }}</span>
        <button
          type="button"
          :disabled="loading || refreshing"
          class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600 disabled:opacity-50"
          @click="$emit('refresh')"
        >
          <ArrowPathIcon class="size-4" :class="refreshing && 'animate-spin'" />Refresh
        </button>
      </div>
    </header>

    <div v-if="loading && !statistics" class="p-8 text-center text-xs text-slate-500" role="status">
      Loading live agent performance…
    </div>
    <div v-else-if="statistics" class="p-5">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.total_calls }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Attempts</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.answered_calls }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Answered</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.missed_calls }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Missed</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">
            {{ percentage(statistics.totals.answer_rate_percentage) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Answer rate
          </p>
        </div>
      </div>

      <div v-if="statistics.agents.length" class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-xs">
          <caption class="sr-only">
            Current performance aggregated by projected agent
          </caption>
          <thead
            class="border-y border-slate-200 text-[10px] font-bold tracking-wide text-slate-500 uppercase"
          >
            <tr>
              <th scope="col" class="py-3 pr-4">Agent</th>
              <th scope="col" class="px-4 py-3">Extension</th>
              <th scope="col" class="px-4 py-3">Attempts</th>
              <th scope="col" class="px-4 py-3">Answered</th>
              <th scope="col" class="px-4 py-3">Missed</th>
              <th scope="col" class="py-3 pl-4">Answer rate</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-600">
            <tr v-for="agent in statistics.agents" :key="agent.id">
              <th scope="row" class="py-3 pr-4 font-semibold text-slate-700">{{ agent.name }}</th>
              <td class="px-4 py-3">{{ agent.extension ?? '—' }}</td>
              <td class="px-4 py-3">{{ agent.total_calls }}</td>
              <td class="px-4 py-3">{{ agent.answered_calls }}</td>
              <td class="px-4 py-3">{{ agent.missed_calls }}</td>
              <td class="py-3 pl-4">{{ percentage(agent.answer_rate_percentage) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-if="statistics.unresolved_agents > 0"
        class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800"
      >
        {{ statistics.unresolved_agents }} statistics entr{{
          statistics.unresolved_agents === 1 ? 'y' : 'ies'
        }}
        could not be matched to a projected Queue agent. Synchronize Queues to reconcile the
        projection.
      </p>
    </div>

    <p
      v-if="error"
      class="border-t border-amber-200 bg-amber-50 px-5 py-3 text-[11px] text-amber-800"
      role="status"
    >
      {{ error }} Existing metrics remain visible while GridPBX retries.
    </p>
  </section>
</template>
