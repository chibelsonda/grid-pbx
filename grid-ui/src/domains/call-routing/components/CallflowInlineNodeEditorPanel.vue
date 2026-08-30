<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, ShieldCheckIcon, TrashIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { findCallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import { useCallflowInlineNodeForm } from '../composables/useCallflowInlineNodeForm'
import { callflowDtmfDigits } from '../schemas/callflowInlineNodeFormSchema'
import type {
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
  CallflowAlertRecipient,
  CallflowEditor,
  CallflowNodeEditorContext,
  CallflowTreeBranchKey,
} from '../types/callRouting'

const props = withDefaults(
  defineProps<{
    context: CallflowNodeEditorContext
    editor?: CallflowEditor | null
    loading?: boolean
    saving: boolean
    error: string | null
    fieldErrors: Record<string, string[]>
  }>(),
  { editor: null, loading: false },
)
const emit = defineEmits<{
  close: []
  save: [input: CallflowInlineNodeCreateInput | CallflowInlineNodeUpdateInput]
}>()
const { form, module, branches, validationErrors, validate } = useCallflowInlineNodeForm(
  () => props.context,
)
const action = computed(() => findCallflowAction(module.value))
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const branchOptions = computed<ListboxOptionValue[]>(() => branches.value)
const title = computed(() =>
  props.context.operation === 'create'
    ? `Add ${action.value?.label ?? 'callflow action'}`
    : `Edit ${action.value?.label ?? 'callflow action'}`,
)
const actionIcon = computed(() => callflowActionIcon(module.value))

const unitOptions: ListboxOptionValue[] = [
  { value: 'ms', label: 'Milliseconds' },
  { value: 's', label: 'Seconds' },
  { value: 'm', label: 'Minutes' },
  { value: 'h', label: 'Hours' },
]
const engineOptions: ListboxOptionValue[] = [
  { value: null, label: 'Switch default' },
  { value: 'flite', label: 'Flite' },
  { value: 'google', label: 'Google' },
  { value: 'ispeech', label: 'iSpeech' },
  { value: 'voicefabric', label: 'VoiceFabric' },
]
const recordingFormatOptions: ListboxOptionValue[] = [
  { value: null, label: 'Switch default' },
  { value: 'mp3', label: 'MP3' },
  { value: 'wav', label: 'WAV' },
]
const recordingActionOptions: ListboxOptionValue[] = [
  { value: 'start', label: 'Start recording' },
  { value: 'stop', label: 'Stop recording' },
]
const recipientTypeOptions: ListboxOptionValue[] = [
  { value: 'email', label: 'Email address' },
  { value: 'user', label: 'Extension user' },
]
const extensionOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.destinations.extension ?? []).map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function setBranch(value: ListboxValue): void {
  if (branches.value.some((option) => option.value === value)) {
    form.branch = value as CallflowTreeBranchKey
  }
}

function setTerminators(value: boolean | string[]): void {
  if (Array.isArray(value)) form.data.terminators = value
}

function recipients(): CallflowAlertRecipient[] {
  return form.data.recipients ?? (form.data.recipients = [])
}

function addRecipient(type: CallflowAlertRecipient['type']): void {
  recipients().push({ type, id: '' })
}

function setRecipientType(index: number, value: ListboxValue): void {
  if (value !== 'user' && value !== 'email') return
  const recipient = recipients()[index]
  if (recipient) recipients()[index] = { type: value, id: '' }
}

function setRecipientId(index: number, value: string): void {
  const recipient = recipients()[index]
  if (recipient) recipient.id = value
}

function removeRecipient(index: number): void {
  recipients().splice(index, 1)
}

