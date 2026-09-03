<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  MicrophoneIcon,
  PencilSquareIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import VoicemailDraftForm from '@/domains/voicemail/components/VoicemailDraftForm.vue'
import type { VoicemailBox, VoicemailFormOptions } from '@/domains/voicemail/types/voicemail'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import BasicAdvancedTabSelector from '@/shared/components/BasicAdvancedTabSelector.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  hydrateExtensionAdvancedCalling,
  hydrateExtensionCredentialsInput,
  hydrateExtensionHotdeskInput,
  hydrateExtensionUserConfiguration,
} from '../extensionForm'
import {
  extensionAdvancedSectionForField,
  type ExtensionAdvancedSection,
} from '../extensionAdvancedSections'
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
import ExtensionAdvancedTabSelector from './ExtensionAdvancedTabSelector.vue'
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
  voicemailBox: VoicemailBox | null
  voicemailOptions: VoicemailFormOptions
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
const selectedFormSection = ref(0)
const selectedAdvancedSection = ref<ExtensionAdvancedSection>('options')
const panelView = ref<'extension' | 'voicemail'>('extension')
const voicemailDraft = ref<InstanceType<typeof VoicemailDraftForm> | null>(null)
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
const managedMailboxName = computed(() => {
  const displayName = `${form.firstName} ${form.lastName}`.trim() || 'Managed voicemail'

  return form.extension.trim() ? `(${form.extension.trim()}) ${displayName}` : displayName
})
const panelTitle = computed(() =>
  panelView.value === 'voicemail' ? 'Configure voicemail' : 'Edit extension',
)
const panelEyebrow = computed(() =>
  panelView.value === 'voicemail'
    ? 'GridPBX / People & Extensions / Edit / Voicemail'
    : 'GridPBX / People & Extensions / Edit',
)
const panelDescription = computed(() =>
  panelView.value === 'voicemail'
    ? 'Configure the managed mailbox without leaving the Extension workflow.'
    : 'Update the managed Switch user, mailbox, and extension routing as one workflow.',
)
const voicemailFieldErrors = computed<FormErrors>(() =>
  Object.fromEntries(
    Object.entries(displayErrors.value)
      .filter(([field]) => field.startsWith('voicemail.input.'))
      .map(([field, messages]) => [field.slice('voicemail.input.'.length), messages]),
  ),
)

watch(
  () => form.voicemailEnabled,
  (enabled, previousEnabled) => {
    if (enabled && previousEnabled === false) panelView.value = 'voicemail'
  },
)

watch(voicemailFieldErrors, (errors) => {
  if (Object.keys(errors).length > 0) panelView.value = 'voicemail'
})

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

function updateAdvancedCalling(
  value: Pick<ExtensionUpdate, 'caller_id' | 'call_forward' | 'call_restriction'>,
): void {
  Object.assign(advancedCalling, value)
}

function updateExtendedAdvanced(value: Partial<ExtensionUpdate>): void {
  Object.assign(advancedCalling, value)
}

function updateCallRecording(value: ExtensionUpdate['call_recording']): void {
  advancedCalling.call_recording = value
}

function updateMetaflows(
  value: Pick<ExtensionMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>,
): void {
  Object.assign(metaflows, value)
}

function isAdvancedField(field: string): boolean {
  return extensionAdvancedSectionForField(field) !== null
}

function revealValidationSection(errors: FormErrors): void {
  const fields = Object.keys(errors)

  const advancedOnly = fields.length > 0 && fields.every(isAdvancedField)
  selectedFormSection.value = advancedOnly ? 1 : 0

  if (advancedOnly) {
    selectedAdvancedSection.value = extensionAdvancedSectionForField(fields[0] ?? '') ?? 'options'
  }
}

watch(
  () => props.fieldErrors,
  (errors) => {
    if (Object.keys(errors).length > 0) revealValidationSection(errors)
  },
  { deep: true, immediate: true },
)

