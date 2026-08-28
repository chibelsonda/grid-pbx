<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ArrowPathRoundedSquareIcon, MicrophoneIcon, UserIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { hydrateExtensionUserConfiguration } from '../extensionForm'
import { extensionUpdateSchema } from '../schemas/extensionFormSchema'
import type { ExtensionDetail, ExtensionUpdate } from '../types/extension'
import ExtensionUserOptions from './ExtensionUserOptions.vue'

const props = defineProps<{
  extension: ExtensionDetail
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; save: [input: ExtensionUpdate] }>()
const voicemail = props.extension.voicemail_boxes.find((box) => box.is_managed)
const userConfiguration = reactive(hydrateExtensionUserConfiguration(props.extension.configuration))
const clientErrors = ref<FormErrors>({})
const validationError = ref<string | null>(null)
const displayErrors = computed(() => ({ ...props.fieldErrors, ...clientErrors.value }))
const form = reactive({
  firstName: props.extension.first_name ?? '',
  lastName: props.extension.last_name ?? '',
  extension: props.extension.extension ?? '',
  username: props.extension.username ?? '',
  email: props.extension.email ?? '',
  timezone: props.extension.timezone ?? '',
  isEnabled: props.extension.is_enabled,
  voicemailEnabled: Boolean(voicemail),
  notificationEmails: voicemail?.notification_emails.join(', ') ?? '',
  transcribe: voicemail?.transcribe ?? false,
  requirePin: voicemail?.require_pin ?? false,
  pin: '',
})

function nullable(value: string): string | null {
  return value.trim() || null
}

function submit(): void {
  const input: ExtensionUpdate = {
    first_name: form.firstName.trim(),
    last_name: form.lastName.trim(),
    extension: form.extension.trim(),
    username: nullable(form.username),
    email: nullable(form.email),
    timezone: nullable(form.timezone),
    is_enabled: form.isEnabled,
    ...userConfiguration,
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
  const validation = validateForm(extensionUpdateSchema, input)

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
    title="Edit extension"
    eyebrow="GridPBX / People & Extensions / Edit"
    description="Update the managed Switch user, mailbox, and extension routing as one workflow."
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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
              class="h-10 rounded-md border border-slate-200 px-3 font-mono text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            /><span v-if="displayErrors.extension" class="text-[10px] text-danger">{{
              displayErrors.extension[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Username</span
            ><input
              v-model="form.username"
              maxlength="256"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            /><span v-if="displayErrors.username" class="text-[10px] text-danger">{{
              displayErrors.username[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Email</span
            ><input
              v-model="form.email"
              type="email"
              maxlength="254"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            /><span v-if="displayErrors.email" class="text-[10px] text-danger">{{
              displayErrors.email[0]
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Timezone</span
            ><input
              v-model="form.timezone"
              placeholder="Asia/Manila"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
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

      <ExtensionUserOptions v-model="userConfiguration" :field-errors="displayErrors" />

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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /><span
              v-if="displayErrors['voicemail.notification_emails']"
              class="text-[10px] text-danger"
              >{{ displayErrors['voicemail.notification_emails'][0] }}</span
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
              class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /><span v-if="displayErrors['voicemail.pin']" class="text-[10px] text-danger">{{
              displayErrors['voicemail.pin'][0]
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
