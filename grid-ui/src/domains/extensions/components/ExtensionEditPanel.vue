<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ArrowPathRoundedSquareIcon, MicrophoneIcon, UserIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  hydrateExtensionAdvancedCalling,
  hydrateExtensionCredentialsInput,
  hydrateExtensionHotdeskInput,
  hydrateExtensionUserConfiguration,
} from '../extensionForm'
import { useExtensionFormOptions } from '../composables/useExtensionFormOptions'
import { extensionUpdateSchemaFor } from '../schemas/extensionFormSchema'
import type {
  ExtensionCredentialsInput,
  ExtensionDetail,
  ExtensionHotdeskInput,
  ExtensionFormOptions,
  ExtensionMetaflows,
  ExtensionUpdate,
  ExtensionUserConfiguration,
} from '../types/extension'
import type { MetaflowAction, MetaflowChild } from '@/shared/switch/metaflows/types'
import ExtensionCredentialsProfile from './ExtensionCredentialsProfile.vue'
import ExtensionAdvancedCallingSettings from './ExtensionAdvancedCallingSettings.vue'
import ExtensionCallRecordingSettings from './ExtensionCallRecordingSettings.vue'
import ExtensionHotdeskProfile from './ExtensionHotdeskProfile.vue'
import ExtensionMetaflowSettings from './ExtensionMetaflowSettings.vue'
import ExtensionMediaSettings from './ExtensionMediaSettings.vue'
import ExtensionRoutingProfileSettings from './ExtensionRoutingProfileSettings.vue'
import ExtensionUserOptions from './ExtensionUserOptions.vue'

const props = defineProps<{
  extension: ExtensionDetail
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  options: ExtensionFormOptions
}>()
const emit = defineEmits<{ close: []; save: [input: ExtensionUpdate] }>()
const voicemail = props.extension.voicemail_boxes.find((box) => box.is_managed)
const userConfiguration = reactive(hydrateExtensionUserConfiguration(props.extension.configuration))
const advancedCalling = reactive(
  hydrateExtensionAdvancedCalling(
    props.extension.configuration,
    props.options.restrictions.map(({ key }) => key),
  ),
)
const credentials = reactive(
  hydrateExtensionCredentialsInput(
    props.extension.username,
    props.extension.configuration.credentials,
  ),
)
const hotdesk = reactive(hydrateExtensionHotdeskInput(props.extension.configuration.hotdesk))
const metaflows = reactive<
  Pick<ExtensionMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>
>({
  binding_digit: props.extension.configuration.metaflows.binding_digit,
  digit_timeout: props.extension.configuration.metaflows.digit_timeout,
  listen_on: props.extension.configuration.metaflows.listen_on,
  actions: cloneMetaflowActions(props.extension.configuration.metaflows.actions),
})
const clientErrors = ref<FormErrors>({})
const displayErrors = computed(() => ({ ...props.fieldErrors, ...clientErrors.value }))
const restrictionOptions = computed(() => {
  const known = new Set(props.options.restrictions.map(({ key }) => key))

  return [
    ...props.options.restrictions,
    ...Object.keys(props.extension.configuration.call_restriction)
      .filter((key) => !known.has(key))
      .map((key) => ({ key, label: key, emergency: false })),
  ]
})
const form = reactive({
  firstName: props.extension.first_name ?? '',
  lastName: props.extension.last_name ?? '',
  extension: props.extension.extension ?? '',
  email: props.extension.email ?? '',
  timezone: props.extension.timezone,
  isEnabled: props.extension.is_enabled,
  voicemailEnabled: Boolean(voicemail),
  notificationEmails: voicemail?.notification_emails.join(', ') ?? '',
  transcribe: voicemail?.transcribe ?? false,
  requirePin: voicemail?.require_pin ?? false,
  pin: '',
})
const { timezoneOptions, languageOptions, presenceOptions } = useExtensionFormOptions(
  () => props.options,
  () => ({
    timezone: form.timezone,
    language: userConfiguration.language,
    presenceId: userConfiguration.presence_id,
  }),
  () => form.extension,
)

function nullable(value: string): string | null {
  return value.trim() || null
}

function cloneMetaflowActions(actions: MetaflowAction[]): MetaflowAction[] {
  return actions.map((action) => ({
    ...action,
    data: { ...action.data },
    children: cloneMetaflowChildren(action.children),
  }))
}

function cloneMetaflowChildren(children: MetaflowChild[]): MetaflowChild[] {
  return children.map((child) => ({
    ...child,
    data: { ...child.data },
    children: cloneMetaflowChildren(child.children),
  }))
}

