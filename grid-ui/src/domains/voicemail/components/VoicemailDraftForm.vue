<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { voicemailBoxFormSchemaFor } from '../schemas/voicemailBoxFormSchema'
import type {
  VoicemailBox,
  VoicemailBoxBasicForm,
  VoicemailBoxConfiguration,
  VoicemailBoxInput,
  VoicemailFormOptions,
  VoicemailNotificationCallback,
} from '../types/voicemail'
import {
  buildVoicemailBoxInput,
  defaultVoicemailBoxBasicForm,
  defaultVoicemailNotificationCallback,
  hydrateVoicemailBoxConfiguration,
} from '../voicemailForm'
import VoicemailBoxFormFields from './VoicemailBoxFormFields.vue'

const props = withDefaults(
  defineProps<{
    options: VoicemailFormOptions
    name: string
    mailbox: string
    timezone: string | null
    initial?: VoicemailBox | null
    editing?: boolean
    pinConfigured?: boolean
    externalFieldErrors?: FormErrors
  }>(),
  {
    initial: null,
    editing: false,
    pinConfigured: false,
    externalFieldErrors: () => ({}),
  },
)

const emit = defineEmits<{
  cancel: []
  configured: [input: VoicemailBoxInput]
}>()

const form = reactive<VoicemailBoxBasicForm>({
  ...defaultVoicemailBoxBasicForm(),
  name: props.name,
  mailbox: props.mailbox,
  assigned_extension_id: props.initial?.assigned_extension?.id ?? null,
  timezone: props.initial?.timezone ?? props.timezone,
  notification_emails: props.initial?.notification_emails.join('\n') ?? '',
  transcribe: props.initial?.transcribe ?? false,
  require_pin: props.initial?.require_pin ?? false,
})
const configuration = reactive<VoicemailBoxConfiguration>(
  hydrateVoicemailBoxConfiguration(props.initial?.configuration),
)
const callbackConfigured = ref(Boolean(props.initial?.configuration.notify_callback))
const callbackSchedule = ref(
  props.initial?.configuration.notify_callback?.schedule.join('\n') ?? '',
)
const notificationCallback = reactive<VoicemailNotificationCallback>(
  props.initial?.configuration.notify_callback ?? defaultVoicemailNotificationCallback(),
)
const clientErrors = ref<FormErrors>({})
const fieldErrors = computed(() => ({ ...props.externalFieldErrors, ...clientErrors.value }))

watch([() => props.name, () => props.mailbox], ([name, mailbox]) =>
  Object.assign(form, { name, mailbox }),
)

watch(
  () => props.timezone,
  (timezone, previousTimezone) => {
    if (form.timezone === previousTimezone) form.timezone = timezone
  },
)

watch(
  [form, configuration, callbackConfigured, callbackSchedule, notificationCallback],
  () => {
    if (Object.keys(clientErrors.value).length > 0) clientErrors.value = {}
  },
  { deep: true },
)

function validatedInput(): VoicemailBoxInput | null {
  const input = currentInput()
  const validation = validateForm(
    voicemailBoxFormSchemaFor(props.editing, props.pinConfigured),
    input,
  )

  if (!validation.success) {
    clientErrors.value = validation.errors

    return null
  }

  clientErrors.value = {}

  return validation.data
}

function currentInput(): VoicemailBoxInput {
  return buildVoicemailBoxInput(
    form,
    configuration,
    callbackConfigured.value,
    notificationCallback,
    callbackSchedule.value,
  )
}

function confirm(): void {
  const input = validatedInput()
  if (input) emit('configured', input)
}

defineExpose({ currentInput, validatedInput })
</script>

<template>
  <form class="grid gap-5" novalidate @submit.prevent="confirm">
    <div class="flex items-center justify-between gap-4">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
        @click="emit('cancel')"
      >
        <ArrowLeftIcon class="size-4" />
        Back to extension
      </button>
      <p class="hidden text-[11px] text-slate-500 sm:block">
        Your unfinished extension details are preserved.
      </p>
    </div>

    <VoicemailBoxFormFields
      :form="form"
      :configuration="configuration"
      :callback-configured="callbackConfigured"
      :callback-schedule="callbackSchedule"
      :notification-callback="notificationCallback"
      :field-errors="fieldErrors"
      :options="options"
      :editing="editing"
      :pin-configured="pinConfigured"
      lock-identity
      :show-assignment="false"
      @update:form="Object.assign(form, $event)"
      @update:configuration="Object.assign(configuration, $event)"
      @update:callback-configured="callbackConfigured = $event"
      @update:callback-schedule="callbackSchedule = $event"
      @update:notification-callback="Object.assign(notificationCallback, $event)"
    />

    <footer class="slide-over-actions flex justify-end gap-3 py-4">
      <button
        type="button"
        class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
        @click="emit('cancel')"
      >
        Back
      </button>
      <button
        type="submit"
        class="inline-flex h-10 items-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
      >
        <CheckCircleIcon class="size-4" />
        Use this mailbox
      </button>
    </footer>
  </form>
</template>
