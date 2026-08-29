<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { RadioGroup, RadioGroupOption } from '@headlessui/vue'
import {
  ArrowPathRoundedSquareIcon,
  DevicePhoneMobileIcon,
  MicrophoneIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
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

const props = defineProps<{
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  options: ExtensionFormOptions
}>()
const emit = defineEmits<{ close: []; save: [input: ExtensionCreate] }>()
const userConfiguration = reactive(defaultExtensionUserConfiguration())
const credentials = reactive(defaultExtensionCredentialsInput())
const hotdesk = reactive(defaultExtensionHotdeskInput())
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
  deviceName: '',
  deviceType: 'sip_device',
  macAddress: '',
  sipUsername: '',
  sipPassword: '',
})
const {
  timezoneOptions,
  languageOptions,
  presenceOptions,
  starterDeviceTypes,
  provisionableTypes,
  sipCredentialTypes,
} = useExtensionFormOptions(
  () => props.options,
  () => ({
    timezone: form.timezone,
    language: userConfiguration.language,
    presenceId: userConfiguration.presence_id,
  }),
  () => form.extension,
)
const starterDeviceIsProvisionable = computed(() => provisionableTypes.value.has(form.deviceType))
const starterDeviceSupportsSip = computed(() => sipCredentialTypes.value.has(form.deviceType))

watch(
  () => form.deviceType,
  () => {
    if (!starterDeviceIsProvisionable.value) form.macAddress = ''
    if (!starterDeviceSupportsSip.value) {
      form.sipUsername = ''
      form.sipPassword = ''
    }
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
      name: form.deviceEnabled ? nullable(form.deviceName) : null,
      device_type: form.deviceEnabled ? nullable(form.deviceType) : null,
      mac_address: starterDeviceIsProvisionable.value ? nullable(form.macAddress) : null,
      sip_username: starterDeviceSupportsSip.value ? nullable(form.sipUsername) : null,
      sip_password: starterDeviceSupportsSip.value ? nullable(form.sipPassword) : null,
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
    title="Create extension"
    eyebrow="GridPBX / People & Extensions / Create"
    description="Provision a managed Switch user, optional mailbox and device, and a safe extension callflow."
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
        <div v-if="form.deviceEnabled" class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Device name</span>
            <input
              v-model="form.deviceName"
              class="field-control"
              :class="validationControlClass(fieldError('device.name'))"
              :aria-invalid="Boolean(fieldError('device.name'))"
            />
            <span v-if="fieldError('device.name')" class="text-[10px] text-danger">{{
              fieldError('device.name')
            }}</span>
          </label>
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Type</span>
            <RadioGroup
              v-model="form.deviceType"
              class="grid gap-2 sm:grid-cols-2"
              :class="validationControlClass(fieldError('device.device_type'))"
              aria-label="Initial device type"
            >
              <RadioGroupOption
                v-for="deviceType in starterDeviceTypes"
                :key="deviceType.value"
                v-slot="{ checked }"
                :value="deviceType.value"
                as="template"
              >
                <button
                  type="button"
                  :aria-label="deviceType.label"
                  class="flex min-w-0 items-center gap-3 rounded-md border p-3 text-left transition"
                  :class="
                    checked
                      ? 'border-brand-500 bg-brand-50'
                      : 'border-slate-300 bg-white hover:border-slate-400'
                  "
                >
                  <span
                    class="grid size-8 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-600"
                  >
                    <component :is="deviceType.icon" class="size-4" />
                  </span>
                  <span class="min-w-0">
                    <span class="block text-xs font-semibold text-slate-700">{{
                      deviceType.label
                    }}</span>
                    <span class="block truncate text-[10px] text-slate-500">{{
                      deviceType.description
                    }}</span>
                  </span>
                </button>
              </RadioGroupOption>
            </RadioGroup>
            <span v-if="fieldError('device.device_type')" class="text-[10px] text-danger">{{
              fieldError('device.device_type')
            }}</span>
          </label>
          <label v-if="starterDeviceIsProvisionable" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">MAC address</span>
            <input
              v-model="form.macAddress"
              placeholder="00:11:22:33:44:55"
              class="field-control font-mono"
              :class="validationControlClass(fieldError('device.mac_address'))"
              :aria-invalid="Boolean(fieldError('device.mac_address'))"
            />
            <span v-if="fieldError('device.mac_address')" class="text-[10px] text-danger">{{
              fieldError('device.mac_address')
            }}</span>
          </label>
          <p class="text-[10px] leading-4 text-slate-500 sm:col-span-2">
            Brand, family, model, line keys, and advanced endpoint settings are configured from the
            full Device editor after creation.
          </p>
          <label v-if="starterDeviceSupportsSip" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">SIP username</span>
            <input
              v-model="form.sipUsername"
              autocomplete="off"
              class="field-control"
              :class="validationControlClass(fieldError('device.sip_username'))"
              :aria-invalid="Boolean(fieldError('device.sip_username'))"
            />
            <span v-if="fieldError('device.sip_username')" class="text-[10px] text-danger">{{
              fieldError('device.sip_username')
            }}</span>
          </label>
          <label v-if="starterDeviceSupportsSip" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">SIP password</span>
            <input
              v-model="form.sipPassword"
              type="password"
              autocomplete="new-password"
              class="field-control"
              :class="validationControlClass(fieldError('device.sip_password'))"
              :aria-invalid="Boolean(fieldError('device.sip_password'))"
            />
            <span v-if="fieldError('device.sip_password')" class="text-[10px] text-danger">{{
              fieldError('device.sip_password')
            }}</span>
          </label>
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
  </CrudSlideOver>
</template>
