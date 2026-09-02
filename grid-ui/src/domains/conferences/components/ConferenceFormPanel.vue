<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  KeyIcon,
  ServerStackIcon,
  Squares2X2Icon,
  TrashIcon,
  UserGroupIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import BasicAdvancedTabSelector from '@/shared/components/BasicAdvancedTabSelector.vue'
import AdvancedFormTabs from '@/shared/components/AdvancedFormTabs.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { useConferenceForm } from '../composables/useConferenceForm'
import type {
  Conference,
  ConferenceInput,
  ConferenceOptions,
  ConferenceToneMode,
} from '../types/conference'

const props = defineProps<{
  record: Conference | null
  options: ConferenceOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: ConferenceInput]; remove: [] }>()
const confirmDelete = ref(false)
const selectedView = ref(0)
const selectedSection = ref(0)
const advancedSections = [
  { key: 'basic', label: 'Basic', icon: Squares2X2Icon },
  { key: 'options', label: 'Options', icon: WrenchScrewdriverIcon },
  { key: 'conference-server', label: 'Conference Server', icon: ServerStackIcon },
] as const
const basicFields = [
  'name',
  'owner_id',
  'member_numbers',
  'member_pins',
  'clear_member_pin',
  'moderator_numbers',
  'moderator_pins',
  'clear_moderator_pin',
] as const
const optionFields = [
  'member_join_muted',
  'member_join_deaf',
  'member_play_entry_prompt',
  'moderator_join_muted',
  'moderator_join_deaf',
  'require_moderator',
  'wait_for_moderator',
  'play_name',
  'play_welcome',
  'max_members_media_id',
  'play_entry_tone_mode',
  'play_exit_tone_mode',
  'play_entry_tone_media_id',
  'play_exit_tone_media_id',
] as const
const conferenceServerFields = [
  'conference_numbers',
  'max_participants',
  'language',
  'profile_name',
  'caller_controls',
  'moderator_controls',
] as const
const { form, numbers, pins, validate, validationErrors } = useConferenceForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const mediaOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Switch default' },
  ...props.options.media.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
])
const baseToneOptions: ListboxOptionValue[] = [
  { value: 'enabled', label: 'Play the standard tone' },
  { value: 'disabled', label: 'Do not play a tone' },
  { value: 'media', label: 'Play selected media' },
]

function toneOptions(mode: ConferenceToneMode): ListboxOptionValue[] {
  return mode === 'current_custom'
    ? [
        ...baseToneOptions,
        {
          value: 'current_custom',
          label: 'Keep current custom Switch tone',
          description: 'This tone is not in the projected media catalog.',
          disabled: true,
        },
      ]
    : baseToneOptions
}

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

function routeToError(errorBag: Record<string, string[]>): void {
  if (basicFields.some((field) => errorBag[field]?.length)) {
    selectedView.value = 0
    selectedSection.value = 0

    return
  }

  selectedView.value = 1
  selectedSection.value = optionFields.some((field) => errorBag[field]?.length)
    ? 1
    : conferenceServerFields.some((field) => errorBag[field]?.length)
      ? 2
      : 0
}

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    if (Object.keys(fieldErrors).length > 0) routeToError(fieldErrors)
  },
  { deep: true, immediate: true },
)

watch(selectedView, (view) => {
  if (view === 0) selectedSection.value = 0
})

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)

    return
  }

  routeToError(validationErrors.value)
}

function setMedia(
  field: 'max_members_media_id' | 'play_entry_tone_media_id' | 'play_exit_tone_media_id',
  value: ListboxValue,
): void {
  if (value === null || typeof value === 'string') form[field] = value
}

