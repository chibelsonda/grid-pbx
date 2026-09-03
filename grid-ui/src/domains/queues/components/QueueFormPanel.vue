<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { MegaphoneIcon, QueueListIcon, TrashIcon, UsersIcon } from '@heroicons/vue/24/outline'
import BasicAdvancedFormTabs from '@/shared/components/BasicAdvancedFormTabs.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useQueueForm } from '../composables/useQueueForm'
import type { Queue, QueueInput, QueueOptions } from '../types/queue'

const props = defineProps<{
  record: Queue | null
  options: QueueOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: QueueInput]; remove: [] }>()
const confirmDelete = ref(false)
const selectedTab = ref(0)
const { form, validate, validationErrors } = useQueueForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const basicFields = new Set(['name', 'strategy', 'music_on_hold_media_id', 'agent_ids'])
const strategyOptions: ListboxOptionValue[] = [
  { value: 'round_robin', label: 'Round robin' },
  { value: 'most_idle', label: 'Most idle' },
]
const exitKeyOptions: ListboxOptionValue[] = [
  '#',
  '*',
  '0',
  '1',
  '2',
  '3',
  '4',
  '5',
  '6',
  '7',
  '8',
  '9',
].map((value) => ({ value, label: value }))
const mediaOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Switch default' },
  ...props.options.media.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
])

type MediaField =
  | 'music_on_hold_media_id'
  | 'announce_media_id'
  | 'announcement_in_the_queue_media_id'
  | 'announcement_increase_in_call_volume_media_id'
  | 'announcement_estimated_wait_time_media_id'
  | 'announcement_position_media_id'
const announcementPromptFields: Array<{ field: MediaField; label: string }> = [
  { field: 'announcement_position_media_id', label: 'You are at position' },
  { field: 'announcement_in_the_queue_media_id', label: 'In the queue' },
  { field: 'announcement_estimated_wait_time_media_id', label: 'Estimated wait time is' },
  { field: 'announcement_increase_in_call_volume_media_id', label: 'Increase in call volume' },
]

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

function hasBasicError(fieldErrors: Record<string, string[]>): boolean {
  return Object.entries(fieldErrors).some(
    ([field, messages]) => Boolean(messages[0]) && basicFields.has(field.split('.')[0] ?? field),
  )
}

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    if (Object.keys(fieldErrors).length === 0) return
    selectedTab.value = hasBasicError(fieldErrors) ? 0 : 1
  },
  { deep: true, immediate: true },
)

function setStrategy(value: ListboxValue): void {
  if (value === 'round_robin' || value === 'most_idle') form.strategy = value
}

function setExitKey(value: ListboxValue): void {
  if (typeof value === 'string') form.caller_exit_key = value
}

