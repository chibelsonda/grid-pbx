<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  CheckCircleIcon,
  EnvelopeIcon,
  KeyIcon,
  LinkIcon,
  MicrophoneIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validateForm } from '@/shared/forms/zod'
import { voicemailBoxFormSchema } from '../schemas/voicemailBoxFormSchema'
import { useVoicemailStore } from '../stores/voicemailStore'
import type { VoicemailBoxInput } from '../types/voicemail'
import {
  defaultVoicemailBoxConfiguration,
  hydrateVoicemailBoxConfiguration,
} from '../voicemailForm'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const voicemail = useVoicemailStore()
const isEditing = computed(() => route.name === 'voicemail-edit')
const voicemailBoxId = computed(() =>
  isEditing.value ? String(route.params.voicemailBoxId) : null,
)
const title = computed(() => (isEditing.value ? 'Edit voicemail box' : 'Add voicemail box'))
const canManage = computed(() => accounts.selected?.permissions.can_manage_voicemail ?? false)
const form = reactive({
  name: '',
  mailbox: '',
  assigned_extension_id: '',
  timezone: '',
  notification_emails: '',
  transcribe: false,
  require_pin: false,
  pin: '',
})
const configuration = reactive(defaultVoicemailBoxConfiguration())

watch(
  [() => accounts.selectedId, voicemailBoxId],
  async ([accountId, selectedId]) => {
    voicemail.mutationError = null
    voicemail.fieldErrors = {}
    if (!accountId) return
    await voicemail.loadExtensionOptions(accountId)
    if (!selectedId) return
    await voicemail.loadDetail(accountId, selectedId)
    const record = voicemail.detail
    if (!record) return
    form.name = record.name ?? ''
    form.mailbox = record.mailbox ?? ''
    form.assigned_extension_id = record.assigned_extension?.id ?? ''
    form.timezone = record.timezone ?? ''
    form.notification_emails = record.notification_emails.join('\n')
    form.transcribe = record.transcribe
    form.require_pin = record.require_pin
    form.pin = ''
    Object.assign(configuration, hydrateVoicemailBoxConfiguration(record.configuration))
  },
  { immediate: true },
)

