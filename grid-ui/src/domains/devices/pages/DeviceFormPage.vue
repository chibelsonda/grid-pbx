<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircleIcon, KeyIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import { validateForm } from '@/shared/forms/zod'
import DeviceFormFields from '../components/DeviceFormFields.vue'
import {
  defaultDeviceConfiguration,
  hydrateDeviceConfiguration,
  hydrateDeviceRestrictions,
} from '../deviceForm'
import { buildDeviceInput } from '../deviceInput'
import { useDeviceStore } from '../stores/deviceStore'
import { createDeviceFormSchema } from '../schemas/deviceFormSchema'
import type { DeviceBasicForm, DeviceConfiguration, DeviceInput, DeviceType } from '../types/device'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const devices = useDeviceStore()
const isEditing = computed(() => route.name === 'device-edit')
const deviceId = computed(() => (isEditing.value ? String(route.params.deviceId) : null))
const title = computed(() => (isEditing.value ? 'Edit device' : 'Add device'))
const canManage = computed(() => accounts.selected?.permissions.can_manage_devices ?? false)
const form = reactive<DeviceBasicForm>({
  name: '',
  device_type: 'sip_device' as DeviceType,
  make: '',
  family: '',
  model: '',
  mac_address: '',
  is_enabled: true,
  assigned_extension_id: '',
})
const configuration = reactive<DeviceConfiguration>(defaultDeviceConfiguration())
const firstErrorField = ref<string | null>(null)

watch(
  [() => accounts.selectedId, deviceId],
  async ([accountId, selectedDeviceId]) => {
    devices.mutationError = null
    devices.fieldErrors = {}

    if (!accountId) return

    await devices.loadOptions(accountId)

    if (selectedDeviceId) {
      await devices.loadDetail(accountId, selectedDeviceId)
      const device = devices.detail

      if (device) {
        form.name = device.name ?? ''
        form.device_type = device.device_type ?? 'sip_device'
        form.make = device.make ?? ''
        form.family = device.endpoint_family ?? ''
        form.model = device.model ?? ''
        form.mac_address = device.mac_address ?? ''
        form.is_enabled = device.is_enabled
        form.assigned_extension_id = device.assigned_extension?.id ?? ''
        Object.assign(configuration, hydrateDeviceConfiguration(device.configuration))
        if (configuration.provision.endpoint_model === null) {
          configuration.provision.endpoint_model = device.model
        }
        configuration.call_restriction = hydrateDeviceRestrictions(
          configuration.call_restriction,
          devices.restrictionOptions,
        )
      }
    } else {
      Object.assign(configuration, defaultDeviceConfiguration())
      configuration.call_restriction = hydrateDeviceRestrictions(
        configuration.call_restriction,
        devices.restrictionOptions,
      )
    }
  },
  { immediate: true },
)

watch(
  [form, configuration],
  () => {
    if (Object.keys(devices.fieldErrors).length === 0 && !devices.mutationError) return
    devices.fieldErrors = {}
    devices.mutationError = null
    firstErrorField.value = null
  },
  { deep: true },
)

function revealFirstError(errors: Record<string, string[]>): void {
  firstErrorField.value = Object.keys(errors)[0] ?? null
}

async function save(): Promise<void> {
  if (!accounts.selectedId) return

  const input: DeviceInput = buildDeviceInput(form, configuration, devices.schemaCompatibility)
  const validation = validateForm(
    createDeviceFormSchema(devices.schemaCompatibility, devices.provisioningCatalog),
    input,
  )

  if (!validation.success) {
    devices.fieldErrors = validation.errors
    devices.mutationError = null
    revealFirstError(validation.errors)

    return
  }

  const device = deviceId.value
    ? await devices.update(accounts.selectedId, deviceId.value, validation.data)
    : await devices.create(accounts.selectedId, validation.data)

  if (device) {
    await router.push({ name: 'device-detail', params: { deviceId: device.id } })
  } else if (Object.keys(devices.fieldErrors).length > 0) {
    revealFirstError(devices.fieldErrors)
  }
}

function close(): void {
  void router.push(
    deviceId.value
      ? { name: 'device-detail', params: { deviceId: deviceId.value } }
      : { name: 'devices' },
  )
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    :eyebrow="`GridPBX / Devices / ${title}`"
    description="Configuration is written to Switch and immediately projected into MySQL."
    @close="close"
  >
    <div
      v-if="accounts.selected && !canManage"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <KeyIcon class="mx-auto size-10 text-slate-400" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Read-only account access</h2>
        <p class="mt-2 text-xs text-slate-500">
          Your organization role can view devices but cannot change Switch configuration.
        </p>
      </div>
    </div>

    <div
      v-else-if="isEditing && devices.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading device configuration…
    </div>

    <div
      v-else-if="isEditing && devices.detailError"
      class="card-surface p-8 text-center text-xs text-danger"
    >
      {{ devices.detailError }}
    </div>

    <form v-else class="grid gap-5" novalidate @submit.prevent="save">
      <div
        v-if="devices.mutationError && Object.keys(devices.fieldErrors).length === 0"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ devices.mutationError }}
      </div>

      <DeviceFormFields
        :form="form"
        :configuration="configuration"
        :field-errors="devices.fieldErrors"
        :first-error-field="firstErrorField"
        :is-editing="isEditing"
        :extension-options="devices.extensionOptions"
        :media-options="devices.mediaOptions"
        :metaflow-resources="devices.metaflowResources"
        :caller-id-number-options="devices.callerIdNumberOptions"
        :provisioning-catalog="devices.provisioningCatalog"
        :restriction-options="devices.restrictionOptions"
        :schema-compatibility="devices.schemaCompatibility"
        @update:form="Object.assign(form, $event)"
        @update:configuration="Object.assign(configuration, $event)"
      />

      <button
        type="submit"
        :disabled="devices.mutationLoading || !accounts.selectedId"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
      >
        <CheckCircleIcon class="size-4" />
        {{ devices.mutationLoading ? 'Saving…' : isEditing ? 'Save changes' : 'Create device' }}
      </button>
    </form>
  </CrudSlideOver>
</template>
