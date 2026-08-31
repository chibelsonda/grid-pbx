<script setup lang="ts">
import { ref, watch } from 'vue'
import BasicAdvancedTabSelector from '@/shared/components/BasicAdvancedTabSelector.vue'
import DeviceAdvancedSettings from './DeviceAdvancedSettings.vue'
import DeviceBasicSettings from './DeviceBasicSettings.vue'
import DeviceTypeSelector from './DeviceTypeSelector.vue'
import { isBasicDeviceErrorField, usesForwarding } from '../deviceForm'
import type {
  DeviceBasicForm,
  DeviceCallerIdNumberOption,
  DeviceConfiguration,
  DeviceMetaflowResources,
  DeviceProvisioningCatalog,
  DeviceRestrictionOption,
  DeviceSchemaCompatibility,
  DeviceType,
  ExtensionOption,
} from '../types/device'

const props = withDefaults(
  defineProps<{
    fieldErrors: Record<string, string[]>
    firstErrorField: string | null
    isEditing?: boolean
    showAssignment?: boolean
    extensionOptions: ExtensionOption[]
    mediaOptions: Array<{ id: string; name: string | null }>
    metaflowResources?: DeviceMetaflowResources
    callerIdNumberOptions?: DeviceCallerIdNumberOption[]
    provisioningCatalog: DeviceProvisioningCatalog
    restrictionOptions: DeviceRestrictionOption[]
    schemaCompatibility: DeviceSchemaCompatibility
  }>(),
  {
    isEditing: false,
    showAssignment: true,
    metaflowResources: () => ({ callflows: [], devices: [] }),
    callerIdNumberOptions: () => [],
  },
)

const form = defineModel<DeviceBasicForm>('form', { required: true })
const configuration = defineModel<DeviceConfiguration>('configuration', { required: true })
const selectedFormTab = ref(0)

watch(
  () => props.firstErrorField,
  (field) => {
    if (field) selectedFormTab.value = isBasicDeviceErrorField(field) ? 0 : 1
  },
)

function selectDeviceType(deviceType: DeviceType): void {
  form.value.device_type = deviceType
  configuration.value.call_forward.enabled = usesForwarding(deviceType)
  configuration.value.media.fax_option = deviceType === 'fax'
  configuration.value.outbound_flags.static =
    deviceType === 'fax'
      ? [...new Set(['fax', ...configuration.value.outbound_flags.static])]
      : configuration.value.outbound_flags.static.filter((flag) => flag !== 'fax')
  configuration.value.sip.invite_format =
    deviceType === 'sip_uri' && props.schemaCompatibility.sip.invite_formats.includes('route')
      ? 'route'
      : props.schemaCompatibility.sip.invite_formats.includes('contact')
        ? 'contact'
        : (props.schemaCompatibility.sip.invite_formats[0] ?? 'username')
}
</script>

<template>
  <DeviceTypeSelector v-model="form.device_type" @select="selectDeviceType" />

  <BasicAdvancedTabSelector v-model="selectedFormTab" aria-label="Device form sections" sticky />

  <div v-if="selectedFormTab === 0" role="tabpanel" class="mt-5 outline-none">
    <DeviceBasicSettings
      v-model:form="form"
      v-model:configuration="configuration"
      :extension-options="extensionOptions"
      :field-errors="fieldErrors"
      :provisioning-catalog="provisioningCatalog"
      :schema-compatibility="schemaCompatibility"
      :show-assignment="showAssignment"
    />
  </div>

  <div v-else role="tabpanel" class="mt-5 outline-none">
    <DeviceAdvancedSettings
      :key="form.device_type"
      v-model="configuration"
      :device-type="form.device_type"
      :field-errors="fieldErrors"
      :first-error-field="firstErrorField"
      :is-editing="isEditing"
      :media-options="mediaOptions"
      :metaflow-resources="metaflowResources"
      :extension-options="extensionOptions"
      :caller-id-number-options="callerIdNumberOptions"
      :restriction-options="restrictionOptions"
      :schema-compatibility="schemaCompatibility"
    >
      <template #basic>
        <DeviceBasicSettings
          v-model:form="form"
          v-model:configuration="configuration"
          :extension-options="extensionOptions"
          :field-errors="fieldErrors"
          :provisioning-catalog="provisioningCatalog"
          :schema-compatibility="schemaCompatibility"
          :show-assignment="showAssignment"
        />
      </template>
    </DeviceAdvancedSettings>
  </div>
</template>
