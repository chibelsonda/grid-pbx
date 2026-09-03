<script setup lang="ts">
import { computed } from 'vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import type { QueueStatistics } from '../types/queue'

const props = defineProps<{
  statistics: QueueStatistics | null
  loading: boolean
  refreshing: boolean
  error: string | null
}>()

const emit = defineEmits<{ refresh: []; openQueue: [queueId: string] }>()

const observedAt = computed(() =>
  props.statistics
    ? new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
      }).format(new Date(props.statistics.observed_at))
    : null,
)

function duration(seconds: number | null): string {
  if (seconds === null) return '—'
  if (seconds < 60) return seconds + 's'
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return remainder === 0 ? minutes + 'm' : minutes + 'm ' + remainder + 's'
}

async function handleRowAction(
  actionId: string,
  queue: { id: string; name: string },
): Promise<void> {
  if (actionId === 'copy') await navigator.clipboard?.writeText(queue.name)
  else emit('openQueue', queue.id)
}
</script>

<template>
  <section class="card-surface mb-5 overflow-hidden" aria-labelledby="queue-statistics-title">
    <header
      class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"
    >
      <div class="min-w-0 flex-1">
        <h2 id="queue-statistics-title" class="text-sm font-semibold text-slate-700">
          Live queue activity
        </h2>
        <p class="mt-0.5 text-[11px] text-heading-description">
          Aggregated from the connected Switch's current statistics window. No caller or agent
          details are exposed.
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
      Loading live queue activity…
    </div>
    <div v-else-if="statistics" class="p-5">
      <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.waiting }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Waiting</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.handled }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            In progress
          </p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.processed }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Completed</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">{{ statistics.totals.abandoned }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Abandoned</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">
            {{ duration(statistics.totals.average_wait_seconds) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">Avg wait</p>
        </div>
        <div class="rounded-md border border-slate-200 p-3">
          <p class="text-lg font-semibold text-slate-800">
            {{ duration(statistics.totals.longest_current_wait_seconds) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Longest waiting
          </p>
        </div>
      </div>

      <div v-if="statistics.queues.length" class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-xs">
          <caption class="sr-only">
            Recent activity aggregated by projected queue
          </caption>
          <thead
            class="border-y border-slate-200 text-[10px] font-bold tracking-wide text-slate-500 uppercase"
          >
            <tr>
              <th scope="col" class="py-3 pr-4">Queue</th>
              <th scope="col" class="px-4 py-3">Waiting</th>
              <th scope="col" class="px-4 py-3">In progress</th>
              <th scope="col" class="px-4 py-3">Completed</th>
              <th scope="col" class="px-4 py-3">Abandoned</th>
              <th scope="col" class="px-4 py-3">Avg wait</th>
              <th scope="col" class="py-3 pl-4">Avg talk</th>
              <th scope="col" class="w-12" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-600">
            <tr v-for="queue in statistics.queues" :key="queue.id">
              <th scope="row" class="py-3 pr-4 font-semibold text-slate-700">{{ queue.name }}</th>
              <td class="px-4 py-3">{{ queue.waiting }}</td>
              <td class="px-4 py-3">{{ queue.handled }}</td>
              <td class="px-4 py-3">{{ queue.processed }}</td>
              <td class="px-4 py-3">{{ queue.abandoned }}</td>
              <td class="px-4 py-3">{{ duration(queue.average_wait_seconds) }}</td>
              <td class="py-3 pl-4">{{ duration(queue.average_talk_seconds) }}</td>
              <td class="py-3 pl-3 text-right">
                <RowActionMenu
                  :label="`Actions for ${queue.name}`"
                  :actions="[
                    { id: 'open', label: 'Open queue', icon: 'view' },
                    { id: 'copy', label: 'Copy queue name', icon: 'copy' },
                  ]"
                  @select="handleRowAction($event, queue)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-if="statistics.unresolved_records > 0"
        class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800"
      >
        {{ statistics.unresolved_records }} unresolved statistic
        {{ statistics.unresolved_records === 1 ? 'record' : 'records' }} could not be matched to a
        projected queue. Synchronize queues to reconcile the projection.
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
