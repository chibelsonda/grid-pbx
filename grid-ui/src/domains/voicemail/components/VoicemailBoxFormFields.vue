<script setup lang="ts">
import { watch } from 'vue'
import {
  EnvelopeIcon,
  KeyIcon,
  LinkIcon,
  MicrophoneIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useVoicemailFormOptions } from '../composables/useVoicemailFormOptions'
import type {
  VoicemailBoxBasicForm,
  VoicemailBoxConfiguration,
  VoicemailFormOptions,
  VoicemailNotificationCallback,
} from '../types/voicemail'

const props = withDefaults(
  defineProps<{
    fieldErrors: Record<string, string[]>
    options: VoicemailFormOptions
    editing?: boolean
    pinConfigured?: boolean
    lockIdentity?: boolean
    showAssignment?: boolean
  }>(),
  {
    editing: false,
    pinConfigured: false,
    lockIdentity: false,
    showAssignment: true,
  },
)

const form = defineModel<VoicemailBoxBasicForm>('form', { required: true })
const configuration = defineModel<VoicemailBoxConfiguration>('configuration', { required: true })
const callbackConfigured = defineModel<boolean>('callbackConfigured', { required: true })
const callbackSchedule = defineModel<string>('callbackSchedule', { required: true })
const notificationCallback = defineModel<VoicemailNotificationCallback>('notificationCallback', {
  required: true,
})

const { timezoneOptions, extensionOptions } = useVoicemailFormOptions(
  () => props.options,
  () => form.value.timezone,
  () => form.value.assigned_extension_id,
)

watch(
  () => configuration.value.save_after_notify,
  (saveAfterNotify) => {
    if (saveAfterNotify) configuration.value.delete_after_notify = false
  },
)

