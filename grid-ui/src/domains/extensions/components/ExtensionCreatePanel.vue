<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  DevicePhoneMobileIcon,
  PencilSquareIcon,
  MicrophoneIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import DeviceDraftForm from '@/domains/devices/components/DeviceDraftForm.vue'
import { defaultDeviceOptions, deviceTypes } from '@/domains/devices/deviceForm'
import type { DeviceInput, DeviceOptions } from '@/domains/devices/types/device'
import VoicemailDraftForm from '@/domains/voicemail/components/VoicemailDraftForm.vue'
import type { VoicemailBoxInput, VoicemailFormOptions } from '@/domains/voicemail/types/voicemail'
import { defaultVoicemailFormOptions } from '@/domains/voicemail/voicemailForm'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import BasicAdvancedTabSelector from '@/shared/components/BasicAdvancedTabSelector.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  defaultExtensionCredentialsInput,
  defaultExtensionAdvancedCallingConfiguration,
  defaultExtensionHotdeskInput,
  defaultExtensionUserConfiguration,
  hydrateExtensionAdvancedCalling,
} from '../extensionForm'
import {
  extensionAdvancedSectionForField,
  type ExtensionAdvancedSection,
} from '../extensionAdvancedSections'
import { useExtensionFormOptions } from '../composables/useExtensionFormOptions'
import { extensionCreateSchema } from '../schemas/extensionFormSchema'
import type {
  ExtensionCreate,
  ExtensionCredentialsInput,
  ExtensionMetaflows,
  ExtensionHotdeskInput,
  ExtensionFormOptions,
  ExtensionUserConfiguration,
} from '../types/extension'
import ExtensionCredentialsProfile from './ExtensionCredentialsProfile.vue'
import ExtensionAdvancedCallingSettings from './ExtensionAdvancedCallingSettings.vue'
import ExtensionAdvancedTabSelector from './ExtensionAdvancedTabSelector.vue'
import ExtensionCallRecordingSettings from './ExtensionCallRecordingSettings.vue'
import ExtensionHotdeskProfile from './ExtensionHotdeskProfile.vue'
import ExtensionMediaSettings from './ExtensionMediaSettings.vue'
import ExtensionMetaflowSettings from './ExtensionMetaflowSettings.vue'
import ExtensionRoutingProfileSettings from './ExtensionRoutingProfileSettings.vue'
import ExtensionUserOptions from './ExtensionUserOptions.vue'

const props = withDefaults(
  defineProps<{
    saving: boolean
    error: string | null
    fieldErrors: Record<string, string[]>
    options: ExtensionFormOptions
    deviceOptions?: DeviceOptions
    voicemailOptions?: VoicemailFormOptions
  }>(),
  { deviceOptions: defaultDeviceOptions, voicemailOptions: defaultVoicemailFormOptions },
)
const emit = defineEmits<{ close: []; save: [input: ExtensionCreate] }>()
const userConfiguration = reactive(defaultExtensionUserConfiguration())
const credentials = reactive(defaultExtensionCredentialsInput())
const hotdesk = reactive(defaultExtensionHotdeskInput())
const advancedCalling = reactive(
  hydrateExtensionAdvancedCalling(
    defaultExtensionAdvancedCallingConfiguration(),
    props.options.restrictions.map(({ key }) => key),
  ),
)
const selectedFormSection = ref(0)
const selectedAdvancedSection = ref<ExtensionAdvancedSection>('options')
const metaflows = reactive<
  Pick<ExtensionMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>