function setToneMode(
  field: 'play_entry_tone_mode' | 'play_exit_tone_mode',
  value: ListboxValue,
): void {
  if (['enabled', 'disabled', 'media', 'current_custom'].includes(String(value))) {
    form[field] = value as ConferenceToneMode
  }
}
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View conference' : record ? 'Edit conference' : 'Create conference'"
    eyebrow="GridPBX / Conferences"
    description="Configure conference access roles, safe PIN replacement, and participant behavior."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="error && Object.keys(fieldErrors).length === 0"
        class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
      >
        {{ error }}
      </div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <BasicAdvancedTabSelector v-model="selectedView" />
        <AdvancedFormTabs
          v-model="selectedSection"
          :tabs="advancedSections"
          aria-label="Conference advanced sections"
          :active="selectedView === 1"
        >
          <div v-show="selectedSection === 0" role="tabpanel" class="grid gap-5">
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                  ><UserGroupIcon class="size-5"
                /></span>
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Conference identity</h2>
                  <p class="text-[10px] text-slate-400">Name and account-scoped owner.</p>
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
                <label class="grid gap-2 sm:col-span-2"
                  ><span class="text-xs font-semibold text-slate-600">Owner</span
                  ><FormSelect
                    v-model="form.owner_id"
                    aria-label="Owner"
                    :invalid="Boolean(fieldError('owner_id'))"
                    ><option :value="null">No owner</option>
                    <option v-for="owner in options.owners" :key="owner.id" :value="owner.id">
                      {{ owner.label }}{{ owner.detail ? ` · ${owner.detail}` : '' }}
                    </option></FormSelect
                  ><span v-if="fieldError('owner_id')" class="text-[10px] text-danger">{{
                    fieldError('owner_id')
                  }}</span></label
                >
              </div>
            </article>

            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <KeyIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Member access</h2>
                  <p class="text-[10px] text-slate-400">
                    PINs are write-only and never returned by the API.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model="numbers.member"
                  label="Member numbers"
                  class="sm:col-span-2"
                  inputmode="numeric"
                  placeholder="7001"
                  :error="fieldError('member_numbers')"
                />
                <FormInput
                  v-model="pins.member"
                  label="Member PINs"
                  :disabled="form.clear_member_pin"
                  inputmode="numeric"
                  input-class="disabled:opacity-50"
                  description="Comma- or space-separated; replaces the complete member PIN list."
                  :placeholder="
                    record?.member_pin_configured
                      ? 'Configured — enter PINs to replace'
                      : '1234, 5678 (optional)'
                  "
                  :error="fieldError('member_pins')"
                />
                <ToggleSwitch
                  v-if="record?.member_pin_configured"
                  v-model="form.clear_member_pin"
                  label="Remove current member PINs"
                  class="pt-7"
                  :invalid="Boolean(fieldError('clear_member_pin'))"
                  @update:model-value="pins.member = ''"
                />
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <KeyIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Moderator access</h2>
                  <p class="text-[10px] text-slate-400">
                    Moderator PINs are write-only and never returned by the API.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model="numbers.moderator"
                  label="Moderator numbers"
                  class="sm:col-span-2"
                  inputmode="numeric"
                  placeholder="7099"
                  :error="fieldError('moderator_numbers')"
                />
                <FormInput
                  v-model="pins.moderator"
                  label="Moderator PINs"
                  :disabled="form.clear_moderator_pin"
                  inputmode="numeric"
                  input-class="disabled:opacity-50"
                  description="Comma- or space-separated; replaces the complete moderator PIN list."
                  :placeholder="
                    record?.moderator_pin_configured
                      ? 'Configured — enter PINs to replace'
                      : '9876, 8765 (optional)'
                  "
                  :error="fieldError('moderator_pins')"
                />
                <ToggleSwitch
                  v-if="record?.moderator_pin_configured"
                  v-model="form.clear_moderator_pin"
                  label="Remove current moderator PINs"
                  class="pt-7"
                  :invalid="Boolean(fieldError('clear_moderator_pin'))"
                  @update:model-value="pins.moderator = ''"
                />
              </div>
            </article>
          </div>

          <div v-show="selectedSection === 1" role="tabpanel" class="grid gap-5">
            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Member behavior</h2>
                <p class="mt-1 text-[10px] text-slate-500">
                  Initial audio state and entry-prompt behavior.
                </p>
              </header>
              <div class="grid gap-3 p-5 sm:grid-cols-3">
                <ToggleSwitch
                  v-model="form.member_join_muted"
                  label="Join muted"
                  :invalid="Boolean(fieldError('member_join_muted'))"
                />
                <ToggleSwitch
                  v-model="form.member_join_deaf"
                  label="Join deaf"
                  :invalid="Boolean(fieldError('member_join_deaf'))"
                />
                <ToggleSwitch
                  v-model="form.member_play_entry_prompt"
                  label="Play entry prompt"
                  :invalid="Boolean(fieldError('member_play_entry_prompt'))"
                />
              </div>
            </article>

            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Moderator behavior</h2>
                <p class="mt-1 text-[10px] text-slate-400">
                  Moderator audio state and room-start behavior.
                </p>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                  <ToggleSwitch
                    v-model="form.moderator_join_muted"
                    label="Moderator joins muted"
                    :invalid="Boolean(fieldError('moderator_join_muted'))"
                  />
                  <ToggleSwitch
                    v-model="form.moderator_join_deaf"
                    label="Moderator joins deaf"
                    :invalid="Boolean(fieldError('moderator_join_deaf'))"
                  />
                </div>
                <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                  <ToggleSwitch
                    v-model="form.require_moderator"
                    label="Require moderator"
                    :invalid="Boolean(fieldError('require_moderator'))"
                  />
                  <ToggleSwitch
                    v-model="form.wait_for_moderator"
                    label="Members wait for moderator"
                    :invalid="Boolean(fieldError('wait_for_moderator'))"
                  />
                  <ToggleSwitch
                    v-model="form.play_name"
                    label="Announce participant names"
                    :invalid="Boolean(fieldError('play_name'))"
                  />
                  <ToggleSwitch
                    v-model="form.play_welcome"
                    label="Play welcome prompt"
                    :invalid="Boolean(fieldError('play_welcome'))"
                  />
                </div>
              </div>
            </article>

            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Conference sounds</h2>
                <p class="mt-1 text-[10px] text-slate-500">
                  Current-schema capacity and participant entry/exit audio behavior.
                </p>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <label class="grid gap-2 sm:col-span-2"
                  ><span class="text-xs font-semibold text-slate-600">Conference full prompt</span
                  ><FormListbox
                    :model-value="form.max_members_media_id"
                    :options="mediaOptions"
                    aria-label="Conference full prompt"
                    :invalid="Boolean(fieldError('max_members_media_id'))"
                    @update:model-value="setMedia('max_members_media_id', $event)"
                  /><span
                    v-if="fieldError('max_members_media_id')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('max_members_media_id') }}</span
                  ></label
                >
                <label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Participant entry tone</span
                  ><FormListbox
                    :model-value="form.play_entry_tone_mode"
                    :options="toneOptions(form.play_entry_tone_mode)"
                    aria-label="Participant entry tone"
                    :invalid="Boolean(fieldError('play_entry_tone_mode'))"
                    @update:model-value="setToneMode('play_entry_tone_mode', $event)"
                  /><span
                    v-if="fieldError('play_entry_tone_mode')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('play_entry_tone_mode') }}</span
                  ></label
                >
                <label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Participant exit tone</span
                  ><FormListbox
                    :model-value="form.play_exit_tone_mode"
                    :options="toneOptions(form.play_exit_tone_mode)"
                    aria-label="Participant exit tone"
                    :invalid="Boolean(fieldError('play_exit_tone_mode'))"
                    @update:model-value="setToneMode('play_exit_tone_mode', $event)"
                  /><span
                    v-if="fieldError('play_exit_tone_mode')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('play_exit_tone_mode') }}</span
                  ></label
                >
                <label v-if="form.play_entry_tone_mode === 'media'" class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Entry tone media</span
                  ><FormListbox
                    :model-value="form.play_entry_tone_media_id"
                    :options="mediaOptions"
                    aria-label="Entry tone media"
                    :invalid="Boolean(fieldError('play_entry_tone_media_id'))"
                    @update:model-value="setMedia('play_entry_tone_media_id', $event)"
                  /><span
                    v-if="fieldError('play_entry_tone_media_id')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('play_entry_tone_media_id') }}</span
                  ></label
                >
                <label v-if="form.play_exit_tone_mode === 'media'" class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Exit tone media</span
                  ><FormListbox
                    :model-value="form.play_exit_tone_media_id"
                    :options="mediaOptions"
                    aria-label="Exit tone media"
                    :invalid="Boolean(fieldError('play_exit_tone_media_id'))"
                    @update:model-value="setMedia('play_exit_tone_media_id', $event)"
                  /><span
                    v-if="fieldError('play_exit_tone_media_id')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('play_exit_tone_media_id') }}</span
                  ></label
                >
              </div>
            </article>
          </div>

          <div v-show="selectedSection === 2" role="tabpanel" class="grid gap-5">
            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Conference server</h2>
                <p class="mt-1 text-[10px] text-slate-500">
                  Access identifiers, room capacity, and prompt language.
                </p>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model="numbers.conference"
                  label="General conference numbers"
                  class="sm:col-span-2"
                  inputmode="numeric"
                  placeholder="7000, 7002"
                  description="Comma- or space-separated access identifiers; these are not purchased PSTN numbers."
                  :error="fieldError('conference_numbers')"
                />
                <FormInput
                  v-model.number="form.max_participants"
                  label="Maximum participants"
                  type="number"
                  min="1"
                  max="10000"
                  placeholder="No explicit limit"
                  :error="fieldError('max_participants')"
                />
                <FormInput
                  v-model="form.language"
                  label="Prompt language"
                  maxlength="16"
                  placeholder="en-US"
                  :error="fieldError('language')"
                />
              </div>
            </article>

            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Switch profiles</h2>
                <p class="mt-1 text-[10px] text-slate-500">
                  Named conference profiles and control sets from the installed Switch schema.
                </p>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model="form.profile_name"
                  label="Profile name"
                  maxlength="128"
                  :error="fieldError('profile_name')"
                /><FormInput
                  v-model="form.caller_controls"
                  label="Caller controls"
                  maxlength="128"
                  :error="fieldError('caller_controls')"
                /><FormInput
                  v-model="form.moderator_controls"
                  label="Moderator controls"
                  maxlength="128"
                  :error="fieldError('moderator_controls')"
                />
              </div>
            </article>
          </div>
        </AdvancedFormTabs>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete conference
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
          {{ saving ? 'Saving…' : 'Save conference' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete conference"
    description="Delete this conference after checking its call-routing dependencies?"
    confirm-label="Delete conference"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
