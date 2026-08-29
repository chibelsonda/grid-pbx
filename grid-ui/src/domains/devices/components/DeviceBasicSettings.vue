<script setup lang="ts">
import { computed } from 'vue'
import { DevicePhoneMobileIcon, LinkIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import { supportsDeviceNotifications, supportsProvisioning, usesForwarding } from '../deviceForm'
import { normalizeMacAddress } from '../deviceInput'
import type {
  DeviceBasicForm,
  DeviceConfiguration,
  DeviceProvisioningCatalog,
  DeviceSchemaCompatibility,
  ExtensionOption,
} from '../types/device'

const props = defineProps<{
  extensionOptions: ExtensionOption[]
  fieldErrors: Record<string, string[]>
  provisioningCatalog: DeviceProvisioningCatalog
  schemaCompatibility: DeviceSchemaCompatibility
  showAssignment?: boolean
}>()
const form = defineModel<DeviceBasicForm>('form', { required: true })
const configuration = defineModel<DeviceConfiguration>('configuration', { required: true })
const provisioningFields = [
  { key: 'make', label: 'Brand', error: 'provision.endpoint_brand' },
  { key: 'family', label: 'Family', error: 'provision.endpoint_family' },
] as const
const catalogAvailable = computed(
  () => props.provisioningCatalog.available && props.provisioningCatalog.brands.length > 0,
)
const matchesCatalogIdentity = (
  selected: string,
  ...candidates: Array<string | number | null | undefined>
) =>
  candidates.some(
    (candidate) =>
      candidate !== null &&
      candidate !== undefined &&
      selected.trim().toLowerCase() === String(candidate).trim().toLowerCase(),
  )
const selectedBrand = computed(() =>
  props.provisioningCatalog.brands.find((brand) =>
    matchesCatalogIdentity(form.value.make, brand.id, brand.name),
  ),
)
const selectedFamily = computed(() =>
  selectedBrand.value?.families.find((family) =>
    matchesCatalogIdentity(form.value.family, family.id, family.name),
  ),
)
const selectedModel = computed(() =>
  selectedFamily.value?.models.find((model) =>
    matchesCatalogIdentity(form.value.model, model.id, model.name, model.template_id),
  ),
)
const brandOptions = computed<ListboxOptionValue[]>(() => [
  { value: '', label: 'Select a brand' },
  ...withCurrentOption(
    props.provisioningCatalog.brands.map((brand) => ({ value: brand.id, label: brand.name })),
    form.value.make,
  ),
])
const familyOptions = computed<ListboxOptionValue[]>(() => [
  { value: '', label: 'Select a family' },
  ...withCurrentOption(
    (selectedBrand.value?.families ?? []).map((family) => ({
      value: family.id,
      label: family.name,
    })),
    form.value.family,
  ),
])
const modelOptions = computed<ListboxOptionValue[]>(() => [
  { value: '', label: 'Select a model' },
  ...withCurrentOption(
    (selectedFamily.value?.models ?? []).map((model) => ({ value: model.id, label: model.name })),
    form.value.model,
  ),
])
const modelIdentifiers = computed({
  get: () => {
    const endpointModel = configuration.value.provision.endpoint_model

    if (Array.isArray(endpointModel)) return endpointModel.join('\n')
    if (typeof endpointModel === 'number') return String(endpointModel)

    return endpointModel ?? form.value.model
  },
  set: (value: string) => {
    const models = value
      .split(/[\n,]+/)
      .map((model) => model.trim())
      .filter((model, index, values) => model !== '' && values.indexOf(model) === index)

    form.value.model = models[0] ?? ''
    configuration.value.provision.endpoint_model =
      props.schemaCompatibility.provision.endpoint_model_types.includes('array') &&
      models.length > 1
        ? models
        : (models[0] ?? null)
  },
})

function withCurrentOption(options: ListboxOptionValue[], current: string | null) {
  if (!current || options.some((option) => option.value === current)) return options
  return [...options, { value: current, label: `${current} (current Switch value)` }]
}

function stringValue(value: ListboxValue): string {
  return typeof value === 'string' ? value : ''
}

function selectBrand(value: ListboxValue): void {
  form.value.make = stringValue(value)
  form.value.family = ''
  form.value.model = ''
  configuration.value.provision.endpoint_model = null
  configuration.value.provision.id = null
}

function selectFamily(value: ListboxValue): void {
  form.value.family = stringValue(value)
  form.value.model = ''
  configuration.value.provision.endpoint_model = null
  configuration.value.provision.id = null
}

function selectModel(value: ListboxValue): void {
  form.value.model = stringValue(value)
  configuration.value.provision.endpoint_model = form.value.model || null
  configuration.value.provision.id = selectedModel.value?.template_id ?? null
}

function normalizeMac(): void {
  form.value.mac_address =
    normalizeMacAddress(form.value.mac_address) ?? form.value.mac_address.trim()
}

function fieldError(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function setEnabled(value: boolean): void {
  form.value.is_enabled = value

  if (usesForwarding(form.value.device_type)) {
    configuration.value.call_forward.enabled = value
  }
}
</script>

<template>
  <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
    <div class="grid gap-5">
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <DevicePhoneMobileIcon class="size-5 text-brand-600" />
          <h2 class="text-sm font-semibold text-slate-700">Device identity</h2>
        </header>
        <div class="grid gap-5 p-5 sm:grid-cols-2">
          <FormInput
            v-model="form.name"
            label="Device name"
            maxlength="128"
            placeholder="Reception Desk Phone"
            required
            class="sm:col-span-2"
            :error="fieldError('name')"
          />
          <ToggleSwitch
            :model-value="form.is_enabled"
            label="Enabled"
            :description="
              usesForwarding(form.device_type)
                ? 'Enable this forwarded destination'
                : 'Allow this endpoint to operate'
            "
            class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
            @update:model-value="setEnabled"
          />
          <ToggleSwitch
            v-if="supportsDeviceNotifications(form.device_type)"
            :model-value="!configuration.suppress_unregister_notifications"
            label="Notify when unregistered"
            description="Send a notification when this device loses registration"
            class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
            @update:model-value="configuration.suppress_unregister_notifications = !$event"
          />
          <FormInput
            v-if="usesForwarding(form.device_type)"
            v-model="configuration.call_forward.number"
            label="Destination number"
            :maxlength="schemaCompatibility.call_forward.number_max_length"
            placeholder="+15551234567"
            class="sm:col-span-2"
            :error="fieldError('call_forward.number')"
          />
          <FormInput
            v-if="form.device_type === 'sip_uri'"
            v-model="configuration.sip.route"
            label="SIP URI"
            maxlength="2048"
            input-class="font-mono"
            placeholder="sip:user@example.com"
            class="sm:col-span-2"
            :error="fieldError('sip.route')"
          />
        </div>
      </article>

      <article v-if="supportsProvisioning(form.device_type)" class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <WrenchScrewdriverIcon class="size-5 text-info" />
          <h2 class="text-sm font-semibold text-slate-700">Hardware and provisioning</h2>
        </header>
        <div class="grid gap-5 p-5 sm:grid-cols-2">
          <div
            v-if="!catalogAvailable && provisioningCatalog.reason"
            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-800 sm:col-span-2"
          >
            {{ provisioningCatalog.reason }}
          </div>
          <template v-if="catalogAvailable">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Brand</span>
              <FormListbox
                :model-value="form.make"
                :options="brandOptions"
                aria-label="Select device brand"
                :invalid="Boolean(fieldError('provision.endpoint_brand'))"
                @update:model-value="selectBrand"
              />
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Family</span>
              <FormListbox
                :model-value="form.family"
                :options="familyOptions"
                aria-label="Select device family"
                :disabled="!form.make"
                :invalid="Boolean(fieldError('provision.endpoint_family'))"
                @update:model-value="selectFamily"
              />
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Model</span>
              <FormListbox
                :model-value="form.model"
                :options="modelOptions"
                aria-label="Select device model"
                :disabled="!form.family"
                :invalid="Boolean(fieldError('provision.endpoint_model'))"
                @update:model-value="selectModel"
              />
            </label>
          </template>
          <FormInput
            v-for="field in catalogAvailable ? [] : provisioningFields"
            :key="field.key"
            v-model="form[field.key]"
            :label="field.label"
            maxlength="255"
            :error="fieldError(field.error)"
          />
          <FormTextarea
            v-if="
              !catalogAvailable ||
              schemaCompatibility.provision.endpoint_model_types.includes('array')
            "
            v-model="modelIdentifiers"
            :label="
              schemaCompatibility.provision.endpoint_model_types.includes('array')
                ? 'Model identifiers'
                : 'Model'
            "
            :rows="schemaCompatibility.provision.endpoint_model_types.includes('array') ? 2 : 1"
            maxlength="8192"
            :size="
              schemaCompatibility.provision.endpoint_model_types.includes('array')
                ? 'medium'
                : 'compact'
            "
            class="sm:col-span-2"
            :description="
              schemaCompatibility.provision.endpoint_model_types.includes('array')
                ? 'The connected Switch accepts one model or an ordered list.'
                : null
            "
            :error="fieldError('provision.endpoint_model')"
            :placeholder="
              schemaCompatibility.provision.endpoint_model_types.includes('array')
                ? 'One model identifier per line, in priority order'
                : ''
            "
          />
          <FormInput
            v-if="schemaCompatibility.provision.template_id"
            v-model="configuration.provision.id"
            label="Provisioner template ID"
            maxlength="255"
            input-class="font-mono"
            placeholder="Optional template identifier"
            class="sm:col-span-2"
            :error="fieldError('provision.id')"
          />
          <FormInput
            v-model="form.mac_address"
            label="MAC address"
            maxlength="64"
            input-class="font-mono"
            placeholder="00:11:22:33:44:55"
            :error="fieldError('mac_address')"
            @blur="normalizeMac"
          />
        </div>
      </article>
    </div>

    <article v-if="showAssignment !== false" class="card-surface h-fit overflow-hidden">
      <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
        <LinkIcon class="size-5 text-violet-500" />
        <h2 class="text-sm font-semibold text-slate-700">Assignment</h2>
      </header>
      <div class="p-5">
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Extension</span>
          <FormSelect
            v-model="form.assigned_extension_id"
            class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
            :class="{
              'border-danger focus:border-danger focus:ring-red-100':
                fieldError('assigned_extension_id'),
            }"
            :aria-invalid="Boolean(fieldError('assigned_extension_id'))"
          >
            <option value="">Unassigned</option>
            <option v-for="extension in extensionOptions" :key="extension.id" :value="extension.id">
              {{ extension.display_name
              }}{{ extension.extension ? ` · ${extension.extension}` : '' }}
            </option>
          </FormSelect>
          <span v-if="fieldError('assigned_extension_id')" class="text-[11px] text-danger">
            {{ fieldError('assigned_extension_id') }}
          </span>
        </label>
      </div>
    </article>
  </div>
</template>
