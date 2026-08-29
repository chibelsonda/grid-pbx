<script setup lang="ts">
import { ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
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

function selectFormTab(index: number): void {
  selectedFormTab.value = index
}
</script>

<template>
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
          :extension-options="extensionOptions"
          :field-errors="fieldErrors"
          :provisioning-catalog="provisioningCatalog"
          :schema-compatibility="schemaCompatibility"
          :show-assignment="showAssignment"
        />
      </TabPanel>

      <TabPanel class="outline-none">
        <DeviceAdvancedSettings
          v-if="selectedFormTab === 1"
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
      </TabPanel>
    </TabPanels>
  </TabGroup>
</template>