function submit(): void {
  const input = validate()
  if (input) emit('save', input)
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    eyebrow="GridPBX / Call Routing / Action"
    description="Configure the public Switch schema fields for this inline action."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <section class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
            <component :is="actionIcon" class="size-4" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">
              {{ action?.label ?? context.module }}
            </h2>
            <p class="mt-0.5 font-mono text-[10px] text-slate-500">{{ context.module }}</p>
          </div>
        </header>

        <div class="grid gap-5 p-5">
          <label v-if="context.operation === 'create'" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-700">Parent branch</span>
            <FormListbox
              :model-value="form.branch"
              :options="branchOptions"
              aria-label="Parent branch"
              :invalid="Boolean(fieldError('branch'))"
              placeholder="Select an empty branch"
              @update:model-value="setBranch"
            />
            <span v-if="fieldError('branch')" class="text-[10px] font-medium text-danger">
              {{ fieldError('branch') }}
            </span>
          </label>

          <template v-if="module === 'sleep'">
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.duration ?? null"
                label="Duration"
                type="number"
                min="0"
                max="86400000"
                required
                :error="fieldError('data.duration')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.duration = Number($event)"
              />
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Unit</span>
                <FormListbox
                  :model-value="form.data.unit ?? 's'"
                  :options="unitOptions"
                  aria-label="Duration unit"
                  :invalid="Boolean(fieldError('data.unit'))"
                  @update:model-value="form.data.unit = $event as 'ms' | 's' | 'm' | 'h'"
                />
                <span v-if="fieldError('data.unit')" class="text-[10px] text-danger">
                  {{ fieldError('data.unit') }}
                </span>
              </label>
            </div>
          </template>

          <template v-if="module === 'tts'">
            <FormTextarea
              :model-value="form.data.text ?? ''"
              label="Text to speak"
              description="1–1000 characters sent to the selected Switch speech engine."
              required
              :error="fieldError('data.text')"
              @update:model-value="form.data.text = $event"
            />
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.voice ?? ''"
                label="Voice"
                placeholder="female"
                :error="fieldError('data.voice')"
                @update:model-value="form.data.voice = String($event)"
              />
              <FormInput
                :model-value="form.data.language ?? ''"
                label="Language"
                placeholder="en-US"
                :error="fieldError('data.language')"
                @update:model-value="form.data.language = String($event)"
              />
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Speech engine</span>
                <FormListbox
                  :model-value="form.data.engine ?? null"
                  :options="engineOptions"
                  aria-label="Speech engine"
                  :invalid="Boolean(fieldError('data.engine'))"
                  @update:model-value="
                    form.data.engine = $event as
                      'flite' | 'google' | 'ispeech' | 'voicefabric' | null
                  "
                />
                <span v-if="fieldError('data.engine')" class="text-[10px] text-danger">
                  {{ fieldError('data.engine') }}
                </span>
              </label>
            </div>
          </template>

          <template v-if="module === 'collect_dtmf'">
            <FormInput
              :model-value="form.data.collection_name ?? ''"
              label="Collection name"
              description="Optional variable name used to retain the collected digits."
              :error="fieldError('data.collection_name')"
              @update:model-value="form.data.collection_name = String($event)"
            />
            <div class="grid gap-4 sm:grid-cols-3">
              <FormInput
                :model-value="form.data.max_digits ?? null"
                label="Maximum digits"
                type="number"
                min="1"
                max="128"
                required
                :error="fieldError('data.max_digits')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.max_digits = Number($event)"
              />
              <FormInput
                :model-value="form.data.timeout ?? null"
                label="Overall timeout (ms)"
                type="number"
                min="1"
                required
                :error="fieldError('data.timeout')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.timeout = Number($event)"
              />
              <FormInput
                :model-value="form.data.interdigit_timeout ?? null"
                label="Interdigit timeout (ms)"
                type="number"
                min="1"
                required
                :error="fieldError('data.interdigit_timeout')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.interdigit_timeout = Number($event)"
              />
            </div>
          </template>

          <template v-if="module === 'send_dtmf'">
            <FormInput
              :model-value="form.data.digits ?? ''"
              label="DTMF digits"
              description="Digits and keypad symbols sent in order to the active call."
              placeholder="1234#"
              required
              :error="fieldError('data.digits')"
              @update:model-value="form.data.digits = String($event)"
            />
            <FormInput
              :model-value="form.data.duration_ms ?? null"
              label="Tone duration (ms)"
              type="number"
              min="1"
              max="60000"
              required
              :error="fieldError('data.duration_ms')"
              :model-modifiers="{ number: true }"
              @update:model-value="form.data.duration_ms = Number($event)"
            />
          </template>

          <FormInput
            v-if="module === 'flush_dtmf'"
            :model-value="form.data.collection_name ?? ''"
            label="Collection name"
            description="The buffered digit collection to clear. Kazoo defaults this to default."
            required
            :error="fieldError('data.collection_name')"
            @update:model-value="form.data.collection_name = String($event)"
          />

          <FormInput
            v-if="module === 'language'"
            :model-value="form.data.language ?? ''"
            label="Call language"
            description="Two-letter language with an optional region, such as en or en-US."
            placeholder="en-US"
            required
            :error="fieldError('data.language')"
            @update:model-value="form.data.language = String($event)"
          />

          <div
            v-if="module === 'dead_air'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
          >
            Dead Air suppresses media and waits for the caller to hang up. It is normally used as a
            terminal action.
          </div>

          <section v-if="module === 'missed_call_alert'" class="grid gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <div class="mr-auto">
                <h3 class="text-xs font-semibold text-slate-700">Notification recipients</h3>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  Public extension IDs are translated to private Switch IDs by the API.
                </p>
              </div>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-[10px] font-semibold text-slate-700"
                @click="addRecipient('email')"
              >
                <PlusIcon class="size-3.5" /> Email
              </button>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-[10px] font-semibold text-slate-700"
                @click="addRecipient('user')"
              >
                <PlusIcon class="size-3.5" /> Extension
              </button>
            </div>

            <p v-if="loading" class="text-xs text-slate-500">Loading synchronized extensions…</p>
            <div
              v-for="(recipient, index) in form.data.recipients ?? []"
              :key="`${index}-${recipient.type}`"
              class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[10rem_minmax(0,1fr)_2rem]"
            >
              <FormListbox
                :model-value="recipient.type"
                :options="recipientTypeOptions"
                :aria-label="`Recipient ${index + 1} type`"
                @update:model-value="setRecipientType(index, $event)"
              />
              <FormInput
                v-if="recipient.type === 'email'"
                :model-value="recipient.id"
                :label="`Recipient ${index + 1} email`"
                type="email"
                required
                :error="fieldError(`data.recipients.${index}.id`)"
                @update:model-value="setRecipientId(index, String($event))"
              />
              <label v-else class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Extension</span>
                <FormListbox
                  :model-value="recipient.id || null"
                  :options="extensionOptions"
                  :aria-label="`Recipient ${index + 1} extension`"
                  :invalid="Boolean(fieldError(`data.recipients.${index}.id`))"
                  :disabled="loading || extensionOptions.length === 0"
                  placeholder="Select an extension"
                  @update:model-value="typeof $event === 'string' && setRecipientId(index, $event)"
                />
                <span
                  v-if="fieldError(`data.recipients.${index}.id`)"
                  class="text-[10px] text-danger"
                >
                  {{ fieldError(`data.recipients.${index}.id`) }}
                </span>
              </label>
              <button
                type="button"
                :aria-label="`Remove recipient ${index + 1}`"
                class="grid size-8 place-items-center rounded-md border border-red-200 bg-white text-danger hover:bg-red-50"
                @click="removeRecipient(index)"
              >
                <TrashIcon class="size-4" />
              </button>
            </div>
            <p v-if="fieldError('data.recipients')" class="text-[10px] text-danger">
              {{ fieldError('data.recipients') }}
            </p>
          </section>

          <section v-if="module === 'tts' || module === 'collect_dtmf'" class="grid gap-3">
            <div>
              <h3 class="text-xs font-semibold text-slate-700">DTMF terminators</h3>
              <p class="mt-0.5 text-[10px] text-slate-500">
                These keys stop playback or complete digit collection.
              </p>
            </div>
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
              <FormCheckbox
                v-for="digit in callflowDtmfDigits"
                :key="digit"
                :model-value="form.data.terminators ?? []"
                :value="digit"
                :label="digit"
                variant="compact"
                @update:model-value="setTerminators"
              />
            </div>
            <p v-if="fieldError('data.terminators')" class="text-[10px] text-danger">
              {{ fieldError('data.terminators') }}
            </p>
          </section>

          <template v-if="module === 'record_call' || module === 'record_caller'">
            <div class="grid gap-4 sm:grid-cols-2">
              <label v-if="module === 'record_call'" class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Recording action</span>
                <FormListbox
                  :model-value="form.data.action ?? 'start'"
                  :options="recordingActionOptions"
                  aria-label="Recording action"
                  :invalid="Boolean(fieldError('data.action'))"
                  @update:model-value="form.data.action = $event as 'start' | 'stop'"
                />
                <span v-if="fieldError('data.action')" class="text-[10px] text-danger">
                  {{ fieldError('data.action') }}
                </span>
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Format</span>
                <FormListbox
                  :model-value="form.data.format ?? null"
                  :options="recordingFormatOptions"
                  aria-label="Recording format"
                  :invalid="Boolean(fieldError('data.format'))"
                  @update:model-value="form.data.format = $event as 'mp3' | 'wav' | null"
                />
                <span v-if="fieldError('data.format')" class="text-[10px] text-danger">
                  {{ fieldError('data.format') }}
                </span>
              </label>
              <FormInput
                :model-value="form.data.time_limit ?? null"
                label="Time limit (seconds)"
                type="number"
                min="5"
                max="10800"
                required
                :error="fieldError('data.time_limit')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.time_limit = Number($event)"
              />
              <FormInput
                v-if="module === 'record_call'"
                :model-value="form.data.label ?? ''"
                label="Recording label"
                :error="fieldError('data.label')"
                @update:model-value="form.data.label = String($event)"
              />
              <FormInput
                v-if="module === 'record_call'"
                :model-value="form.data.record_min_sec ?? null"
                label="Minimum length (seconds)"
                type="number"
                min="0"
                max="10800"
                :error="fieldError('data.record_min_sec')"
                :model-modifiers="{ number: true }"
                @update:model-value="
                  form.data.record_min_sec = $event === '' ? null : Number($event)
                "
              />
              <FormInput
                v-if="module === 'record_call'"
                :model-value="form.data.record_sample_rate ?? null"
                label="Sample rate (Hz)"
                type="number"
                min="8000"
                max="192000"
                :error="fieldError('data.record_sample_rate')"
                :model-modifiers="{ number: true }"
                @update:model-value="
                  form.data.record_sample_rate = $event === '' ? null : Number($event)
                "
              />
            </div>
          </template>
        </div>
      </section>

      <section class="card-surface grid gap-4 p-5">
        <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Behavior</h2>
        <ToggleSwitch
          v-if="module === 'tts'"
          :model-value="Boolean(form.data.endless_playback)"
          label="Endless playback"
          description="Repeat speech until a terminator is entered or the call ends."
          @update:model-value="form.data.endless_playback = $event"
        />
        <ToggleSwitch
          v-if="module === 'record_call'"
          :model-value="Boolean(form.data.record_on_answer)"
          label="Record on answer"
          description="Delay recording until the call is answered."
          @update:model-value="form.data.record_on_answer = $event"
        />
        <ToggleSwitch
          v-if="module === 'record_call'"
          :model-value="Boolean(form.data.record_on_bridge)"
          label="Record on bridge"
          description="Delay recording until both call legs are bridged."
          @update:model-value="form.data.record_on_bridge = $event"
        />
        <ToggleSwitch
          v-if="module === 'record_call'"
          :model-value="Boolean(form.data.should_follow_transfer)"
          label="Follow transfers"
          description="Continue recording if the call is transferred."
          @update:model-value="form.data.should_follow_transfer = $event"
        />
        <ToggleSwitch
          :model-value="Boolean(form.data.skip_module)"
          label="Skip this action"
          description="Preserve the action but ask Switch not to execute it."
          @update:model-value="form.data.skip_module = $event"
        />
      </section>

      <div
        class="flex gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          Storage URLs, HTTP methods, origins, and media names are server-managed. Existing values
          and child branches are preserved without exposing them to this form.
        </p>
      </div>

      <p v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </p>

      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-700"
          :disabled="saving"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="saving"
        >
          {{ saving ? 'Saving…' : context.operation === 'create' ? 'Add action' : 'Save action' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
