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
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  defaultExtensionCredentialsInput,
  defaultExtensionHotdeskInput,
  defaultExtensionUserConfiguration,
} from '../extensionForm'
import { useExtensionFormOptions } from '../composables/useExtensionFormOptions'
import { extensionCreateSchema } from '../schemas/extensionFormSchema'
import type {
  ExtensionCreate,
  ExtensionCredentialsInput,
  ExtensionHotdeskInput,
  ExtensionFormOptions,
  ExtensionUserConfiguration,
} from '../types/extension'
import ExtensionCredentialsProfile from './ExtensionCredentialsProfile.vue'
import ExtensionHotdeskProfile from './ExtensionHotdeskProfile.vue'
import ExtensionUserOptions from './ExtensionUserOptions.vue'

const props = withDefaults(
  defineProps<{
    saving: boolean
    error: string | null
    fieldErrors: Record<string, string[]>
    options: ExtensionFormOptions
    deviceOptions?: DeviceOptions
  }>(),
  { deviceOptions: defaultDeviceOptions },
)
const emit = defineEmits<{ close: []; save: [input: ExtensionCreate] }>()
const userConfiguration = reactive(defaultExtensionUserConfiguration())
const credentials = reactive(defaultExtensionCredentialsInput())
const hotdesk = reactive(defaultExtensionHotdeskInput())
const panelView = ref<'extension' | 'device'>('extension')
const configuredDevice = ref<DeviceInput | null>(null)
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
  notificationEmails: '',
  transcribe: false,
  requirePin: false,
  pin: '',
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
const panelTitle = computed(() =>
  panelView.value === 'device' ? 'Configure device' : 'Create extension',
)
const panelEyebrow = computed(() =>
  panelView.value === 'device'
    ? 'GridPBX / People & Extensions / Create / Initial device'
    : 'GridPBX / People & Extensions / Create',
)
const panelDescription = computed(() =>
  panelView.value === 'device'
    ? 'Configure the optional endpoint without leaving the Extension workflow.'
    : 'Provision a managed Switch user, optional mailbox and device, and a safe extension callflow.',
)
const deviceFieldErrors = computed<FormErrors>(() =>
  Object.fromEntries(
    Object.entries(displayErrors.value)
      .filter(([field]) => field.startsWith('device.input.'))
      .map(([field, messages]) => [field.slice('device.input.'.length), messages]),
  ),
)

watch(
  () => form.deviceEnabled,
  (enabled) => {
    if (enabled && configuredDevice.value === null) panelView.value = 'device'
    if (!enabled) configuredDevice.value = null
  },
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

function configureDevice(input: DeviceInput): void {
  configuredDevice.value = input
  panelView.value = 'extension'
}

function submit(): void {
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
    hotdesk: {
      ...hotdesk,
      id: hotdesk.id ? hotdesk.id.trim() : null,
      pin: hotdesk.require_pin && hotdesk.pin ? hotdesk.pin.trim() : null,
      clear_pin: false,
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
    device: {
      enabled: form.deviceEnabled,
      input: form.deviceEnabled ? configuredDevice.value : null,
    },
  }
  const validation = validateForm(extensionCreateSchema, input)

  if (!validation.success) {
    clientErrors.value = validation.errors

    return
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
    :width="panelView === 'device' ? 'wide' : 'medium'"
    @close="emit('close')"
  >
    <form v-show="panelView === 'extension'" class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="error"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ error }}
      </div>

      <article class="card-surface overflow-hidden">
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
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">First name</span>
            <input
              v-model="form.firstName"
              required
              maxlength="128"
              class="field-control"
              :class="validationControlClass(fieldError('first_name'))"
              :aria-invalid="Boolean(fieldError('first_name'))"
            />
            <span v-if="displayErrors.first_name" class="text-[10px] text-danger">{{
              displayErrors.first_name[0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Last name</span>
            <input
              v-model="form.lastName"
              required
              maxlength="128"
              class="field-control"
              :class="validationControlClass(fieldError('last_name'))"
              :aria-invalid="Boolean(fieldError('last_name'))"
            />
            <span v-if="displayErrors.last_name" class="text-[10px] text-danger">{{
              displayErrors.last_name[0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Extension number</span>
            <input
              v-model="form.extension"
              required
              inputmode="numeric"
              pattern="[0-9]{2,15}"
              class="field-control font-mono"
              :class="validationControlClass(fieldError('extension'))"
              :aria-invalid="Boolean(fieldError('extension'))"
            />
            <span v-if="displayErrors.extension" class="text-[10px] text-danger">{{
              displayErrors.extension[0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Email</span>
            <input
              v-model="form.email"
              type="email"
              maxlength="254"
              class="field-control"
              :class="validationControlClass(fieldError('email'))"
              :aria-invalid="Boolean(fieldError('email'))"
            />
            <span v-if="displayErrors.email" class="text-[10px] text-danger">{{
              displayErrors.email[0]
            }}</span>
          </label>
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

      <ExtensionCredentialsProfile
        :model-value="credentials"
        :field-errors="displayErrors"
        @update:model-value="updateCredentials"
      />

      <ExtensionUserOptions
        :model-value="userConfiguration"
        :field-errors="displayErrors"
        :language-options="languageOptions"
        :presence-options="presenceOptions"
        @update:model-value="updateUserConfiguration"
      />

      <ExtensionHotdeskProfile
        :model-value="hotdesk"
        :field-errors="displayErrors"
        @update:model-value="updateHotdesk"
      />

      <article class="card-surface overflow-hidden">
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
        <div v-if="form.voicemailEnabled" class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Notification emails</span>
            <input
              v-model="form.notificationEmails"
              placeholder="alice@example.com, team@example.com"
              class="field-control"
              :class="validationControlClass(fieldError('voicemail.notification_emails'))"
              :aria-invalid="Boolean(fieldError('voicemail.notification_emails'))"
            />
            <span class="text-[10px] text-slate-400">Separate multiple addresses with commas.</span>
            <span
              v-if="fieldError('voicemail.notification_emails')"
              class="text-[10px] text-danger"
              >{{ fieldError('voicemail.notification_emails') }}</span
            >
          </label>
          <ToggleSwitch v-model="form.transcribe" label="Enable transcription" />
          <ToggleSwitch v-model="form.requirePin" label="Require mailbox PIN" />
          <label v-if="form.requirePin" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Mailbox PIN</span>
            <input
              v-model="form.pin"
              required
              type="password"
              inputmode="numeric"
              pattern="[0-9]{4,6}"
              autocomplete="new-password"
              class="field-control"
              :class="validationControlClass(fieldError('voicemail.pin'))"
              :aria-invalid="Boolean(fieldError('voicemail.pin'))"
            />
            <span v-if="fieldError('voicemail.pin')" class="text-[10px] text-danger">{{
              fieldError('voicemail.pin')
            }}</span>
          </label>
        </div>
      </article>

      <article class="card-surface overflow-hidden">
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
  </CrudSlideOver>
</template>
