<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  DevicePhoneMobileIcon,
  MicrophoneIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { deviceTypes } from '@/domains/devices/deviceForm'
import {
  defaultExtensionCredentialsInput,
  defaultExtensionHotdeskInput,
  defaultExtensionUserConfiguration,
} from '../extensionForm'
import { extensionCreateSchema } from '../schemas/extensionFormSchema'
import type { ExtensionCreate } from '../types/extension'
import ExtensionCredentialsProfile from './ExtensionCredentialsProfile.vue'
import ExtensionHotdeskProfile from './ExtensionHotdeskProfile.vue'
import ExtensionUserOptions from './ExtensionUserOptions.vue'

const props = defineProps<{
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; save: [input: ExtensionCreate] }>()
const userConfiguration = reactive(defaultExtensionUserConfiguration())
const credentials = reactive(defaultExtensionCredentialsInput())
const hotdesk = reactive(defaultExtensionHotdeskInput())
const clientErrors = ref<FormErrors>({})
const validationError = ref<string | null>(null)
const displayErrors = computed(() => ({ ...props.fieldErrors, ...clientErrors.value }))
const form = reactive({
  firstName: '',
  lastName: '',
  extension: '',
  email: '',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
  isEnabled: true,
  voicemailEnabled: true,
  notificationEmails: '',
  transcribe: false,
  requirePin: false,
  pin: '',
  deviceEnabled: false,
  deviceName: '',
  deviceType: 'sip_device',
  make: '',
  model: '',
  macAddress: '',
  sipUsername: '',
  sipPassword: '',
})

function nullable(value: string): string | null {
  const trimmed = value.trim()
  return trimmed ? trimmed : null
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
    timezone: nullable(form.timezone),
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
      make: nullable(form.make),
      model: nullable(form.model),
      mac_address: nullable(form.macAddress),
      sip_username: nullable(form.sipUsername),
      sip_password: nullable(form.sipPassword),
    },
  }
  const validation = validateForm(extensionCreateSchema, input)

  if (!validation.success) {
    clientErrors.value = validation.errors
    validationError.value = 'Check the highlighted fields and try again.'

    return
  }

  clientErrors.value = {}
  validationError.value = null
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
        v-if="validationError || error"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ validationError ?? error }}
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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
              class="h-10 rounded-md border border-slate-200 px-3 font-mono text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
            <span v-if="displayErrors.email" class="text-[10px] text-danger">{{
              displayErrors.email[0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Timezone</span>
            <input
              v-model="form.timezone"
              placeholder="Asia/Manila"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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

      <ExtensionCredentialsProfile v-model="credentials" :field-errors="displayErrors" />

      <ExtensionUserOptions v-model="userConfiguration" :field-errors="displayErrors" />

      <ExtensionHotdeskProfile v-model="hotdesk" :field-errors="displayErrors" />

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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
            <span class="text-[10px] text-slate-400">Separate multiple addresses with commas.</span>
            <span
              v-if="displayErrors['voicemail.notification_emails']"
              class="text-[10px] text-danger"
              >{{ displayErrors['voicemail.notification_emails'][0] }}</span
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
            <span v-if="displayErrors['voicemail.pin']" class="text-[10px] text-danger">{{
              displayErrors['voicemail.pin'][0]
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
            <input v-model="form.deviceName" class="field-control" />
            <span v-if="displayErrors['device.name']" class="text-[10px] text-danger">{{
              displayErrors['device.name'][0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Type</span>
            <FormSelect
              v-model="form.deviceType"
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
            >
              <option
                v-for="deviceType in deviceTypes"
                :key="deviceType.value"
                :value="deviceType.value"
              >
                {{ deviceType.label }}
              </option>
            </FormSelect>
            <span v-if="displayErrors['device.device_type']" class="text-[10px] text-danger">{{
              displayErrors['device.device_type'][0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">MAC address</span>
            <input
              v-model="form.macAddress"
              placeholder="00:11:22:33:44:55"
              class="field-control font-mono"
            />
            <span v-if="displayErrors['device.mac_address']" class="text-[10px] text-danger">{{
              displayErrors['device.mac_address'][0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Make</span>
            <input v-model="form.make" class="field-control" />
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Model</span>
            <input v-model="form.model" class="field-control" />
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">SIP username</span>
            <input v-model="form.sipUsername" autocomplete="off" class="field-control" />
            <span v-if="displayErrors['device.sip_username']" class="text-[10px] text-danger">{{
              displayErrors['device.sip_username'][0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">SIP password</span>
            <input
              v-model="form.sipPassword"
              type="password"
              autocomplete="new-password"
              class="field-control"
            />
            <span v-if="displayErrors['device.sip_password']" class="text-[10px] text-danger">{{
              displayErrors['device.sip_password'][0]
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