function nullable(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

function fieldError(field: string): string | null {
  return voicemail.fieldErrors[field]?.[0] ?? null
}

function emails(): string[] {
  return form.notification_emails
    .split(/[\n,]/)
    .map((email) => email.trim())
    .filter(Boolean)
}

function close(): void {
  void router.push(
    voicemailBoxId.value
      ? { name: 'voicemail-detail', params: { voicemailBoxId: voicemailBoxId.value } }
      : { name: 'voicemail' },
  )
}

async function save(): Promise<void> {
  if (!accounts.selectedId) return
  const input: VoicemailBoxInput = {
    name: form.name.trim(),
    mailbox: form.mailbox.trim(),
    assigned_extension_id: nullable(form.assigned_extension_id),
    timezone: nullable(form.timezone),
    notification_emails: emails(),
    transcribe: form.transcribe,
    require_pin: form.require_pin,
    pin: nullable(form.pin),
    ...configuration,
  }
  const validation = validateForm(voicemailBoxFormSchema, input)

  if (!validation.success) {
    voicemail.fieldErrors = validation.errors
    voicemail.mutationError = 'Check the highlighted fields and try again.'

    return
  }

  const record = voicemailBoxId.value
    ? await voicemail.update(accounts.selectedId, voicemailBoxId.value, validation.data)
    : await voicemail.create(accounts.selectedId, validation.data)
  if (record) await router.push({ name: 'voicemail-detail', params: { voicemailBoxId: record.id } })
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    :eyebrow="`GridPBX / Voicemail / ${title}`"
    description="Changes are written to Switch first and then projected into MySQL."
    @close="close"
  >
    <div
      v-if="accounts.selected && !canManage"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <KeyIcon class="mx-auto size-10 text-slate-300" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Read-only account access</h2>
        <p class="mt-2 text-xs text-slate-500">
          Your organization role can view voicemail boxes but cannot change Switch configuration.
        </p>
        <button
          type="button"
          class="mt-5 h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="close"
        >
          Return to voicemail
        </button>
      </div>
    </div>
    <div
      v-else-if="isEditing && voicemail.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading voicemail configuration…
    </div>
    <div
      v-else-if="isEditing && voicemail.detailError"
      class="card-surface p-8 text-center text-xs text-danger"
    >
      {{ voicemail.detailError }}
    </div>
    <form
      v-else
      class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"
      novalidate
      @submit.prevent="save"
    >
      <div class="grid content-start gap-5">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><MicrophoneIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Mailbox identity</h2>
              <p class="text-[10px] text-slate-400">
                Name and number callers use to reach voicemail
              </p>
            </div>
          </header>
          <div class="grid gap-5 p-5 sm:grid-cols-2">
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Mailbox name</span
              ><input
                v-model="form.name"
                required
                maxlength="128"
                placeholder="Reception voicemail"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              /><span v-if="fieldError('name')" class="text-[11px] text-danger">{{
                fieldError('name')
              }}</span></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Mailbox number</span
              ><input
                v-model="form.mailbox"
                required
                maxlength="30"
                inputmode="numeric"
                pattern="[0-9]+"
                placeholder="1001"
                class="h-10 rounded-md border border-slate-200 px-3 font-mono text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              /><span v-if="fieldError('mailbox')" class="text-[11px] text-danger">{{
                fieldError('mailbox')
              }}</span></label
            >
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Timezone</span
              ><input
                v-model="form.timezone"
                maxlength="64"
                list="common-timezones"
                placeholder="Account default"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              /><datalist id="common-timezones">
                <option value="Asia/Tokyo" />
                <option value="Asia/Manila" />
                <option value="America/New_York" />
                <option value="America/Chicago" />
                <option value="America/Denver" />
                <option value="America/Los_Angeles" />
                <option value="Europe/London" /></datalist
              ><span v-if="fieldError('timezone')" class="text-[11px] text-danger">{{
                fieldError('timezone')
              }}</span></label
            >
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <EnvelopeIcon class="size-5 text-emerald-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Notifications</h2>
              <p class="text-[10px] text-slate-400">One email address per line, up to ten</p>
            </div>
          </header>
          <div class="p-5">
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Notification email addresses</span
              ><textarea
                v-model="form.notification_emails"
                rows="6"
                placeholder="support@example.com&#10;manager@example.com"
                class="rounded-md border border-slate-200 p-3 text-xs leading-5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              /><span
                v-if="fieldError('notification_emails') || fieldError('notification_emails.0')"
                class="text-[11px] text-danger"
                >{{
                  fieldError('notification_emails') ?? fieldError('notification_emails.0')
                }}</span
              ></label
            >
          </div>
        </article>

        <DisclosureCard title="Advanced notification delivery">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Voicemail audio format</span>
              <FormListbox
                v-model="configuration.media_extension"
                :options="[
                  { value: 'mp3', label: 'MP3' },
                  { value: 'mp4', label: 'MP4' },
                  { value: 'wav', label: 'WAV' },
                ]"
              />
              <span v-if="fieldError('media_extension')" class="text-[11px] text-danger">{{
                fieldError('media_extension')
              }}</span>
            </label>
            <ToggleSwitch
              v-model="configuration.include_message_on_notify"
              label="Attach voicemail audio"
              description="Include the recording with email notifications"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.include_transcription_on_notify"
              label="Include transcription"
              description="Add ASR text to notification emails"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.save_after_notify"
              label="Save after notification"
              description="Move the message to Saved after notification"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.delete_after_notify"
              label="Delete after notification"
              description="Move the message to Deleted unless Save is enabled"
              class="rounded-md border border-slate-200 p-3"
            />
          </div>
        </DisclosureCard>
      </div>

      <div class="grid content-start gap-5">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <LinkIcon class="size-5 text-blue-500" />
            <h2 class="text-sm font-semibold text-slate-700">Assignment</h2>
          </header>
          <div class="p-5">
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Extension</span
              ><FormSelect
                v-model="form.assigned_extension_id"
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500"
              >
                <option value="">Unassigned</option>
                <option
                  v-for="extension in voicemail.extensionOptions"
                  :key="extension.id"
                  :value="extension.id"
                >
                  {{ extension.display_name
                  }}{{ extension.extension ? ` · ${extension.extension}` : '' }}
                </option></FormSelect
              ><span v-if="fieldError('assigned_extension_id')" class="text-[11px] text-danger">{{
                fieldError('assigned_extension_id')
              }}</span></label
            >
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
            />
            <ToggleSwitch
              v-model="form.require_pin"
              label="Require PIN"
              description="Prompt when checking from owner devices"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.check_if_owner"
              label="Recognize owner devices"
              description="Prompt the owner to sign in when calling this mailbox"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.not_configurable"
              label="Lock mailbox configuration"
              description="Prevent the mailbox owner from changing settings by phone"
              class="rounded-md border border-slate-200 p-3"
            />
          </div>
        </article>

        <DisclosureCard title="Playback behavior">
          <div class="grid gap-3">
            <ToggleSwitch
              v-model="configuration.oldest_message_first"
              label="Play oldest messages first"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.skip_envelope"
              label="Skip message envelope"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.skip_greeting"
              label="Skip unavailable greeting"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.skip_instructions"
              label="Skip recording instructions"
              class="rounded-md border border-slate-200 p-3"
            />
            <ToggleSwitch
              v-model="configuration.is_voicemail_ff_rw_enabled"
              label="Enable fast-forward and rewind"
              class="rounded-md border border-slate-200 p-3"
            />
            <label v-if="configuration.is_voicemail_ff_rw_enabled" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Seek duration</span>
              <div class="relative">
                <input
                  v-model.number="configuration.seek_duration_ms"
                  type="number"
                  min="0"
                  max="300000"
                  step="1000"
                  class="field-control pr-12"
                />
                <span class="absolute top-1/2 right-3 -translate-y-1/2 text-[10px] text-slate-400"
                  >ms</span
                >
              </div>
              <span v-if="fieldError('seek_duration_ms')" class="text-[11px] text-danger">{{
                fieldError('seek_duration_ms')
              }}</span>
            </label>
          </div>
        </DisclosureCard>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <KeyIcon class="size-5 text-amber-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Mailbox PIN</h2>
              <p class="text-[10px] text-slate-400">Optional and write-only</p>
            </div>
          </header>
          <div class="p-5">
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">{{
                isEditing ? 'New PIN' : 'PIN'
              }}</span
              ><input
                v-model="form.pin"
                type="password"
                inputmode="numeric"
                pattern="[0-9]{4,6}"
                minlength="4"
                maxlength="6"
                autocomplete="new-password"
                class="h-10 rounded-md border border-slate-200 px-3 font-mono text-xs outline-none focus:border-brand-500"
              /><span class="text-[10px] leading-4 text-slate-400"
                >{{
                  isEditing ? 'Leave blank to keep the existing PIN.' : 'Use 4–6 digits.'
                }}
                GridPBX never returns or stores it unredacted.</span
              ><span v-if="fieldError('pin')" class="text-[11px] text-danger">{{
                fieldError('pin')
              }}</span></label
            >
          </div>
        </article>

        <div
          v-if="voicemail.mutationError"
          class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
        >
          {{ voicemail.mutationError }}
        </div>
        <button
          type="submit"
          :disabled="voicemail.mutationLoading || !accounts.selectedId"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          <CheckCircleIcon class="size-4" />{{
            voicemail.mutationLoading ? 'Saving…' : isEditing ? 'Save changes' : 'Create mailbox'
          }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