function submit(): void {
  const currentVoicemail = form.voicemailEnabled
    ? (voicemailDraft.value?.currentInput() ?? null)
    : null
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
      input: currentVoicemail,
    },
  }
  const validation = validateForm(
    extensionUpdateSchemaFor(props.extension.username, props.voicemailBox?.pin_configured ?? false),
    input,
  )

  if (!validation.success) {
    clientErrors.value = validation.errors
    revealValidationSection(validation.errors)

    return
  }

  if (form.voicemailEnabled) {
    const validatedVoicemail = voicemailDraft.value?.validatedInput() ?? null
    if (validatedVoicemail === null) {
      panelView.value = 'voicemail'

      return
    }
    validation.data.voicemail.input = validatedVoicemail
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
    :title="panelTitle"
    :eyebrow="panelEyebrow"
    :description="panelDescription"
    width="extra-wide"
    :scroll-key="panelView"
    @close="emit('close')"
  >
    <form v-show="panelView === 'extension'" class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="displayErrors"
        title="Unable to save the extension"
      />

      <BasicAdvancedTabSelector
        v-model="selectedFormSection"
        aria-label="Extension form sections"
      />

      <article v-show="selectedFormSection === 0" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><UserIcon class="size-5"
          /></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Person and extension</h2>
            <p class="text-[10px] text-heading-description">The managed user is the aggregate root.</p>
          </div>
        </header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <FormInput
            v-model="form.firstName"
            label="First name"
            required
            maxlength="128"
            :error="fieldError('first_name')"
          />
          <FormInput
            v-model="form.lastName"
            label="Last name"
            required
            maxlength="128"
            :error="fieldError('last_name')"
          />
          <FormInput
            v-model="form.extension"
            label="Extension number"
            required
            inputmode="numeric"
            pattern="[0-9]{2,15}"
            input-class="font-mono"
            :error="fieldError('extension')"
          />
          <FormInput
            v-model="form.email"
            label="Email"
            type="email"
            maxlength="254"
            :error="fieldError('email')"
          />
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

      <div v-show="selectedFormSection === 0" class="contents">
        <ExtensionCredentialsProfile
          :model-value="credentials"
          :field-errors="displayErrors"
          :original-username="extension.username"
          :password-configured="extension.configuration.credentials.password_configured"
          section="login"
          editing
          @update:model-value="updateCredentials"
        />
      </div>

      <ExtensionAdvancedTabSelector
        v-model="selectedAdvancedSection"
        v-show="selectedFormSection === 1"
        data-testid="extension-advanced-section"
        extended
      >
        <div
          v-show="selectedAdvancedSection === 'caller-id'"
          data-testid="extension-advanced-caller-id"
          class="contents"
        >
          <ExtensionUserOptions
            :model-value="userConfiguration"
            :field-errors="displayErrors"
            :language-options="languageOptions"
            :presence-options="presenceOptions"
            section="presence-id"
            @update:model-value="updateUserConfiguration"
          />
          <ExtensionAdvancedCallingSettings
            :model-value="advancedCalling"
            :field-errors="displayErrors"
            :phone-numbers="options.caller_id_numbers"
            :restrictions="restrictionOptions"
            :unresolved-numbers="{
              external: extension.configuration.caller_id.external.number,
              emergency: extension.configuration.caller_id.emergency.number,
            }"
            section="caller-id"
            @update:model-value="updateAdvancedCalling"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'options'"
          data-testid="extension-advanced-options"
          class="contents"
        >
          <ExtensionUserOptions
            :model-value="userConfiguration"
            :field-errors="displayErrors"
            :language-options="languageOptions"
            :presence-options="presenceOptions"
            section="options"
            @update:model-value="updateUserConfiguration"
          />
          <ExtensionMediaSettings
            :model-value="advancedCalling"
            :field-errors="displayErrors"
            :media-options="options.media"
            section="music-on-hold"
            @update:model-value="updateExtendedAdvanced"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'call-forward'"
          data-testid="extension-advanced-call-forward"
          class="contents"
        >
          <ExtensionAdvancedCallingSettings
            :model-value="advancedCalling"
            :field-errors="displayErrors"
            :phone-numbers="options.caller_id_numbers"
            :restrictions="restrictionOptions"
            :unresolved-numbers="{
              external: extension.configuration.caller_id.external.number,
              emergency: extension.configuration.caller_id.emergency.number,
            }"
            section="call-forward"
            @update:model-value="updateAdvancedCalling"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'password'"
          data-testid="extension-advanced-password"
          class="contents"
        >
          <ExtensionCredentialsProfile
            :model-value="credentials"
            :field-errors="displayErrors"
            :original-username="extension.username"
            :password-configured="extension.configuration.credentials.password_configured"
            section="password"
            editing
            @update:model-value="updateCredentials"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'recording'"
          data-testid="extension-advanced-recording"
          class="contents"
        >
          <ExtensionCallRecordingSettings
            :model-value="advancedCalling.call_recording"
            :field-errors="displayErrors"
            @update:model-value="updateCallRecording"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'hot-desking'"
          data-testid="extension-advanced-hot-desking"
          class="contents"
        >
          <ExtensionHotdeskProfile
            :model-value="hotdesk"
            :field-errors="displayErrors"
            :pin-configured="extension.configuration.hotdesk.pin_configured"
            editing
            @update:model-value="updateHotdesk"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'restrictions'"
          data-testid="extension-advanced-restrictions"
          class="contents"
        >
          <ExtensionAdvancedCallingSettings
            :model-value="advancedCalling"
            :field-errors="displayErrors"
            :phone-numbers="options.caller_id_numbers"
            :restrictions="restrictionOptions"
            :unresolved-numbers="{
              external: extension.configuration.caller_id.external.number,
              emergency: extension.configuration.caller_id.emergency.number,
            }"
            section="restrictions"
            @update:model-value="updateAdvancedCalling"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'media'"
          data-testid="extension-advanced-media"
          class="contents"
        >
          <ExtensionMediaSettings
            :model-value="advancedCalling"
            :field-errors="displayErrors"
            :media-options="options.media"
            section="media"
            @update:model-value="updateExtendedAdvanced"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'routing-profile'"
          data-testid="extension-advanced-routing-profile"
          class="contents"
        >
          <ExtensionRoutingProfileSettings
            :model-value="advancedCalling"
            :field-errors="displayErrors"
            :media-options="options.media"
            :policy="extension.configuration.policy"
            @update:model-value="updateExtendedAdvanced"
          />
        </div>

        <div
          v-show="selectedAdvancedSection === 'metaflows'"
          data-testid="extension-advanced-metaflows"
          class="contents"
        >
          <ExtensionMetaflowSettings
            :model-value="metaflows"
            :current="extension.configuration.metaflows"
            :resources="options.metaflow_resources"
            :field-errors="displayErrors"
            @update:model-value="updateMetaflows"
          />
        </div>
      </ExtensionAdvancedTabSelector>

      <article v-show="selectedFormSection === 0" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-purple-50 text-purple-600"
            ><MicrophoneIcon class="size-5"
          /></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-sm font-semibold text-slate-700">Voicemail fallback</h2>
            <p class="text-[10px] text-heading-description">Managed mailbox and callflow fallback.</p>
          </div>
          <ToggleSwitch v-model="form.voicemailEnabled" label="Enabled" />
        </header>
        <div v-if="form.voicemailEnabled" class="p-5">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg border p-4 text-left transition"
            :class="
              fieldError('voicemail.input')
                ? 'border-red-400 bg-red-50/40 ring-2 ring-red-100'
                : 'border-slate-200 bg-slate-50 hover:border-brand-300 hover:bg-brand-50/40'
            "
            :aria-invalid="Boolean(fieldError('voicemail.input'))"
            @click="panelView = 'voicemail'"
          >
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-white text-purple-600 shadow-sm"
            >
              <MicrophoneIcon class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-xs font-semibold text-slate-700">
                {{ managedMailboxName }}
              </span>
              <span class="mt-1 block text-[11px] leading-4 text-slate-500">
                Basic and Advanced mailbox settings are available in the shared Voicemail editor.
              </span>
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600">
              <PencilSquareIcon class="size-4" />
              Configure
            </span>
          </button>
          <p v-if="fieldError('voicemail.input')" class="mt-2 text-[11px] text-danger">
            {{ fieldError('voicemail.input') }}
          </p>
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

      <div class="slide-over-actions flex justify-end gap-3 pt-5">
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

    <div v-show="panelView === 'voicemail'" data-testid="voicemail-subview">
      <VoicemailDraftForm
        ref="voicemailDraft"
        :options="voicemailOptions"
        :name="managedMailboxName"
        :mailbox="form.extension.trim()"
        :timezone="form.timezone"
        :initial="voicemailBox"
        :pin-configured="voicemailBox?.pin_configured ?? false"
        :external-field-errors="voicemailFieldErrors"
        editing
        @cancel="panelView = 'extension'"
        @configured="panelView = 'extension'"
      />
    </div>
  </CrudSlideOver>
</template>
