<script setup lang="ts">
import { computed } from 'vue'
import { BoltIcon, UserCircleIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { useAgentStatusForm } from '../composables/useAgentStatusForm'
import type { Agent, AgentStatus, AgentStatusInput } from '../types/queue'

const props = defineProps<{
  agent: Agent
  current: AgentStatus | null
  loading: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: AgentStatusInput] }>()
const { form, validate, validationErrors } = useAgentStatusForm()
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const statusOptions: ListboxOptionValue[] = [
  { value: 'login', label: 'Log in' },
  { value: 'logout', label: 'Log out' },
  { value: 'pause', label: 'Pause' },
  { value: 'resume', label: 'Resume' },
  { value: 'end_wrapup', label: 'End wrap-up' },
]

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
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Current Switch status
          </p>
          <p
            class="mt-1 text-sm font-semibold text-slate-700"
            :role="loading ? 'status' : undefined"
          >
            {{ loading ? 'Loading…' : (current?.status ?? 'Unknown') }}
          </p>
        </div>
      </article>
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
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
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