>({
  binding_digit: null,
  digit_timeout: null,
  listen_on: null,
  actions: [],
})
const emptyMetaflowConfiguration: ExtensionMetaflows = {
  ...metaflows,
  number_flow_count: 0,
  pattern_flow_count: 0,
  locked_action_count: 0,
}
const panelView = ref<'extension' | 'device' | 'voicemail'>('extension')
const configuredDevice = ref<DeviceInput | null>(null)
const configuredVoicemail = ref<VoicemailBoxInput | null>(null)
const voicemailDraft = ref<InstanceType<typeof VoicemailDraftForm> | null>(null)
const clientErrors = ref<FormErrors>({})
const displayErrors = computed(() => ({ ...props.fieldErrors, ...clientErrors.value }))
const form = reactive({
  firstName: '',
  lastName: '',
  extension: '',
  email: '',
  timezone: null as string | null,
  isEnabled: true,
  voicemailEnabled: true,
  deviceEnabled: false,
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
const configuredDeviceType = computed(() =>
  deviceTypes.find((type) => type.value === configuredDevice.value?.device_type),
)
const managedMailboxName = computed(() => {
  const displayName = `${form.firstName} ${form.lastName}`.trim() || 'Managed voicemail'

  return form.extension.trim() ? `(${form.extension.trim()}) ${displayName}` : displayName
})
const panelTitle = computed(() => {
  if (panelView.value === 'device') return 'Configure device'
  if (panelView.value === 'voicemail') return 'Configure voicemail'

  return 'Create extension'
})
const panelEyebrow = computed(() =>
  panelView.value === 'extension'
    ? 'GridPBX / People & Extensions / Create'
    : `GridPBX / People & Extensions / Create / ${panelView.value === 'device' ? 'Initial device' : 'Voicemail'}`,
)
const panelDescription = computed(() => {
  if (panelView.value === 'device') {
    return 'Configure the optional endpoint without leaving the Extension workflow.'
  }
  if (panelView.value === 'voicemail') {
    return 'Configure the managed mailbox without leaving the Extension workflow.'
  }

  return 'Provision a managed Switch user, optional mailbox and device, and a safe extension callflow.'
})
const deviceFieldErrors = computed<FormErrors>(() =>
  Object.fromEntries(
    Object.entries(displayErrors.value)
      .filter(([field]) => field.startsWith('device.input.'))
      .map(([field, messages]) => [field.slice('device.input.'.length), messages]),
  ),
)
const voicemailFieldErrors = computed<FormErrors>(() =>
  Object.fromEntries(
    Object.entries(displayErrors.value)
      .filter(([field]) => field.startsWith('voicemail.input.'))
      .map(([field, messages]) => [field.slice('voicemail.input.'.length), messages]),
  ),
)

watch(
  () => form.deviceEnabled,
  (enabled) => {
    if (enabled && configuredDevice.value === null) panelView.value = 'device'
    if (!enabled) configuredDevice.value = null
  },
)

watch(
  () => form.voicemailEnabled,
  (enabled, previousEnabled) => {
    if (enabled && previousEnabled === false) panelView.value = 'voicemail'
    if (!enabled) configuredVoicemail.value = null
  },
)

watch(
  () => props.options.restrictions,
  (restrictions) => {
    for (const { key } of restrictions) {
      advancedCalling.call_restriction[key] ??= { action: 'inherit' }
    }
  },
  { deep: true },
)

function nullable(value: string): string | null {
  const trimmed = value.trim()
  return trimmed ? trimmed : null
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
  value: Pick<ExtensionCreate, 'caller_id' | 'call_forward' | 'call_restriction'>,
): void {
  Object.assign(advancedCalling, value)
}

function updateExtendedAdvanced(value: Partial<ExtensionCreate>): void {
  Object.assign(advancedCalling, value)
}

function updateCallRecording(value: ExtensionCreate['call_recording']): void {
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

function configureDevice(input: DeviceInput): void {
  configuredDevice.value = input
  panelView.value = 'extension'
}

function configureVoicemail(input: VoicemailBoxInput): void {
  configuredVoicemail.value = input
  panelView.value = 'extension'
}

function submit(): void {
  const currentVoicemail = form.voicemailEnabled
    ? (voicemailDraft.value?.currentInput() ?? null)
    : null
  const input: ExtensionCreate = {
    first_name: form.firstName.trim(),
    last_name: form.lastName.trim(),
    extension: form.extension.trim(),
    username: nullable(credentials.username ?? ''),
    password: credentials.password || null,
    password_confirmation: credentials.password_confirmation || null,
    require_password_update: credentials.require_password_update,
    clear_credentials: false,
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
      clear_pin: false,
    },
    voicemail: {
      enabled: form.voicemailEnabled,
      input: currentVoicemail,
    },
    device: {
      enabled: form.deviceEnabled,
      input: form.deviceEnabled ? configuredDevice.value : null,
    },
  }
  const validation = validateForm(extensionCreateSchema, input)

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
  emit('save', validation.data)
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
      <div
        v-if="error"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
        role="alert"
      >
        {{ error }}
      </div>

      <BasicAdvancedTabSelector
        v-model="selectedFormSection"
        aria-label="Extension form sections"
      />

      <article v-show="selectedFormSection === 0" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
            <UserIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Person and extension</h2>
            <p class="text-[10px] text-slate-400">The Switch user is the aggregate root.</p>
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
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Timezone</span>
            <FormListbox
              v-model="form.timezone"
              :options="timezoneOptions"
              :invalid="Boolean(fieldError('timezone'))"
              aria-label="Timezone"
            />
            <span v-if="displayErrors.timezone" class="text-[10px] text-danger">{{
              displayErrors.timezone[0]
            }}</span>
          </label>
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
          section="login"
          @update:model-value="updateCredentials"
        />
      </div>

      <div
        v-show="selectedFormSection === 1"
        data-testid="extension-advanced-section"
        class="contents"
      >
        <ExtensionAdvancedTabSelector v-model="selectedAdvancedSection" extended />

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
            :restrictions="options.restrictions"
            :unresolved-numbers="{ external: null, emergency: null }"
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
            :restrictions="options.restrictions"
            :unresolved-numbers="{ external: null, emergency: null }"
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
            section="password"
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
            :restrictions="options.restrictions"
            :unresolved-numbers="{ external: null, emergency: null }"
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
            :policy="{
              verified: false,
              privilege: null,
              feature_level: null,
              external_flag_count: 0,
            }"
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
            :current="emptyMetaflowConfiguration"
            :resources="options.metaflow_resources"
            :field-errors="displayErrors"
            @update:model-value="updateMetaflows"
          />
        </div>
      </div>

      <article v-show="selectedFormSection === 0" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-purple-50 text-purple-600"
            ><MicrophoneIcon class="size-5"
          /></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-sm font-semibold text-slate-700">Voicemail fallback</h2>
            <p class="text-[10px] text-slate-400">
              Creates an owned mailbox and attaches it to the managed callflow.
            </p>
          </div>
          <ToggleSwitch v-model="form.voicemailEnabled" label="Create" />
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
                {{
                  configuredVoicemail
                    ? 'Mailbox settings configured'
                    : 'Uses account defaults until you customize its Basic and Advanced settings.'
                }}
              </span>
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600">
              <PencilSquareIcon class="size-4" />
              {{ configuredVoicemail ? 'Edit' : 'Configure' }}
            </span>
          </button>
          <p v-if="fieldError('voicemail.input')" class="mt-2 text-[11px] text-danger">
            {{ fieldError('voicemail.input') }}
          </p>
        </div>
      </article>

      <article v-show="selectedFormSection === 0" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-blue-50 text-info"
            ><DevicePhoneMobileIcon class="size-5"
          /></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-sm font-semibold text-slate-700">Initial device</h2>
            <p class="text-[10px] text-slate-400">
              Optional endpoint owned by the new Switch user.
            </p>
          </div>
          <ToggleSwitch v-model="form.deviceEnabled" label="Create" />
        </header>
        <div v-if="form.deviceEnabled" class="p-5">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg border p-4 text-left transition"
            :class="
              fieldError('device.input')
                ? 'border-red-400 bg-red-50/40 ring-2 ring-red-100'
                : 'border-slate-200 bg-slate-50 hover:border-brand-300 hover:bg-brand-50/40'
            "
            :aria-invalid="Boolean(fieldError('device.input'))"
            @click="panelView = 'device'"
          >
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-white text-brand-600 shadow-sm"
            >
              <component :is="configuredDeviceType?.icon ?? DevicePhoneMobileIcon" class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-xs font-semibold text-slate-700">
                {{ configuredDevice?.name ?? 'Configure the initial device' }}
              </span>
              <span class="mt-1 block text-[11px] leading-4 text-slate-500">
                {{
                  configuredDeviceType
                    ? `${configuredDeviceType.label} · Basic and Advanced settings configured`
                    : 'Open the shared Device editor to choose a type and configure its fields.'
                }}
              </span>
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600">
              <PencilSquareIcon class="size-4" />
              {{ configuredDevice ? 'Edit' : 'Configure' }}
            </span>
          </button>
          <p v-if="fieldError('device.input')" class="mt-2 text-[11px] text-danger">
            {{ fieldError('device.input') }}
          </p>
        </div>
      </article>

      <aside
        class="flex items-start gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <ArrowPathRoundedSquareIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          GridPBX provisions each selected Switch resource in dependency order. If a later step
          fails, it removes newly created resources in reverse order and reports when manual repair
          is required.
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
          {{ saving ? 'Provisioning extension…' : 'Create extension' }}
        </button>
      </div>
    </form>

    <div v-show="panelView === 'device'" data-testid="device-subview">
      <DeviceDraftForm
        :options="deviceOptions"
        :external-field-errors="deviceFieldErrors"
        @cancel="panelView = 'extension'"
        @configured="configureDevice"
      />
    </div>

    <div v-show="panelView === 'voicemail'" data-testid="voicemail-subview">
      <VoicemailDraftForm
        ref="voicemailDraft"
        :options="voicemailOptions"
        :name="managedMailboxName"
        :mailbox="form.extension.trim()"
        :timezone="form.timezone"
        :external-field-errors="voicemailFieldErrors"
        @cancel="panelView = 'extension'"
        @configured="configureVoicemail"
      />
    </div>
  </CrudSlideOver>
</template>
