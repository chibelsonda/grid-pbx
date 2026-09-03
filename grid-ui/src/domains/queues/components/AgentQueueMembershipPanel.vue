<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ArrowPathIcon, QueueListIcon } from '@heroicons/vue/24/outline'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { agentQueueMembershipInputSchema } from '../schemas/agentQueueMembershipSchema'
import type { AgentQueueMembership, AgentQueueMembershipInput } from '../types/queue'

const props = defineProps<{
  membership: AgentQueueMembership | null
  loading: boolean
  saving: boolean
  error: string | null
  commandAccepted: boolean
  canManage: boolean
}>()
const emit = defineEmits<{
  refresh: []
  change: [input: AgentQueueMembershipInput]
}>()
const selectedQueueId = ref('')
const pendingFinalQueueId = ref<string | null>(null)
const validationError = ref<string | null>(null)
const availableOptions = computed<ListboxOptionValue[]>(() => [
  { value: '', label: 'Select a Queue' },
  ...(props.membership?.available_queues.map(({ id, name }) => ({ value: id, label: name })) ?? []),
])

watch(
  () => props.membership?.agent.id,
  () => {
    selectedQueueId.value = ''
    pendingFinalQueueId.value = null
    validationError.value = null
  },
)

function selectQueue(value: ListboxValue): void {
  selectedQueueId.value = typeof value === 'string' ? value : ''
  validationError.value = null
}

function join(): void {
  change({ action: 'login', queue_id: selectedQueueId.value })
}

function leave(queueId: string): void {
  if (props.membership?.assigned_queues.length === 1 && props.membership.unresolved_queues === 0) {
    pendingFinalQueueId.value = queueId
    return
  }

  change({ action: 'logout', queue_id: queueId })
}

function confirmFinalLeave(): void {
  if (!pendingFinalQueueId.value) return
  const queueId = pendingFinalQueueId.value
  pendingFinalQueueId.value = null
  change({ action: 'logout', queue_id: queueId, confirm_last_queue: true })
}

function change(input: AgentQueueMembershipInput): void {
  if (!props.canManage || props.saving) return
  const result = agentQueueMembershipInputSchema.safeParse(input)

  if (!result.success) {
    validationError.value = result.error.issues[0]?.message ?? 'Select a valid Queue.'
    return
  }

  validationError.value = null
  emit('change', result.data)
  if (result.data.action === 'login') selectedQueueId.value = ''
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header
      class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"
    >
      <div class="flex items-center gap-3">
        <QueueListIcon class="size-5 text-brand-500" aria-hidden="true" />
        <div>
          <h2 class="text-sm font-semibold text-slate-700">Queue memberships</h2>
          <p class="text-[10px] text-heading-description">Authoritative Switch assignments for this Agent.</p>
        </div>
      </div>
      <button
        type="button"
        :disabled="loading || saving"
        class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-600 disabled:opacity-50"
        @click="emit('refresh')"
      >
        <ArrowPathIcon class="size-4" :class="loading && 'animate-spin'" />Refresh
      </button>
    </header>

    <div class="grid gap-4 p-5">
      <p v-if="loading && !membership" class="text-xs text-slate-500" role="status">
        Loading Queue memberships…
      </p>
      <template v-else>
        <div class="grid gap-2">
          <p class="text-xs font-semibold text-slate-600">Assigned Queues</p>
          <p
            v-if="!membership?.assigned_queues.length"
            class="rounded-md border border-dashed border-slate-200 p-3 text-xs text-slate-500"
          >
            This Agent is not assigned to a projected Queue.
          </p>
          <div v-for="queue in membership?.assigned_queues" :key="queue.id" class="grid gap-2">
            <div
              class="flex items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2"
            >
              <span class="text-xs font-semibold text-slate-700">{{ queue.name }}</span>
              <button
                v-if="canManage"
                type="button"
                :disabled="saving"
                class="h-8 rounded-md border border-red-200 px-3 text-[11px] font-semibold text-danger disabled:opacity-50"
                @click="leave(queue.id)"
              >
                Leave
              </button>
            </div>
            <div
              v-if="pendingFinalQueueId === queue.id"
              class="rounded-md border border-amber-200 bg-amber-50 p-3"
              role="alert"
            >
              <p class="text-xs font-semibold text-amber-900">Remove the final Queue?</p>
              <p class="mt-1 text-[11px] text-amber-800">
                Switch defines an Agent by Queue membership. This User will disappear from the
                Agents list and must be re-added through a Queue roster.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  type="button"
                  class="h-8 rounded-md border border-amber-300 bg-white px-3 text-[11px] font-semibold text-amber-900"
                  @click="pendingFinalQueueId = null"
                >
                  Keep Agent
                </button>
                <button
                  type="button"
                  :disabled="saving"
                  class="h-8 rounded-md bg-red-600 px-3 text-[11px] font-semibold text-white disabled:opacity-50"
                  @click="confirmFinalLeave"
                >
                  Leave final Queue
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="canManage && membership?.available_queues.length" class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Join a Queue</span>
          <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
            <FormListbox
              :model-value="selectedQueueId"
              :options="availableOptions"
              aria-label="Queue to join"
              :invalid="Boolean(validationError)"
              @update:model-value="selectQueue"
            />
            <button
              type="button"
              :disabled="saving"
              class="h-10 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:opacity-50"
              @click="join"
            >
              Join
            </button>
          </div>
          <span v-if="validationError" class="text-[10px] text-danger">{{ validationError }}</span>
        </div>

        <p
          v-if="membership?.unresolved_queues"
          class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
        >
          {{ membership.unresolved_queues }} Switch Queue assignment<span
            v-if="membership.unresolved_queues !== 1"
            >s</span
          >
          cannot be shown because the Queue is not projected for this account.
        </p>
      </template>

      <p
        v-if="commandAccepted"
        class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800"
        role="status"
      >
        Switch saved the Queue membership and accepted the live Agent command.
      </p>
      <p
        v-if="error"
        class="rounded-md border border-red-100 bg-red-50 p-3 text-xs text-danger"
        role="alert"
      >
        {{ error }}
      </p>
    </div>
  </article>
</template>
