<script setup lang="ts">
import { computed, ref } from 'vue'
import { KeyIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
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
const { form, numbers, validate, validationErrors } = useConferenceForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const mediaOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Switch default' },
  ...props.options.media.map(({ id, label, detail }) => ({ value: id, label, description: detail })),
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

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) emit('save', result.data)
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
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><UserGroupIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Conference identity</h2>
              <p class="text-[10px] text-slate-400">Name, owner, access numbers, and capacity.</p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Name</span
              ><input
                v-model="form.name"
                aria-label="Name"
                required
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('name'))"
                :aria-invalid="Boolean(fieldError('name'))"
              /><span v-if="fieldError('name')" class="text-[10px] text-danger">{{
                fieldError('name')
              }}</span></label
            >
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
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">General conference numbers</span
              ><input
                v-model="numbers.conference"
                aria-label="General conference numbers"
                inputmode="numeric"
                class="field-control"
                :class="validationControlClass(fieldError('conference_numbers'))"
                :aria-invalid="Boolean(fieldError('conference_numbers'))"
                placeholder="7000, 7002"
              /><span v-if="fieldError('conference_numbers')" class="text-[10px] text-danger">{{ fieldError('conference_numbers') }}</span
              ><span class="text-[10px] text-slate-500"
                >Comma- or space-separated access identifiers; these are not purchased PSTN
                numbers.</span
              ></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Maximum participants</span
              ><input
                v-model.number="form.max_participants"
                type="number"
                min="1"
                max="10000"
                class="field-control"
                :class="validationControlClass(fieldError('max_participants'))"
                :aria-invalid="Boolean(fieldError('max_participants'))"
                placeholder="No explicit limit"
              /><span v-if="fieldError('max_participants')" class="text-[10px] text-danger">{{ fieldError('max_participants') }}</span></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Prompt language</span
              ><input
                v-model="form.language"
                maxlength="16"
                class="field-control"
                :class="validationControlClass(fieldError('language'))"
                :aria-invalid="Boolean(fieldError('language'))"
                placeholder="en-US"
              /><span v-if="fieldError('language')" class="text-[10px] text-danger">{{ fieldError('language') }}</span></label>
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
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Member numbers</span
              ><input
                v-model="numbers.member"
                aria-label="Member numbers"
                inputmode="numeric"
                class="field-control"
                :class="validationControlClass(fieldError('member_numbers'))"
                :aria-invalid="Boolean(fieldError('member_numbers'))"
                placeholder="7001"
              /><span v-if="fieldError('member_numbers')" class="text-[10px] text-danger">{{ fieldError('member_numbers') }}</span></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Member PIN</span
              ><input
                v-model="form.member_pin"
                :disabled="form.clear_member_pin"
                inputmode="numeric"
                maxlength="32"
                class="field-control disabled:opacity-50"
                :class="validationControlClass(fieldError('member_pin'))"
                :aria-invalid="Boolean(fieldError('member_pin'))"
                :placeholder="
                  record?.member_pin_configured ? 'Configured — enter to replace' : 'Optional'
                "
              /><span v-if="fieldError('member_pin')" class="text-[10px] text-danger">{{ fieldError('member_pin') }}</span></label>
            <ToggleSwitch
              v-if="record?.member_pin_configured"
              v-model="form.clear_member_pin"
              label="Remove current member PIN"
              class="pt-7"
              :invalid="Boolean(fieldError('clear_member_pin'))"
              @update:model-value="form.member_pin = null"
            />
            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-3">
              <ToggleSwitch v-model="form.member_join_muted" label="Join muted" :invalid="Boolean(fieldError('member_join_muted'))" />
              <ToggleSwitch v-model="form.member_join_deaf" label="Join deaf" :invalid="Boolean(fieldError('member_join_deaf'))" />
              <ToggleSwitch v-model="form.member_play_entry_prompt" label="Play entry prompt" :invalid="Boolean(fieldError('member_play_entry_prompt'))" />
            </div>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Moderator access & behavior</h2>
            <p class="mt-1 text-[10px] text-slate-400">
              Separate access credentials and room-start behavior.
            </p>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Moderator numbers</span
              ><input
                v-model="numbers.moderator"
                aria-label="Moderator numbers"
                inputmode="numeric"
                class="field-control"
                :class="validationControlClass(fieldError('moderator_numbers'))"
                :aria-invalid="Boolean(fieldError('moderator_numbers'))"
                placeholder="7099"
              /><span v-if="fieldError('moderator_numbers')" class="text-[10px] text-danger">{{ fieldError('moderator_numbers') }}</span></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Moderator PIN</span
              ><input
                v-model="form.moderator_pin"
                :disabled="form.clear_moderator_pin"
                inputmode="numeric"
                maxlength="32"
                class="field-control disabled:opacity-50"
                :class="validationControlClass(fieldError('moderator_pin'))"
                :aria-invalid="Boolean(fieldError('moderator_pin'))"
                :placeholder="
                  record?.moderator_pin_configured ? 'Configured — enter to replace' : 'Optional'
                "
              /><span v-if="fieldError('moderator_pin')" class="text-[10px] text-danger">{{ fieldError('moderator_pin') }}</span></label>
            <ToggleSwitch
              v-if="record?.moderator_pin_configured"
              v-model="form.clear_moderator_pin"
              label="Remove current moderator PIN"
              class="pt-7"
              :invalid="Boolean(fieldError('clear_moderator_pin'))"
              @update:model-value="form.moderator_pin = null"
            />
            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
              <ToggleSwitch v-model="form.moderator_join_muted" label="Moderator joins muted" :invalid="Boolean(fieldError('moderator_join_muted'))" />
              <ToggleSwitch v-model="form.moderator_join_deaf" label="Moderator joins deaf" :invalid="Boolean(fieldError('moderator_join_deaf'))" />
            </div>
            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
              <ToggleSwitch v-model="form.require_moderator" label="Require moderator" :invalid="Boolean(fieldError('require_moderator'))" />
              <ToggleSwitch v-model="form.wait_for_moderator" label="Members wait for moderator" :invalid="Boolean(fieldError('wait_for_moderator'))" />
              <ToggleSwitch v-model="form.play_name" label="Announce participant names" :invalid="Boolean(fieldError('play_name'))" />
              <ToggleSwitch v-model="form.play_welcome" label="Play welcome prompt" :invalid="Boolean(fieldError('play_welcome'))" />
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
              /><span v-if="fieldError('max_members_media_id')" class="text-[10px] text-danger">{{ fieldError('max_members_media_id') }}</span></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Participant entry tone</span
              ><FormListbox
                :model-value="form.play_entry_tone_mode"
                :options="toneOptions(form.play_entry_tone_mode)"
                aria-label="Participant entry tone"
                :invalid="Boolean(fieldError('play_entry_tone_mode'))"
                @update:model-value="setToneMode('play_entry_tone_mode', $event)"
              /><span v-if="fieldError('play_entry_tone_mode')" class="text-[10px] text-danger">{{ fieldError('play_entry_tone_mode') }}</span></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Participant exit tone</span
              ><FormListbox
                :model-value="form.play_exit_tone_mode"
                :options="toneOptions(form.play_exit_tone_mode)"
                aria-label="Participant exit tone"
                :invalid="Boolean(fieldError('play_exit_tone_mode'))"
                @update:model-value="setToneMode('play_exit_tone_mode', $event)"
              /><span v-if="fieldError('play_exit_tone_mode')" class="text-[10px] text-danger">{{ fieldError('play_exit_tone_mode') }}</span></label
            >
            <label v-if="form.play_entry_tone_mode === 'media'" class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Entry tone media</span
              ><FormListbox
                :model-value="form.play_entry_tone_media_id"
                :options="mediaOptions"
                aria-label="Entry tone media"
                :invalid="Boolean(fieldError('play_entry_tone_media_id'))"
                @update:model-value="setMedia('play_entry_tone_media_id', $event)"
              /><span v-if="fieldError('play_entry_tone_media_id')" class="text-[10px] text-danger">{{ fieldError('play_entry_tone_media_id') }}</span></label
            >
            <label v-if="form.play_exit_tone_mode === 'media'" class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Exit tone media</span
              ><FormListbox
                :model-value="form.play_exit_tone_media_id"
                :options="mediaOptions"
                aria-label="Exit tone media"
                :invalid="Boolean(fieldError('play_exit_tone_media_id'))"
                @update:model-value="setMedia('play_exit_tone_media_id', $event)"
              /><span v-if="fieldError('play_exit_tone_media_id')" class="text-[10px] text-danger">{{ fieldError('play_exit_tone_media_id') }}</span></label
            >
          </div>
        </article>

        <DisclosureCard title="Advanced Switch profiles"
          ><div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Profile name</span
              ><input
                v-model="form.profile_name"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('profile_name'))"
                :aria-invalid="Boolean(fieldError('profile_name'))" /><span v-if="fieldError('profile_name')" class="text-[10px] text-danger">{{ fieldError('profile_name') }}</span></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Caller controls</span
              ><input
                v-model="form.caller_controls"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('caller_controls'))"
                :aria-invalid="Boolean(fieldError('caller_controls'))" /><span v-if="fieldError('caller_controls')" class="text-[10px] text-danger">{{ fieldError('caller_controls') }}</span></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Moderator controls</span
              ><input
                v-model="form.moderator_controls"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('moderator_controls'))"
                :aria-invalid="Boolean(fieldError('moderator_controls'))"
              /><span v-if="fieldError('moderator_controls')" class="text-[10px] text-danger">{{ fieldError('moderator_controls') }}</span></label></div
        ></DisclosureCard>
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