function fieldError(field: string): string | null {
  const direct = displayErrors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(displayErrors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

function updateCredentials(value: ExtensionCredentialsInput): void {
  Object.assign(credentials, value)
}

function updateUserConfiguration(value: ExtensionUserConfiguration): void {
  Object.assign(userConfiguration, value)
}

function updateHotdesk(value: ExtensionHotdeskInput): void {
  Object.assign(hotdesk, value)
}

function submit(): void {
  const input: ExtensionUpdate = {
    first_name: form.firstName.trim(),
    last_name: form.lastName.trim(),
    extension: form.extension.trim(),
    username: nullable(credentials.username ?? ''),
    password: credentials.password || null,
    password_confirmation: credentials.password_confirmation || null,
    require_password_update: credentials.require_password_update,
    clear_credentials: credentials.clear_credentials,
    email: nullable(form.email),
    timezone: form.timezone,
    is_enabled: form.isEnabled,
    ...userConfiguration,
    caller_id: advancedCalling.caller_id,
    call_forward: {
      ...advancedCalling.call_forward,
      number: advancedCalling.call_forward.number?.trim() || null,
    },
    call_restriction: advancedCalling.call_restriction,
    call_recording: advancedCalling.call_recording,
    media: advancedCalling.media,
    music_on_hold: advancedCalling.music_on_hold,
    ringtones: {
      internal: nullable(advancedCalling.ringtones.internal ?? ''),
      external: nullable(advancedCalling.ringtones.external ?? ''),
    },
    dial_plan: {
      system: [...advancedCalling.dial_plan.system],
      rules: advancedCalling.dial_plan.rules.map((rule) => ({
        pattern: rule.pattern.trim(),
        description: nullable(rule.description ?? ''),
        prefix: nullable(rule.prefix ?? ''),
        suffix: nullable(rule.suffix ?? ''),
      })),
    },
    formatters: advancedCalling.formatters.map((formatter) => ({
      ...formatter,
      field: formatter.field.trim(),
      prefix: nullable(formatter.prefix ?? ''),
      regex: nullable(formatter.regex ?? ''),
      suffix: nullable(formatter.suffix ?? ''),
      value: nullable(formatter.value ?? ''),
    })),
    profile: {
      ...advancedCalling.profile,
      addresses: advancedCalling.profile.addresses.map((address) => ({
        address: address.address.trim(),
        types: [...address.types],
      })),
      assistant: nullable(advancedCalling.profile.assistant ?? ''),
      birthday: nullable(advancedCalling.profile.birthday ?? ''),
      nicknames: advancedCalling.profile.nicknames.map((nickname) => nickname.trim()),
      note: nullable(advancedCalling.profile.note ?? ''),
      role: nullable(advancedCalling.profile.role ?? ''),
      sort_string: nullable(advancedCalling.profile.sort_string ?? ''),
      title: nullable(advancedCalling.profile.title ?? ''),
    },
    pronounced_name: advancedCalling.pronounced_name,
    metaflows,
    hotdesk: {
      ...hotdesk,
      id: hotdesk.id ? hotdesk.id.trim() : null,
      pin: hotdesk.require_pin && hotdesk.pin ? hotdesk.pin.trim() : null,
      clear_pin: hotdesk.clear_pin,
    },
    voicemail: {
      enabled: form.voicemailEnabled,
      notification_emails: form.notificationEmails
        .split(',')
        .map((email) => email.trim())
        .filter(Boolean),
      transcribe: form.transcribe,
      require_pin: form.requirePin,
      pin: form.requirePin ? nullable(form.pin) : null,
    },
  }
  const validation = validateForm(extensionUpdateSchemaFor(props.extension.username), input)

  if (!validation.success) {
    clientErrors.value = validation.errors

    return
  }

  clientErrors.value = {}
  emit('save', {
    ...validation.data,
    metaflows: validation.data.metaflows ?? input.metaflows,
  })
}
</script>

<template>
  <CrudSlideOver
    title="Edit extension"
    eyebrow="GridPBX / People & Extensions / Edit"
    description="Update the managed Switch user, mailbox, and extension routing as one workflow."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="error"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ error }}
      </div>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><UserIcon class="size-5"
          /></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Person and extension</h2>
            <p class="text-[10px] text-slate-400">The managed user is the aggregate root.</p>
          </div>
        </header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">First name</span
            ><input
              v-model="form.firstName"
              required
              maxlength="128"
              class="field-control"
              :class="validationControlClass(fieldError('first_name'))"
              :aria-invalid="Boolean(fieldError('first_name'))"
            /><span v-if="displayErrors.first_name" class="text-[10px] text-danger">{{
              displayErrors.first_name[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Last name</span
            ><input
              v-model="form.lastName"
              required
              maxlength="128"
              class="field-control"
              :class="validationControlClass(fieldError('last_name'))"
              :aria-invalid="Boolean(fieldError('last_name'))"
            /><span v-if="displayErrors.last_name" class="text-[10px] text-danger">{{
              displayErrors.last_name[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Extension number</span
            ><input
              v-model="form.extension"
              required
              inputmode="numeric"
              pattern="[0-9]{2,15}"
              class="field-control font-mono"
              :class="validationControlClass(fieldError('extension'))"
              :aria-invalid="Boolean(fieldError('extension'))"
            /><span v-if="displayErrors.extension" class="text-[10px] text-danger">{{
              displayErrors.extension[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Email</span
            ><input
              v-model="form.email"
              type="email"
              maxlength="254"
              class="field-control"
              :class="validationControlClass(fieldError('email'))"
              :aria-invalid="Boolean(fieldError('email'))"
            /><span v-if="displayErrors.email" class="text-[10px] text-danger">{{
              displayErrors.email[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Timezone</span
            ><FormListbox
              v-model="form.timezone"
              :options="timezoneOptions"
              :invalid="Boolean(fieldError('timezone'))"
              aria-label="Timezone"
            /><span v-if="displayErrors.timezone" class="text-[10px] text-danger">{{
              displayErrors.timezone[0]
            }}</span></label
          >
          <ToggleSwitch
            v-model="form.isEnabled"
            label="Enable this Switch user"
            class="sm:col-span-2"
          />
        </div>
      </article>

      <ExtensionCredentialsProfile
        :model-value="credentials"
        :field-errors="displayErrors"
        :original-username="extension.username"
        :password-configured="extension.configuration.credentials.password_configured"
        editing
        @update:model-value="updateCredentials"
      />

      <ExtensionUserOptions
        :model-value="userConfiguration"
        :field-errors="displayErrors"
        :language-options="languageOptions"
        :presence-options="presenceOptions"
        @update:model-value="updateUserConfiguration"
      />

      <ExtensionAdvancedCallingSettings
        v-model="advancedCalling"
        :field-errors="displayErrors"
        :phone-numbers="options.caller_id_numbers"
        :restrictions="restrictionOptions"
        :unresolved-numbers="{
          external: extension.configuration.caller_id.external.number,
          emergency: extension.configuration.caller_id.emergency.number,
        }"
      />

      <ExtensionCallRecordingSettings
        v-model="advancedCalling.call_recording"
        :field-errors="displayErrors"
      />

      <ExtensionMediaSettings
        v-model="advancedCalling"
        :field-errors="displayErrors"
        :media-options="options.media"
      />

      <ExtensionRoutingProfileSettings
        v-model="advancedCalling"
        :field-errors="displayErrors"
        :media-options="options.media"
        :policy="extension.configuration.policy"
      />

      <ExtensionMetaflowSettings
        v-model="metaflows"
        :current="extension.configuration.metaflows"
        :resources="options.metaflow_resources"
        :field-errors="displayErrors"
      />

      <ExtensionHotdeskProfile
        :model-value="hotdesk"
        :field-errors="displayErrors"
        :pin-configured="extension.configuration.hotdesk.pin_configured"
        editing
        @update:model-value="updateHotdesk"
      />

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-purple-50 text-purple-600"
            ><MicrophoneIcon class="size-5"
          /></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-sm font-semibold text-slate-700">Voicemail fallback</h2>
            <p class="text-[10px] text-slate-400">Managed mailbox and callflow fallback.</p>
          </div>
          <ToggleSwitch v-model="form.voicemailEnabled" label="Enabled" />
        </header>
        <div v-if="form.voicemailEnabled" class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2"
            ><span class="text-xs font-semibold text-slate-600">Notification emails</span
            ><input
              v-model="form.notificationEmails"
              placeholder="alice@example.com, team@example.com"
              class="field-control"
              :class="validationControlClass(fieldError('voicemail.notification_emails'))"
              :aria-invalid="Boolean(fieldError('voicemail.notification_emails'))"
            /><span
              v-if="fieldError('voicemail.notification_emails')"
              class="text-[10px] text-danger"
              >{{ fieldError('voicemail.notification_emails') }}</span
            ></label
          >
          <ToggleSwitch v-model="form.transcribe" label="Enable transcription" />
          <ToggleSwitch v-model="form.requirePin" label="Require mailbox PIN" />
          <label v-if="form.requirePin" class="grid gap-2 sm:col-span-2"
            ><span class="text-xs font-semibold text-slate-600"
              >New mailbox PIN
              <span class="font-normal text-slate-400">(optional when unchanged)</span></span
            ><input
              v-model="form.pin"
              type="password"
              inputmode="numeric"
              pattern="[0-9]{4,6}"
              autocomplete="new-password"
              class="field-control"
              :class="validationControlClass(fieldError('voicemail.pin'))"
              :aria-invalid="Boolean(fieldError('voicemail.pin'))"
            /><span v-if="fieldError('voicemail.pin')" class="text-[10px] text-danger">{{
              fieldError('voicemail.pin')
            }}</span></label
          >
        </div>
      </article>

      <aside
        class="flex items-start gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <ArrowPathRoundedSquareIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          Devices are intentionally edited in the Devices area. This workflow updates only resources
          it owns and reports partial upstream completion when manual repair is needed.
        </p>
      </aside>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Updating extension…' : 'Save changes' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
