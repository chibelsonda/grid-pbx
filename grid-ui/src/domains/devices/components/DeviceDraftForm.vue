<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { defaultDeviceConfiguration, hydrateDeviceRestrictions } from '../deviceForm'
import { buildDeviceInput } from '../deviceInput'
import { createDeviceFormSchema } from '../schemas/deviceFormSchema'
import type {
  DeviceBasicForm,
  DeviceConfiguration,
  DeviceInput,
  DeviceOptions,
} from '../types/device'
import DeviceFormFields from './DeviceFormFields.vue'

const props = withDefaults(
  defineProps<{
    options: DeviceOptions
    externalFieldErrors?: FormErrors
  }>(),
  { externalFieldErrors: () => ({}) },
)

const emit = defineEmits<{
  cancel: []
  configured: [input: DeviceInput]
}>()

const form = reactive<DeviceBasicForm>({
  name: '',
  device_type: 'sip_device',
  make: '',
  family: '',
  model: '',
  mac_address: '',
  is_enabled: true,
  assigned_extension_id: '',
})
const configuration = reactive<DeviceConfiguration>(defaultDeviceConfiguration())
const clientErrors = ref<FormErrors>({})
const fieldErrors = computed(() => ({ ...props.externalFieldErrors, ...clientErrors.value }))
const firstErrorField = computed(
  () => Object.keys(clientErrors.value)[0] ?? Object.keys(props.externalFieldErrors)[0] ?? null,
)

watch(
  () => props.options.restrictions,
  (restrictions) => {
    configuration.call_restriction = hydrateDeviceRestrictions(
      configuration.call_restriction,
      restrictions,
    )
  },
  { immediate: true },
)

watch(
  [form, configuration],
  () => {
    if (Object.keys(clientErrors.value).length === 0) return
    clientErrors.value = {}
  },
  { deep: true },
)

function confirm(): void {
  const input = buildDeviceInput(form, configuration, props.options.device_schema)
  const validation = validateForm(
    createDeviceFormSchema(props.options.device_schema, props.options.provisioning_catalog),
    input,
  )

  if (!validation.success) {
    clientErrors.value = validation.errors

    return
  }

  clientErrors.value = {}
  emit('configured', validation.data)
}
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

    <DeviceFormFields
      :form="form"
      :configuration="configuration"
      :field-errors="fieldErrors"
      :first-error-field="firstErrorField"
      :extension-options="[]"
      :media-options="options.media"
      :metaflow-resources="options.metaflow_resources"
      :caller-id-number-options="options.caller_id_numbers"
      :provisioning-catalog="options.provisioning_catalog"
      :restriction-options="options.restrictions"
      :schema-compatibility="options.device_schema"
      :show-assignment="false"
      @update:form="Object.assign(form, $event)"
      @update:configuration="Object.assign(configuration, $event)"
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
        Use this device
      </button>
    </footer>
  </form>
</template>
