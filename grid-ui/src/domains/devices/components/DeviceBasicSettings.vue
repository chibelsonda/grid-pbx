<script setup lang="ts">
import { DevicePhoneMobileIcon, LinkIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import { supportsProvisioning, usesForwarding } from '../deviceForm'
import type { DeviceBasicForm, DeviceConfiguration, ExtensionOption } from '../types/device'

const props = defineProps<{
  extensionOptions: ExtensionOption[]
  fieldErrors: Record<string, string[]>
}>()
const form = defineModel<DeviceBasicForm>('form', { required: true })
const configuration = defineModel<DeviceConfiguration>('configuration', { required: true })
const provisioningFields = [
  { key: 'make', label: 'Brand', error: 'provision.endpoint_brand' },
  { key: 'family', label: 'Family', error: 'provision.endpoint_family' },
  { key: 'model', label: 'Model', error: 'provision.endpoint_model' },
] as const

function fieldError(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
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
            v-model="form.is_enabled"
            label="Enabled"
            description="Allow this endpoint to operate"
            class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
          />
          <label v-if="usesForwarding(form.device_type)" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Destination number</span>
            <input
              v-model="configuration.call_forward.number"
              maxlength="15"
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
          <label v-for="field in provisioningFields" :key="field.key" class="grid gap-2">
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
