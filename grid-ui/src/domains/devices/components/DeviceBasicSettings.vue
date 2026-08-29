<script setup lang="ts">
import { computed } from 'vue'
import { DevicePhoneMobileIcon, LinkIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
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
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Device name</span>
            <input
              v-model="form.name"
              maxlength="128"
              placeholder="Reception Desk Phone"
              class="field-control"
              :class="{
                'border-danger focus:border-danger focus:ring-red-100': fieldError('name'),
              }"
              :aria-invalid="Boolean(fieldError('name'))"
            />
            <span v-if="fieldError('name')" class="text-[11px] text-danger">
              {{ fieldError('name') }}
            </span>
          </label>
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
          <label v-if="usesForwarding(form.device_type)" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Destination number</span>
            <input
              v-model="configuration.call_forward.number"
              :maxlength="schemaCompatibility.call_forward.number_max_length"
              class="field-control"
              :class="{
                'border-danger focus:border-danger focus:ring-red-100':
                  fieldError('call_forward.number'),
              }"
              :aria-invalid="Boolean(fieldError('call_forward.number'))"
              placeholder="+15551234567"
            />
            <span v-if="fieldError('call_forward.number')" class="text-[11px] text-danger">
              {{ fieldError('call_forward.number') }}
            </span>
          </label>
          <label v-if="form.device_type === 'sip_uri'" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">SIP URI</span>
            <input
              v-model="configuration.sip.route"
              maxlength="2048"
              class="field-control font-mono"
              :class="{
                'border-danger focus:border-danger focus:ring-red-100': fieldError('sip.route'),
              }"
              :aria-invalid="Boolean(fieldError('sip.route'))"
              placeholder="sip:user@example.com"
            />
            <span v-if="fieldError('sip.route')" class="text-[11px] text-danger">
              {{ fieldError('sip.route') }}
            </span>
          </label>
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
          <label
            v-for="field in catalogAvailable ? [] : provisioningFields"
            :key="field.key"
            class="grid gap-2"
          >
            <span class="text-xs font-semibold text-slate-600">{{ field.label }}</span>
            <input
              v-model="form[field.key]"
              maxlength="255"
              class="field-control"
              :class="{
                'border-danger focus:border-danger focus:ring-red-100': fieldError(field.error),
              }"
              :aria-invalid="Boolean(fieldError(field.error))"
            />
            <span v-if="fieldError(field.error)" class="text-[11px] text-danger">
              {{ fieldError(field.error) }}
            </span>
          </label>
          <label
            v-if="
              !catalogAvailable ||
              schemaCompatibility.provision.endpoint_model_types.includes('array')
            "
            class="grid gap-2 sm:col-span-2"
          >
            <span class="text-xs font-semibold text-slate-600">
              {{
                schemaCompatibility.provision.endpoint_model_types.includes('array')
                  ? 'Model identifiers'
                  : 'Model'
              }}
            </span>
            <textarea
              v-model="modelIdentifiers"
              :rows="schemaCompatibility.provision.endpoint_model_types.includes('array') ? 2 : 1"
              maxlength="8192"
              class="field-control py-2"
              :class="{
                'min-h-20': schemaCompatibility.provision.endpoint_model_types.includes('array'),
                'border-danger focus:border-danger focus:ring-red-100': fieldError(
                  'provision.endpoint_model',
                ),
              }"
              :aria-invalid="Boolean(fieldError('provision.endpoint_model'))"
              :placeholder="
                schemaCompatibility.provision.endpoint_model_types.includes('array')
                  ? 'One model identifier per line, in priority order'
                  : ''
              "
            />
            <span class="text-[10px] text-slate-400">
              <template v-if="schemaCompatibility.provision.endpoint_model_types.includes('array')">
                The connected Switch accepts one model or an ordered list.
              </template>
            </span>
            <span v-if="fieldError('provision.endpoint_model')" class="text-[11px] text-danger">
              {{ fieldError('provision.endpoint_model') }}
            </span>
          </label>
          <label v-if="schemaCompatibility.provision.template_id" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Provisioner template ID</span>
            <input
              v-model="configuration.provision.id"
              maxlength="255"
              class="field-control font-mono"
              :class="{
                'border-danger focus:border-danger focus:ring-red-100': fieldError('provision.id'),
              }"
              :aria-invalid="Boolean(fieldError('provision.id'))"
              placeholder="Optional template identifier"
            />
            <span v-if="fieldError('provision.id')" class="text-[11px] text-danger">
              {{ fieldError('provision.id') }}
            </span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">MAC address</span>
            <input
              v-model="form.mac_address"
              maxlength="64"
              class="field-control font-mono"
              :class="{
                'border-danger focus:border-danger focus:ring-red-100': fieldError('mac_address'),
              }"
              :aria-invalid="Boolean(fieldError('mac_address'))"
              placeholder="00:11:22:33:44:55"
              @blur="normalizeMac"
            />
            <span v-if="fieldError('mac_address')" class="text-[11px] text-danger">
              {{ fieldError('mac_address') }}
            </span>
          </label>
        </div>
      </article>
    </div>

    <article class="card-surface h-fit overflow-hidden">
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

<style scoped>
@reference "../../../assets/main.css";

.field-control {
  @apply h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100;
}
</style>
