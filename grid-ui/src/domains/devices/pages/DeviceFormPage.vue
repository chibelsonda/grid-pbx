<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import { CheckCircleIcon, KeyIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import { validateForm } from '@/shared/forms/zod'
import DeviceAdvancedSettings from '../components/DeviceAdvancedSettings.vue'
import DeviceBasicSettings from '../components/DeviceBasicSettings.vue'
import DeviceTypeSelector from '../components/DeviceTypeSelector.vue'
import {
  defaultDeviceConfiguration,
  deviceSupportsTab,
  hydrateDeviceConfiguration,
  hydrateDeviceRestrictions,
  isBasicDeviceErrorField,
  supportsDeviceNotifications,
  supportsDeviceRecording,
  usesForwarding,
  usesSip,
} from '../deviceForm'
import { useDeviceStore } from '../stores/deviceStore'
import { deviceFormSchema } from '../schemas/deviceFormSchema'
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
const selectedFormTab = ref(0)
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

function nullable(value: string | null): string | null {
  const trimmed = value?.trim() ?? ''

  return trimmed === '' ? null : trimmed
}

function selectDeviceType(deviceType: DeviceType): void {
  form.device_type = deviceType
  configuration.call_forward.enabled = usesForwarding(deviceType)
  configuration.media.fax_option = deviceType === 'fax'
  configuration.sip.invite_format = deviceType === 'sip_uri' ? 'route' : 'contact'
}

function selectFormTab(index: number): void {
  selectedFormTab.value = index
}

function revealFirstError(errors: Record<string, string[]>): void {
  firstErrorField.value = Object.keys(errors)[0] ?? null
  selectedFormTab.value =
    firstErrorField.value && !isBasicDeviceErrorField(firstErrorField.value) ? 1 : 0
}

async function save(): Promise<void> {
  if (!accounts.selectedId) return

  const { username_configured: _usernameConfigured, ...sipConfiguration } = configuration.sip

  const input: DeviceInput = {
    name: form.name.trim(),
    device_type: form.device_type,
    provision: {
      endpoint_brand: nullable(form.make),
      endpoint_family: nullable(form.family),
      endpoint_model: nullable(form.model),
    },
    mac_address: nullable(form.mac_address),
    is_enabled: form.is_enabled,
    assigned_extension_id: nullable(form.assigned_extension_id),
    ...(usesForwarding(form.device_type)
      ? {
          call_forward: {
            ...configuration.call_forward,
            number: nullable(configuration.call_forward.number),
          },
        }
      : {}),
    ...(usesSip(form.device_type)
      ? {
          sip: {
            ...sipConfiguration,
            username: nullable(configuration.sip.username),
            password: nullable(configuration.sip.password),
            realm: nullable(configuration.sip.realm),
            ip: nullable(configuration.sip.ip),
            number: nullable(configuration.sip.number),
            route: nullable(configuration.sip.route),
            static_route: nullable(configuration.sip.static_route),
          },
          ...(deviceSupportsTab(form.device_type, 'audio') ||
          form.device_type === 'fax' ||
          form.device_type === 'ata'
            ? { media: configuration.media }
            : {}),
        }
      : {}),
    ...(deviceSupportsTab(form.device_type, 'caller-id')
      ? {
          caller_id: configuration.caller_id,
          caller_id_options: configuration.caller_id_options,
        }
      : {}),
    call_waiting: configuration.call_waiting,
    do_not_disturb: configuration.do_not_disturb,
    contact_list: configuration.contact_list,
    exclude_from_queues: configuration.exclude_from_queues,
    language: nullable(configuration.language),
    timezone: nullable(configuration.timezone),
    presence_id: nullable(configuration.presence_id),
    ...(supportsDeviceNotifications(form.device_type)
      ? {
          mwi_unsolicited_updates: configuration.mwi_unsolicited_updates,
          register_overwrite_notify: configuration.register_overwrite_notify,
          suppress_unregister_notifications: configuration.suppress_unregister_notifications,
          ringtones: {
            internal: nullable(configuration.ringtones.internal),
            external: nullable(configuration.ringtones.external),
          },
        }
      : {}),
    ...(deviceSupportsTab(form.device_type, 'restrictions')
      ? { call_restriction: configuration.call_restriction }
      : {}),
    ...(supportsDeviceRecording(form.device_type)
      ? { call_recording: configuration.call_recording }
      : {}),
    music_on_hold: { media_id: configuration.music_on_hold.media_id },
    outbound_flags: {
      static: [...configuration.outbound_flags.static],
      dynamic: [...configuration.outbound_flags.dynamic],
    },
    dial_plan: {
      system: [...configuration.dial_plan.system],
      rules: configuration.dial_plan.rules.map((rule) => ({
        pattern: rule.pattern.trim(),
        description: nullable(rule.description),
        prefix: nullable(rule.prefix),
        suffix: nullable(rule.suffix),
      })),
    },
    metaflows: {
      binding_digit: configuration.metaflows.binding_digit,
      digit_timeout: configuration.metaflows.digit_timeout,
      listen_on: configuration.metaflows.listen_on,
    },
  }
  const validation = validateForm(deviceFormSchema, input)

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
        <KeyIcon class="mx-auto size-10 text-slate-300" />
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

      <DeviceTypeSelector v-model="form.device_type" @select="selectDeviceType" />

      <TabGroup :selected-index="selectedFormTab" @change="selectFormTab">
        <div
          class="sticky top-0 z-30 -mx-1 rounded-lg border border-slate-200/90 bg-slate-50/95 p-1 shadow-sm backdrop-blur"
        >
          <TabList
            aria-label="Device form sections"
            class="grid w-full grid-cols-2 gap-1 sm:inline-grid sm:w-auto sm:grid-cols-2"
          >
            <Tab
              v-for="label in ['Basic', 'Advanced']"
              :key="label"
              v-slot="{ selected }"
              as="template"
            >
              <button
                type="button"
                class="min-w-28 rounded-md px-5 py-2.5 text-xs font-semibold outline-none transition focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2"
                :class="
                  selected
                    ? 'bg-brand-500 text-white shadow-sm'
                    : 'bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-700'
                "
              >
                {{ label }}
              </button>
            </Tab>
          </TabList>
        </div>

        <TabPanels class="mt-5">
          <TabPanel class="outline-none">
            <DeviceBasicSettings
              v-if="selectedFormTab === 0"
              v-model:form="form"
              v-model:configuration="configuration"
              :extension-options="devices.extensionOptions"
              :field-errors="devices.fieldErrors"
            />
          </TabPanel>

          <TabPanel class="outline-none">
            <DeviceAdvancedSettings
              v-if="selectedFormTab === 1"
              v-model="configuration"
              :device-type="form.device_type"
              :field-errors="devices.fieldErrors"
              :first-error-field="firstErrorField"
              :is-editing="isEditing"
              :media-options="devices.mediaOptions"
              :restriction-options="devices.restrictionOptions"
            >
              <template #basic>
                <DeviceBasicSettings
                  v-model:form="form"
                  v-model:configuration="configuration"
                  :extension-options="devices.extensionOptions"
                  :field-errors="devices.fieldErrors"
                />
              </template>
            </DeviceAdvancedSettings>
          </TabPanel>
        </TabPanels>
      </TabGroup>

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
