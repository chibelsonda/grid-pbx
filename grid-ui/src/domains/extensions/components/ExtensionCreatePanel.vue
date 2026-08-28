<script setup lang="ts">
import { reactive } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  DevicePhoneMobileIcon,
  MicrophoneIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { ExtensionCreate } from '../types/extension'

defineProps<{
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; save: [input: ExtensionCreate] }>()
const form = reactive({
  firstName: '',
  lastName: '',
  extension: '',
  username: '',
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
  emit('save', {
    first_name: form.firstName.trim(),
    last_name: form.lastName.trim(),
    extension: form.extension.trim(),
    username: nullable(form.username),
    email: nullable(form.email),
    timezone: nullable(form.timezone),
    is_enabled: form.isEnabled,
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
  })
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
    <form class="grid gap-5" @submit.prevent="submit">
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
            <span v-if="fieldErrors.first_name" class="text-[10px] text-danger">{{
              fieldErrors.first_name[0]
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
            <span v-if="fieldErrors.last_name" class="text-[10px] text-danger">{{
              fieldErrors.last_name[0]
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
            <span v-if="fieldErrors.extension" class="text-[10px] text-danger">{{
              fieldErrors.extension[0]
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Username</span>
            <input
              v-model="form.username"
              maxlength="256"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
            <span v-if="fieldErrors.username" class="text-[10px] text-danger">{{
              fieldErrors.username[0]
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
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Timezone</span>
            <input
              v-model="form.timezone"
              placeholder="Asia/Manila"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
          </label>
          <label class="flex items-center gap-3 sm:col-span-2">
            <input v-model="form.isEnabled" type="checkbox" class="size-4 accent-brand-500" />
            <span class="text-xs font-semibold text-slate-600">Enable this Switch user</span>
          </label>
        </div>
      </article>

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
          <input
            v-model="form.voicemailEnabled"
            type="checkbox"
            class="size-4 accent-brand-500"
            aria-label="Create voicemail box"
          />
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
          </label>
          <label class="flex items-center gap-3"
            ><input
              v-model="form.transcribe"
              type="checkbox"
              class="size-4 accent-brand-500"
            /><span class="text-xs text-slate-600">Enable transcription</span></label
          >
          <label class="flex items-center gap-3"
            ><input
              v-model="form.requirePin"
              type="checkbox"
              class="size-4 accent-brand-500"
            /><span class="text-xs text-slate-600">Require mailbox PIN</span></label
          >
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
            <span v-if="fieldErrors['voicemail.pin']" class="text-[10px] text-danger">{{
              fieldErrors['voicemail.pin'][0]
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
          <input
            v-model="form.deviceEnabled"
            type="checkbox"
            class="size-4 accent-brand-500"
            aria-label="Create initial device"
          />
        </header>
        <div v-if="form.deviceEnabled" class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2"
            ><span class="text-xs font-semibold text-slate-600">Device name</span
            ><input
              v-model="form.deviceName"
              required
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
          /></label>
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Type</span
            ><select
              v-model="form.deviceType"
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
            >
              <option value="sip_device">SIP device</option>
              <option value="softphone">Softphone</option>
              <option value="cellphone">Cellphone</option>
            </select></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">MAC address</span
            ><input
              v-model="form.macAddress"
              placeholder="00:11:22:33:44:55"
              class="h-10 rounded-md border border-slate-200 px-3 font-mono text-xs"
          /></label>
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Make</span
            ><input
              v-model="form.make"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs"
          /></label>
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Model</span
            ><input
              v-model="form.model"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs"
          /></label>
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">SIP username</span
            ><input
              v-model="form.sipUsername"
              autocomplete="off"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs"
          /></label>
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">SIP password</span
            ><input
              v-model="form.sipPassword"
              type="password"
              minlength="12"
              autocomplete="new-password"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs"
          /></label>
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
