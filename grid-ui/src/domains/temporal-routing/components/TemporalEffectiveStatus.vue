<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowPathIcon, BoltIcon, NoSymbolIcon, PlayIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import type { TemporalControlAction, TemporalEffectiveStatus } from '../types/temporalRouting'

const props = defineProps<{
  status: TemporalEffectiveStatus
  subject: 'rule' | 'rule set'
  canManage: boolean
  busy: boolean
}>()
const emit = defineEmits<{ control: [action: TemporalControlAction] }>()
const pending = ref<TemporalControlAction | null>(null)
const actionLabel = computed(
  () =>
    ({ enable: 'Force active', disable: 'Force inactive', reset: 'Resume schedule' })[
      pending.value ?? 'reset'
    ],
)
const description = computed(() =>
  pending.value === 'reset'
    ? `Remove the manual override and let this ${props.subject} follow its configured schedule?`
    : `${actionLabel.value} for this ${props.subject}? This changes live call-routing behavior.`,
)
const overrideLabel = computed(
  () =>
    ({
      scheduled: 'Following schedule',
      forced_active: 'Forced active',
      forced_inactive: 'Forced inactive',
      mixed: 'Mixed overrides',
      empty: 'No rules',
    })[props.status.override],
)

function confirm(): void {
  if (!pending.value) return
  emit('control', pending.value)
  pending.value = null
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <BoltIcon class="size-5 text-amber-500" />
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Effective status</h2>
        <p class="text-[10px] text-slate-400">Evaluated in {{ status.timezone }}.</p>
      </div>
      <span
        class="ml-auto rounded-full px-2.5 py-1 text-[10px] font-semibold"
        :class="status.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
      >
        {{ status.is_active ? 'Active' : 'Inactive' }}
      </span>
    </header>
    <div class="grid gap-4 p-5">
      <div class="grid gap-1 rounded-md bg-slate-50 p-4 sm:grid-cols-2">
        <div>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Control mode
          </p>
          <p class="mt-1 text-xs font-semibold text-slate-700">{{ overrideLabel }}</p>
        </div>
        <div v-if="status.rule_count !== undefined">
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Active rules
          </p>
          <p class="mt-1 text-xs font-semibold text-slate-700">
            {{ status.active_rule_count }} of {{ status.resolved_rule_count }}
          </p>
        </div>
      </div>
      <div v-if="canManage" class="flex flex-wrap gap-2">
        <button
          type="button"
          :disabled="busy"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-emerald-600 px-3 text-xs font-semibold text-white disabled:opacity-50"
          @click="pending = 'enable'"
        >
          <PlayIcon class="size-4" />Force active
        </button>
        <button
          type="button"
          :disabled="busy"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-slate-700 px-3 text-xs font-semibold text-white disabled:opacity-50"
          @click="pending = 'disable'"
        >
          <NoSymbolIcon class="size-4" />Force inactive
        </button>
        <button
          type="button"
          :disabled="busy"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 disabled:opacity-50"
          @click="pending = 'reset'"
        >
          <ArrowPathIcon class="size-4" />Resume schedule
        </button>
      </div>
      <p class="text-[10px] text-slate-400">
        Last evaluated {{ new Date(status.evaluated_at).toLocaleString() }}.
      </p>
    </div>
  </article>
  <ConfirmDialog
    :open="pending !== null"
    :title="actionLabel"
    :description="description"
    :confirm-label="actionLabel"
    :busy="busy"
    :tone="pending === 'disable' ? 'warning' : 'primary'"
    @close="pending = null"
    @confirm="confirm"
  />
</template>
