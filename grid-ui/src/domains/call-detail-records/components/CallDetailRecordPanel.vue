<script setup lang="ts">
import { computed } from 'vue'
import {
  ArrowDownLeftIcon,
  ArrowUpRightIcon,
  ClockIcon,
  HashtagIcon,
  MicrophoneIcon,
  PhoneArrowUpRightIcon,
  ShieldCheckIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { CallDetailRecord } from '../types/callDetailRecord'

const props = defineProps<{
  record: CallDetailRecord | null
  loading: boolean
  error: string | null
}>()
defineEmits<{ close: [] }>()

const title = computed(() => {
  if (!props.record) return 'Call details'
  return `${props.record.caller.number ?? 'Unknown'} → ${props.record.callee.number ?? 'Unknown'}`
})

function formatDuration(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60
  return minutes > 0 ? `${minutes}m ${remainder}s` : `${remainder}s`
}

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Not reported'
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    eyebrow="GridPBX / Call History"
    description="Projected call-leg metadata from the approved Switch import window."
    width="medium"
    @close="$emit('close')"
  >
    <div v-if="loading" class="card-surface p-10 text-center text-xs text-slate-400">
      Loading call details…
    </div>
    <div
      v-else-if="error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else-if="record" class="grid gap-5">
      <article class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span
            class="grid size-11 shrink-0 place-items-center rounded-md"
            :class="
              record.direction === 'inbound'
                ? 'bg-emerald-50 text-emerald-600'
                : 'bg-blue-50 text-blue-600'
            "
          >
            <ArrowDownLeftIcon v-if="record.direction === 'inbound'" class="size-5" />
            <ArrowUpRightIcon v-else class="size-5" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-800">
              {{ humanize(record.direction) }} call
            </p>
            <p class="mt-1 text-xs text-slate-500">
              {{ new Date(record.started_at).toLocaleString() }}
            </p>
          </div>
          <span
            class="rounded-full px-3 py-1 text-[10px] font-bold"
            :class="
              record.answered ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
            "
          >
            {{ record.answered ? 'Answered' : 'Unanswered' }}
          </span>
        </div>
      </article>

      <div class="grid gap-4 sm:grid-cols-2">
        <article class="card-surface p-5">
          <UserIcon class="size-5 text-violet-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">Caller</p>
          <p class="mt-1 truncate text-sm font-semibold text-slate-700">
            {{ record.caller.name ?? 'Unknown caller' }}
          </p>
          <p class="mt-1 font-mono text-xs text-slate-500">
            {{ record.caller.number ?? 'Number unavailable' }}
          </p>
        </article>
        <article class="card-surface p-5">
          <PhoneArrowUpRightIcon class="size-5 text-brand-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">Callee</p>
          <p class="mt-1 truncate text-sm font-semibold text-slate-700">
            {{ record.callee.name ?? 'Unknown destination' }}
          </p>
          <p class="mt-1 font-mono text-xs text-slate-500">
            {{ record.callee.number ?? 'Number unavailable' }}
          </p>
        </article>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <article class="card-surface p-5">
          <ClockIcon class="size-5 text-blue-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">
            Total duration
          </p>
          <p class="mt-1 text-sm font-semibold text-slate-700">
            {{ formatDuration(record.duration_seconds) }}
          </p>
          <p class="mt-1 text-[11px] text-slate-400">
            {{ formatDuration(record.billing_seconds) }} billable
          </p>
        </article>
        <article class="card-surface p-5">
          <ShieldCheckIcon class="size-5 text-emerald-500" />
          <p class="mt-3 text-[10px] font-bold tracking-wide text-slate-400 uppercase">
            Hangup result
          </p>
          <p class="mt-1 text-sm font-semibold text-slate-700">
            {{ humanize(record.hangup_cause) }}
          </p>
          <p class="mt-1 text-[11px] text-slate-400">
            {{ humanize(record.disposition) }}
          </p>
        </article>
      </div>

      <article v-if="record.extension" class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Matched extension owner</h2>
        <RouterLink
          :to="{ name: 'extension-detail', params: { extensionId: record.extension.id } }"
          class="mt-4 flex items-center gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 hover:bg-blue-100/70"
        >
          <span class="grid size-9 place-items-center rounded-md bg-white text-brand-600 shadow-sm">
            <HashtagIcon class="size-4" />
          </span>
          <span>
            <span class="block text-sm font-semibold text-blue-800">{{
              record.extension.display_name
            }}</span>
            <span class="mt-1 block font-mono text-[11px] text-blue-600">{{
              record.extension.extension ?? 'No extension number'
            }}</span>
          </span>
        </RouterLink>
      </article>

      <article class="card-surface p-5">
        <h2 class="text-sm font-semibold text-slate-700">Call references</h2>
        <dl class="mt-4 grid gap-3 text-xs">
          <div class="grid gap-1">
            <dt class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Call ID</dt>
            <dd class="break-all font-mono text-slate-600">{{ record.call_id }}</dd>
          </div>
          <div v-if="record.interaction_id" class="grid gap-1">
            <dt class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
              Interaction ID
            </dt>
            <dd class="break-all font-mono text-slate-600">{{ record.interaction_id }}</dd>
          </div>
        </dl>
      </article>

      <aside
        v-if="record.recording_available"
        class="flex gap-3 rounded-md border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
      >
        <MicrophoneIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          Switch reports recording metadata for this call. Playback and download remain disabled
          until recording authorization, retention, and audit requirements are approved.
        </p>
      </aside>
    </div>
  </CrudSlideOver>
</template>
