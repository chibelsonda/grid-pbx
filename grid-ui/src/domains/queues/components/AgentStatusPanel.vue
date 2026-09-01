<script setup lang="ts">
import { computed } from 'vue'
import { ArrowPathIcon, BoltIcon, UserCircleIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import AgentQueueMembershipPanel from './AgentQueueMembershipPanel.vue'
import { useAgentStatusForm } from '../composables/useAgentStatusForm'
import type {
  Agent,
  AgentQueueMembership,
  AgentQueueMembershipInput,
  AgentStatus,
  AgentStatusInput,
} from '../types/queue'

const props = defineProps<{
  agent: Agent
  current: AgentStatus | null
  loading: boolean
  refreshing: boolean
  lastObservedAt: string | null
  refreshError: string | null
  commandAccepted: boolean
  membership: AgentQueueMembership | null
  membershipLoading: boolean
  membershipSaving: boolean
  membershipError: string | null
  membershipCommandAccepted: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{
  close: []
  refresh: []
  save: [input: AgentStatusInput]
  refreshMemberships: []
  changeMembership: [input: AgentQueueMembershipInput]
}>()
const { form, validate, validationErrors } = useAgentStatusForm()
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const statusOptions: ListboxOptionValue[] = [
  { value: 'login', label: 'Log in' },
  { value: 'logout', label: 'Log out' },
  { value: 'pause', label: 'Pause' },
  { value: 'resume', label: 'Resume' },
  { value: 'end_wrapup', label: 'End wrap-up' },
]
const lastObservedLabel = computed(() =>
  props.lastObservedAt
    ? new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
      }).format(new Date(props.lastObservedAt))
    : null,
)

function setStatus(value: ListboxValue): void {
  if (
    value === 'login' ||
    value === 'logout' ||
    value === 'pause' ||
    value === 'resume' ||
    value === 'end_wrapup'
  ) {
    form.status = value
  }
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) emit('save', result.data)
}
</script>

<template>
  <CrudSlideOver
    title="Agent status"
    eyebrow="GridPBX / Queues / Agents"
    description="Live ACDc state is read from Switch and is not treated as durable MySQL configuration."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="error"
        class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
        role="alert"
      >
        {{ error }}
      </div>
      <article class="card-surface p-5">
        <div class="flex items-center gap-4">
          <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-600"
            ><UserCircleIcon class="size-6"
          /></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">{{ agent.name }}</h2>
            <p class="text-xs text-slate-400">Extension {{ agent.extension ?? 'not assigned' }}</p>
          </div>
        </div>
        <div class="mt-5 rounded-md bg-slate-50 p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Current Switch status
              </p>
              <p class="mt-1 text-[10px] text-slate-500">
                Auto-refresh · 5s<span v-if="lastObservedLabel">
                  · Last checked {{ lastObservedLabel }}</span
                >
              </p>
            </div>
            <button
              type="button"
              :disabled="loading || refreshing"
              class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-600 disabled:opacity-50"
              @click="emit('refresh')"
            >
              <ArrowPathIcon class="size-4" :class="refreshing && 'animate-spin'" />Refresh
            </button>
          </div>
          <p
            class="mt-1 text-sm font-semibold text-slate-700"
            :role="loading || refreshing ? 'status' : undefined"
          >
            {{ loading ? 'Loading…' : (current?.status ?? 'Unknown') }}
          </p>
        </div>
      </article>
      <AgentQueueMembershipPanel
        :membership="membership"
        :loading="membershipLoading"
        :saving="membershipSaving"
        :error="membershipError"
        :command-accepted="membershipCommandAccepted"
        :can-manage="canManage"
        @refresh="emit('refreshMemberships')"
        @change="emit('changeMembership', $event)"
      />
      <div
        v-if="commandAccepted"
        class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800"
        role="status"
      >
        Switch accepted the status command. Live status will continue refreshing because commands
        can be deferred while the agent is on a call.
      </div>
      <div
        v-if="refreshError"
        class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800"
        role="alert"
      >
        {{ refreshError }} The last observed status remains displayed.
      </div>
      <article v-if="canManage" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <BoltIcon class="size-5 text-amber-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Request status change</h2>
            <p class="text-[10px] text-slate-400">
              Commands can be deferred by Switch while an agent is on a call.
            </p>
          </div>
        </header>
        <div class="grid gap-4 p-5">
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Action</span
            ><FormListbox
              :model-value="form.status"
              :options="statusOptions"
              aria-label="Agent status action"
              :invalid="Boolean(errors.status)"
              @update:model-value="setStatus"
            /><span v-if="errors.status" class="text-[10px] text-danger">{{
              errors.status[0]
            }}</span></label
          ><FormInput
            v-if="form.status === 'pause'"
            :model-value="form.pause_timeout ?? null"
            label="Pause timeout (seconds)"
            type="number"
            min="0"
            max="86400"
            required
            :error="errors.pause_timeout"
            @update:model-value="form.pause_timeout = Number($event)"
          />
        </div>
      </article>
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="loading"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          Send command
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