function setMedia(field: MediaField, value: ListboxValue): void {
  if (value === null || typeof value === 'string') form[field] = value
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)

    return
  }

  selectedTab.value = hasBasicError(validationErrors.value) ? 0 : 1
}
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View queue' : record ? 'Edit queue' : 'Create queue'"
    eyebrow="GridPBX / Queues"
    description="Configure caller waiting behavior and the projected Switch agent roster."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the queue"
      />
      <fieldset :disabled="!canManage || saving" class="grid gap-5 disabled:opacity-75">
        <BasicAdvancedFormTabs v-model="selectedTab">
          <template #basic>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                  ><QueueListIcon class="size-5"
                /></span>
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Queue setup</h2>
                  <p class="text-[10px] text-heading-description">
                    Identity, routing strategy, and caller hold media.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model="form.name"
                  label="Name"
                  class="sm:col-span-2"
                  required
                  maxlength="128"
                  :error="fieldError('name')"
                />
                <label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Strategy</span
                  ><FormListbox
                    :model-value="form.strategy"
                    :options="strategyOptions"
                    aria-label="Queue strategy"
                    :invalid="Boolean(fieldError('strategy'))"
                    @update:model-value="setStrategy"
                  /><span v-if="fieldError('strategy')" class="text-[10px] text-danger">{{
                    fieldError('strategy')
                  }}</span></label
                >
                <label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Music on hold</span
                  ><FormListbox
                    :model-value="form.music_on_hold_media_id"
                    :options="mediaOptions"
                    aria-label="Music on hold"
                    :invalid="Boolean(fieldError('music_on_hold_media_id'))"
                    @update:model-value="setMedia('music_on_hold_media_id', $event)"
                  /><span
                    v-if="fieldError('music_on_hold_media_id')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('music_on_hold_media_id') }}</span
                  ></label
                >
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <UsersIcon class="size-5 text-brand-500" />
                <div>
                  <h2 id="queue-agent-roster-heading" class="text-sm font-semibold text-slate-700">
                    Agent roster
                  </h2>
                  <p class="text-[10px] text-heading-description">
                    Agents are existing extensions; Switch stores queue membership on their User
                    documents.
                  </p>
                </div>
              </header>
              <div class="grid gap-2 p-5" role="group" aria-labelledby="queue-agent-roster-heading">
                <FormCheckbox
                  v-for="agent in options.agents"
                  :key="agent.id"
                  :model-value="form.agent_ids"
                  :value="agent.id"
                  :label="agent.label"
                  :description="agent.detail"
                  :error="fieldError('agent_ids')"
                  @update:model-value="form.agent_ids = $event as string[]"
                />
                <p v-if="!options.agents.length" class="text-xs text-slate-400">
                  No projected extensions can act as agents.
                </p>
                <p v-if="fieldError('agent_ids')" class="text-[10px] text-danger">
                  {{ fieldError('agent_ids') }}
                </p>
              </div>
            </article>
          </template>
          <template #advanced>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                  ><QueueListIcon class="size-5"
                /></span>
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Queue behavior</h2>
                  <p class="text-[10px] text-heading-description">
                    Timing, capacity, exit, recording, and priority controls.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model.number="form.agent_ring_timeout"
                  label="Agent ring timeout"
                  type="number"
                  min="1"
                  max="300"
                  :error="fieldError('agent_ring_timeout')"
                />
                <FormInput
                  v-model.number="form.agent_wrapup_time"
                  label="Wrap-up time"
                  type="number"
                  min="0"
                  max="3600"
                  :error="fieldError('agent_wrapup_time')"
                />
                <FormInput
                  v-model.number="form.connection_timeout"
                  label="Connection timeout"
                  type="number"
                  min="0"
                  max="86400"
                  :error="fieldError('connection_timeout')"
                />
                <FormInput
                  v-model.number="form.max_queue_size"
                  label="Maximum callers"
                  description="0 = unlimited"
                  type="number"
                  min="0"
                  max="10000"
                  :error="fieldError('max_queue_size')"
                />
                <FormInput
                  v-model.number="form.ring_simultaneously"
                  label="Agents rung together"
                  type="number"
                  min="1"
                  max="100"
                  :error="fieldError('ring_simultaneously')"
                />
                <label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Caller exit key</span
                  ><FormListbox
                    :model-value="form.caller_exit_key"
                    :options="exitKeyOptions"
                    aria-label="Caller exit key"
                    :invalid="Boolean(fieldError('caller_exit_key'))"
                    @update:model-value="setExitKey"
                  /><span v-if="fieldError('caller_exit_key')" class="text-[10px] text-danger">{{
                    fieldError('caller_exit_key')
                  }}</span></label
                >
                <ToggleSwitch
                  v-model="form.enter_when_empty"
                  label="Allow entry when empty"
                  :class="validationControlClass(fieldError('enter_when_empty'))"
                  :invalid="Boolean(fieldError('enter_when_empty'))"
                />
                <ToggleSwitch
                  v-model="form.record_caller"
                  label="Record callers"
                  :class="validationControlClass(fieldError('record_caller'))"
                  :invalid="Boolean(fieldError('record_caller'))"
                />
                <FormInput
                  v-model.number="form.max_priority"
                  label="Maximum priority"
                  class="sm:col-span-2"
                  type="number"
                  min="0"
                  max="255"
                  :disabled="Boolean(record)"
                  input-class="disabled:bg-slate-50 disabled:text-slate-500"
                  placeholder="Switch default"
                  description="Create-only in the connected Switch schema; existing queues retain their value."
                  :error="fieldError('max_priority')"
                />
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <MegaphoneIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Caller announcements</h2>
                  <p class="text-[10px] text-heading-description">
                    Connection and periodic position or estimated-wait prompts.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <label class="grid gap-2 sm:col-span-2"
                  ><span class="text-xs font-semibold text-slate-600">Connect announcement</span
                  ><FormListbox
                    :model-value="form.announce_media_id"
                    :options="mediaOptions"
                    aria-label="Connect announcement"
                    :invalid="Boolean(fieldError('announce_media_id'))"
                    @update:model-value="setMedia('announce_media_id', $event)"
                  /><span v-if="fieldError('announce_media_id')" class="text-[10px] text-danger">{{
                    fieldError('announce_media_id')
                  }}</span></label
                >
                <ToggleSwitch
                  v-model="form.announcements_enabled"
                  label="Periodic announcements"
                  description="Enable position and estimated-wait announcements while callers wait"
                  class="sm:col-span-2 rounded-md border border-slate-200 p-3"
                  :class="validationControlClass(fieldError('announcements_enabled'))"
                  :invalid="Boolean(fieldError('announcements_enabled'))"
                />
                <template v-if="form.announcements_enabled">
                  <FormInput
                    v-model.number="form.announcement_interval"
                    label="Announcement interval (seconds)"
                    class="sm:col-span-2"
                    type="number"
                    min="15"
                    max="86400"
                    :error="fieldError('announcement_interval')"
                  />
                  <ToggleSwitch
                    v-model="form.position_announcements_enabled"
                    label="Announce queue position"
                    :class="validationControlClass(fieldError('position_announcements_enabled'))"
                    :invalid="Boolean(fieldError('position_announcements_enabled'))"
                  />
                  <ToggleSwitch
                    v-model="form.wait_time_announcements_enabled"
                    label="Announce estimated wait"
                    :class="validationControlClass(fieldError('wait_time_announcements_enabled'))"
                    :invalid="Boolean(fieldError('wait_time_announcements_enabled'))"
                  />
                  <div
                    class="grid gap-4 rounded-md border border-slate-200 p-4 sm:col-span-2 sm:grid-cols-2"
                    :class="validationControlClass(fieldError('announcement_media'))"
                  >
                    <div class="sm:col-span-2">
                      <p class="text-xs font-semibold text-slate-600">Custom prompt set</p>
                      <p class="mt-1 text-[10px] text-slate-500">
                        Select all four prompts or leave all four on the Switch defaults.
                      </p>
                    </div>
                    <label
                      v-for="prompt in announcementPromptFields"
                      :key="prompt.field"
                      class="grid gap-2"
                      ><span class="text-xs font-semibold text-slate-600">{{ prompt.label }}</span
                      ><FormListbox
                        :model-value="form[prompt.field]"
                        :options="mediaOptions"
                        :aria-label="prompt.label"
                        :invalid="
                          Boolean(fieldError(prompt.field) || fieldError('announcement_media'))
                        "
                        @update:model-value="setMedia(prompt.field, $event)"
                    /></label>
                    <p
                      v-if="fieldError('announcement_media')"
                      class="text-[10px] text-danger sm:col-span-2"
                    >
                      {{ fieldError('announcement_media') }}
                    </p>
                  </div>
                </template>
              </div>
            </article>
          </template>
        </BasicAdvancedFormTabs>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete queue
        </button>
      </div>
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
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save queue' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete queue"
    description="Delete this queue after checking its agents and call-routing dependencies?"
    confirm-label="Delete queue"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