function fieldError(field: string): string | null {
  const direct = props.fieldErrors[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(props.fieldErrors).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}
</script>

<template>
  <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
    <div class="grid content-start gap-5">
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
            <MicrophoneIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Mailbox identity</h2>
            <p class="text-[10px] text-slate-500">
              {{
                lockIdentity
                  ? 'Managed by the parent Extension workflow'
                  : 'Name and number callers use to reach voicemail'
              }}
            </p>
          </div>
        </header>
        <div class="grid gap-5 p-5 sm:grid-cols-2">
          <FormInput
            v-model="form.name"
            label="Mailbox name"
            required
            maxlength="128"
            placeholder="Reception voicemail"
            :readonly="lockIdentity"
            :error="fieldError('name')"
          />
          <FormInput
            v-model="form.mailbox"
            label="Mailbox number"
            required
            maxlength="30"
            inputmode="numeric"
            pattern="[0-9]+"
            placeholder="1001"
            input-class="font-mono"
            :readonly="lockIdentity"
            :error="fieldError('mailbox')"
          />
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Timezone</span>
            <FormListbox
              v-model="form.timezone"
              :options="timezoneOptions"
              :invalid="Boolean(fieldError('timezone'))"
              aria-label="Timezone"
            />
            <span v-if="fieldError('timezone')" class="text-[11px] text-danger">{{
              fieldError('timezone')
            }}</span>
          </label>
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <EnvelopeIcon class="size-5 text-emerald-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Notifications</h2>
            <p class="text-[10px] text-slate-500">One email address per line, up to ten</p>
          </div>
        </header>
        <div class="p-5">
          <FormTextarea
            v-model="form.notification_emails"
            label="Notification email addresses"
            placeholder="support@example.com&#10;manager@example.com"
            textarea-class="leading-5"
            :error="fieldError('notification_emails')"
          />
        </div>
      </article>

      <DisclosureCard title="Advanced notification delivery">
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Voicemail audio format</span>
            <FormListbox
              v-model="configuration.media_extension"
              :invalid="Boolean(fieldError('media_extension'))"
              :options="[
                { value: 'mp3', label: 'MP3' },
                { value: 'mp4', label: 'MP4' },
                { value: 'wav', label: 'WAV' },
              ]"
            />
          </label>
          <ToggleSwitch
            v-model="configuration.include_message_on_notify"
            label="Attach voicemail audio"
            description="Include the recording with email notifications"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('include_message_on_notify'))"
            :invalid="Boolean(fieldError('include_message_on_notify'))"
          />
          <ToggleSwitch
            v-model="configuration.include_transcription_on_notify"
            label="Include transcription"
            description="Add ASR text to notification emails"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('include_transcription_on_notify'))"
            :invalid="Boolean(fieldError('include_transcription_on_notify'))"
          />
          <ToggleSwitch
            v-model="configuration.save_after_notify"
            label="Save after notification"
            description="Move the message to Saved after notification"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('save_after_notify'))"
            :invalid="Boolean(fieldError('save_after_notify'))"
          />
          <ToggleSwitch
            v-model="configuration.delete_after_notify"
            label="Delete after notification"
            description="Move the message to Deleted unless Save is enabled"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('delete_after_notify'))"
            :invalid="Boolean(fieldError('delete_after_notify'))"
            :disabled="configuration.save_after_notify"
          />
        </div>
      </DisclosureCard>

      <DisclosureCard title="Callback notification">
        <div class="grid gap-4">
          <ToggleSwitch
            v-model="callbackConfigured"
            label="Configure callback notification"
            description="Call a number when this mailbox receives a new message"
            class="rounded-md border border-slate-200 p-3"
          />
          <template v-if="callbackConfigured">
            <ToggleSwitch
              v-model="notificationCallback.disabled"
              label="Pause callback attempts"
              description="Keep the callback configuration without placing calls"
              class="rounded-md border border-slate-200 p-3"
              :class="validationControlClass(fieldError('notify_callback.disabled'))"
              :invalid="Boolean(fieldError('notify_callback.disabled'))"
            />
            <FormInput
              v-model="notificationCallback.number"
              label="Callback number"
              maxlength="64"
              input-class="font-mono"
              placeholder="+15551234567"
              :error="fieldError('notify_callback.number')"
            />
            <div class="grid gap-4 sm:grid-cols-3">
              <FormInput
                v-for="control in [
                  { key: 'attempts', label: 'Attempts', max: 100 },
                  { key: 'interval_s', label: 'Retry interval', max: 604800 },
                  { key: 'timeout_s', label: 'Answer timeout', max: 3600 },
                ] as const"
                :key="control.key"
                v-model.number="notificationCallback[control.key]"
                :label="control.label"
                type="number"
                min="0"
                :max="control.max"
                :error="fieldError(`notify_callback.${control.key}`)"
              />
            </div>
            <FormTextarea
              v-model="callbackSchedule"
              label="Callback schedule"
              size="compact"
              textarea-class="font-mono leading-5"
              placeholder="60, 300, 900"
              description="Optional intervals in seconds, separated by commas or new lines."
              :error="fieldError('notify_callback.schedule')"
            />
          </template>
        </div>
      </DisclosureCard>
    </div>

    <div class="grid content-start gap-5">
      <article v-if="showAssignment" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <LinkIcon class="size-5 text-blue-500" />
          <h2 class="text-sm font-semibold text-slate-700">Assignment</h2>
        </header>
        <div class="p-5">
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Extension</span>
            <FormListbox
              v-model="form.assigned_extension_id"
              :options="extensionOptions"
              :invalid="Boolean(fieldError('assigned_extension_id'))"
              aria-label="Assigned extension"
            />
          </label>
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <SparklesIcon class="size-5 text-violet-500" />
          <h2 class="text-sm font-semibold text-slate-700">Features</h2>
        </header>
        <div class="grid gap-3 p-5">
          <ToggleSwitch
            v-model="form.transcribe"
            label="Transcribe messages"
            description="Uses the configured Switch ASR provider"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('transcribe'))"
            :invalid="Boolean(fieldError('transcribe'))"
          />
          <p
            v-if="options.capabilities.voicemail_transcription.runtime_available === null"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] leading-4 text-amber-800"
          >
            The schema accepts transcription, but runtime ASR availability is not exposed by this
            session. Saving does not guarantee that an ASR provider is configured.
          </p>
          <ToggleSwitch
            v-model="form.require_pin"
            label="Require PIN"
            description="Prompt when checking from owner devices"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('require_pin'))"
            :invalid="Boolean(fieldError('require_pin'))"
          />
          <ToggleSwitch
            v-model="configuration.check_if_owner"
            label="Recognize owner devices"
            description="Prompt the owner to sign in when calling this mailbox"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('check_if_owner'))"
            :invalid="Boolean(fieldError('check_if_owner'))"
          />
          <ToggleSwitch
            v-model="configuration.not_configurable"
            label="Lock mailbox configuration"
            description="Prevent changes from the phone"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError('not_configurable'))"
            :invalid="Boolean(fieldError('not_configurable'))"
          />
        </div>
      </article>

      <DisclosureCard title="Playback behavior">
        <div class="grid gap-3">
          <ToggleSwitch
            v-for="control in [
              ['oldest_message_first', 'Play oldest messages first'],
              ['skip_envelope', 'Skip message envelope'],
              ['skip_greeting', 'Skip unavailable greeting'],
              ['skip_instructions', 'Skip recording instructions'],
              ['is_voicemail_ff_rw_enabled', 'Enable fast-forward and rewind'],
            ] as const"
            :key="control[0]"
            v-model="configuration[control[0]]"
            :label="control[1]"
            class="rounded-md border border-slate-200 p-3"
            :class="validationControlClass(fieldError(control[0]))"
            :invalid="Boolean(fieldError(control[0]))"
          />
          <FormInput
            v-if="configuration.is_voicemail_ff_rw_enabled"
            v-model.number="configuration.seek_duration_ms"
            label="Seek duration"
            type="number"
            min="0"
            max="300000"
            step="1000"
            :error="fieldError('seek_duration_ms')"
          />
        </div>
      </DisclosureCard>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <KeyIcon class="size-5 text-amber-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Mailbox PIN</h2>
            <p class="text-[10px] text-slate-500">Optional and write-only</p>
          </div>
        </header>
        <div class="p-5">
          <FormInput
            v-model="form.pin"
            :label="editing ? 'New PIN' : 'PIN'"
            :required="form.require_pin && !pinConfigured"
            type="password"
            inputmode="numeric"
            pattern="[0-9]{4,6}"
            minlength="4"
            maxlength="6"
            autocomplete="new-password"
            input-class="font-mono"
            :description="`${
              editing && pinConfigured
                ? 'Leave blank to keep the existing PIN.'
                : form.require_pin
                  ? 'Required when PIN protection is enabled. Use 4–6 digits.'
                  : 'Optional. Use 4–6 digits.'
            } GridPBX never returns or stores it unredacted.`"
            :error="fieldError('pin')"
          />
        </div>
      </article>
    </div>
  </div>
</template>
