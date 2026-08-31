<script setup lang="ts">
import { computed } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import { useDelimitedStringList } from '@/shared/forms/useDelimitedStringList'
import type {
  DeviceConfiguration,
  DeviceMetaflowResources,
  DeviceSchemaCompatibility,
  DeviceSipHeader,
} from '../types/device'
import DeviceFormatterSettings from './DeviceFormatterSettings.vue'
import MetaflowSettings from '@/shared/switch/metaflows/components/MetaflowSettings.vue'

const props = defineProps<{
  fieldErrors: Record<string, string[]>
  mediaOptions: Array<{ id: string; name: string | null }>
  metaflowResources: DeviceMetaflowResources
  extensionOptions: Array<{ id: string; display_name: string; extension: string | null }>
  supportsSip: boolean
  supportsProvisioning: boolean
  schemaCompatibility: DeviceSchemaCompatibility
}>()
const configuration = defineModel<DeviceConfiguration>({ required: true })
const headerDirections = ['in', 'out'] as const
const provisioningEventFields = computed(() =>
  (
    [
      { key: 'check_sync_event', label: 'Check-sync event' },
      { key: 'check_sync_reload', label: 'Reload event' },
      { key: 'check_sync_reboot', label: 'Reboot event' },
    ] as const
  ).filter((field) => props.schemaCompatibility.provision[field.key]),
)

const staticFlags = useDelimitedStringList(
  () => configuration.value.outbound_flags.static,
  (values) => (configuration.value.outbound_flags.static = values),
)
const dynamicFlags = useDelimitedStringList(
  () => configuration.value.outbound_flags.dynamic,
  (values) => (configuration.value.outbound_flags.dynamic = values),
)
const systemDialPlans = useDelimitedStringList(
  () => configuration.value.dial_plan.system,
  (values) => (configuration.value.dial_plan.system = values),
)
const generalFlags = useDelimitedStringList(
  () => configuration.value.flags,
  (values) => (configuration.value.flags = values),
)

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function addHeader(direction: 'in' | 'out'): void {
  configuration.value.sip.custom_sip_headers[direction].push({ name: '', value: '' })
}

function removeHeader(direction: 'in' | 'out', index: number): void {
  configuration.value.sip.custom_sip_headers[direction].splice(index, 1)
}

function addDialPlanRule(): void {
  configuration.value.dial_plan.rules.push({
    pattern: '',
    description: null,
    prefix: null,
    suffix: null,
  })
}

function removeDialPlanRule(index: number): void {
  configuration.value.dial_plan.rules.splice(index, 1)
}

function headerRows(direction: 'in' | 'out'): DeviceSipHeader[] {
  return configuration.value.sip.custom_sip_headers[direction]
}
</script>

<template>
  <section class="grid gap-3 sm:col-span-2">
    <Disclosure v-slot="{ open }" default-open>
      <div class="rounded-md border border-slate-200">
        <DisclosureButton
          class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Routing and endpoint behavior
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-5 border-t border-slate-100 p-4 sm:grid-cols-2">
          <FormTextarea
            v-model="staticFlags"
            label="Static outbound flags"
            rows="2"
            placeholder="fax, trusted"
            description="Separate flags with commas or new lines."
            :error="error('outbound_flags.static')"
          />
          <FormTextarea
            v-model="dynamicFlags"
            label="Dynamic outbound flags"
            rows="2"
            placeholder="Optional runtime flags"
            description="Resolved by the Switch at call time."
            :error="error('outbound_flags.dynamic')"
          />
        </DisclosurePanel>
      </div>
    </Disclosure>

    <Disclosure v-slot="{ open }">
      <div class="rounded-md border border-slate-200">
        <DisclosureButton
          class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          General flags and formatters
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-5 border-t border-slate-100 p-4">
          <FormTextarea
            v-model="generalFlags"
            label="Application flags"
            rows="2"
            placeholder="crm_managed, priority_endpoint"
            description="Flags consumed by external applications; separate with commas or new lines."
            :error="error('flags')"
          />
          <DeviceFormatterSettings v-model="configuration.formatters" :field-errors="fieldErrors" />
        </DisclosurePanel>
      </div>
    </Disclosure>

    <Disclosure
      v-if="
        supportsProvisioning &&
        (schemaCompatibility.provision.check_sync_event ||
          schemaCompatibility.provision.check_sync_reload ||
          schemaCompatibility.provision.check_sync_reboot)
      "
      v-slot="{ open }"
    >
      <div class="rounded-md border border-slate-200">
        <DisclosureButton
          class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Provisioning events
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-3">
          <FormInput
            v-for="field in provisioningEventFields"
            :key="field.key"
            v-model="configuration.provision[field.key]"
            :label="field.label"
            maxlength="255"
            input-class="font-mono"
            placeholder="Switch default"
            :error="error(`provision.${field.key}`)"
          />
          <p class="text-[10px] leading-4 text-slate-400 sm:col-span-3">
            These optional event names configure how this provisioned endpoint reacts to Switch
            check-sync requests. Reload and reboot commands remain explicit actions on the detail
            page.
          </p>
        </DisclosurePanel>
      </div>
    </Disclosure>

    <Disclosure v-if="supportsSip" v-slot="{ open }">
      <div class="rounded-md border border-slate-200">
        <DisclosureButton
          class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Custom SIP headers
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-5 border-t border-slate-100 p-4 lg:grid-cols-2">
          <section
            v-for="direction in headerDirections"
            :key="direction"
            class="grid content-start gap-3"
          >
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-xs font-semibold text-slate-700">
                  {{ direction === 'in' ? 'Inbound to Switch' : 'Outbound to endpoint' }}
                </h4>
                <p class="mt-1 text-[10px] text-slate-400">INVITE headers for this direction.</p>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
                @click="addHeader(direction)"
              >
                <PlusIcon class="size-3.5" /> Add header
              </button>
            </div>
            <div
              v-for="(header, index) in headerRows(direction)"
              :key="`${direction}-${index}`"
              class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] gap-2"
            >
              <FormInput
                v-model="header.name"
                label="Header name"
                maxlength="128"
                input-class="font-mono"
                placeholder="X-Header"
                :aria-label="`${direction} SIP header name`"
                :error="error(`sip.custom_sip_headers.${direction}.${index}.name`)"
              />
              <FormInput
                v-model="header.value"
                label="Header value"
                maxlength="1024"
                placeholder="Value"
                :aria-label="`${direction} SIP header value`"
                :error="error(`sip.custom_sip_headers.${direction}.${index}.value`)"
              />
              <button
                type="button"
                class="grid size-10 place-items-center rounded-md border border-red-100 text-danger hover:bg-red-50"
                :aria-label="`Remove ${direction} SIP header`"
                @click="removeHeader(direction, index)"
              >
                <TrashIcon class="size-4" />
              </button>
            </div>
            <p v-if="headerRows(direction).length === 0" class="text-[11px] text-slate-400">
              No custom headers.
            </p>
          </section>
        </DisclosurePanel>
      </div>
    </Disclosure>

    <Disclosure v-slot="{ open }">
      <div class="rounded-md border border-slate-200">
        <DisclosureButton
          class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Dial plan
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-4 border-t border-slate-100 p-4">
          <FormInput
            v-model="systemDialPlans"
            label="System dial plans"
            placeholder="System plan names, comma separated"
            :error="error('dial_plan.system')"
          />
          <div class="flex items-center justify-between">
            <p class="text-[10px] text-slate-400">Regex rules modify locally dialed numbers.</p>
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
              @click="addDialPlanRule"
            >
              <PlusIcon class="size-3.5" /> Add rule
            </button>
          </div>
          <div
            v-for="(rule, index) in configuration.dial_plan.rules"
            :key="index"
            class="grid gap-2 rounded-md border border-slate-100 p-3 sm:grid-cols-2"
          >
            <FormInput
              v-model="rule.pattern"
              label="Regex pattern"
              maxlength="512"
              input-class="font-mono"
              placeholder="^([2-9][0-9]{6})$"
              class="sm:col-span-2"
              :error="error(`dial_plan.rules.${index}.pattern`)"
            />
            <FormInput
              v-model="rule.description"
              label="Description"
              maxlength="255"
              class="sm:col-span-2"
              :error="error(`dial_plan.rules.${index}.description`)"
            />
            <FormInput
              v-model="rule.prefix"
              label="Prefix"
              maxlength="64"
              :error="error(`dial_plan.rules.${index}.prefix`)"
            />
            <FormInput
              v-model="rule.suffix"
              label="Suffix"
              maxlength="64"
              :error="error(`dial_plan.rules.${index}.suffix`)"
            />
            <button
              type="button"
              class="inline-flex items-center justify-center gap-1 rounded-md border border-red-100 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50 sm:col-span-2"
              @click="removeDialPlanRule(index)"
            >
              <TrashIcon class="size-3.5" /> Remove rule
            </button>
          </div>
        </DisclosurePanel>
      </div>
    </Disclosure>

    <Disclosure v-slot="{ open }">
      <div class="rounded-md border border-slate-200">
        <DisclosureButton
          class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Metaflows and hotdesk
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-3">
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Binding digit</span>
            <FormListbox
              v-model="configuration.metaflows.binding_digit"
              :invalid="Boolean(error('metaflows.binding_digit'))"
              :options="
                ['*', '#', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'].map((value) => ({
                  value,
                  label: value,
                }))
              "
            />
          </label>
          <FormInput
            v-model.number="configuration.metaflows.digit_timeout"
            label="Digit timeout (ms)"
            type="number"
            min="0"
            max="60000"
            :error="error('metaflows.digit_timeout')"
          />
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Listen on</span>
            <FormListbox
              v-model="configuration.metaflows.listen_on"
              :invalid="Boolean(error('metaflows.listen_on'))"
              :options="[
                { value: 'both', label: 'Both call legs' },
                { value: 'self', label: 'This endpoint' },
                { value: 'peer', label: 'Peer endpoint' },
              ]"
            />
          </label>
          <div class="rounded-md bg-slate-50 p-3 text-[11px] text-slate-500 sm:col-span-3">
            Existing metaflow actions: {{ configuration.metaflows.number_flow_count }} number
            flow(s) and {{ configuration.metaflows.pattern_flow_count }} pattern flow(s).
          </div>
          <MetaflowSettings
            v-model="configuration.metaflows.actions"
            :field-errors="fieldErrors"
            :locked-action-count="configuration.metaflows.locked_action_count"
            :media-options="mediaOptions"
            :callflow-options="metaflowResources.callflows"
            :device-options="metaflowResources.devices"
            :extension-options="extensionOptions"
          />
          <div class="rounded-md bg-slate-50 p-3 text-[11px] text-slate-500 sm:col-span-3">
            Hotdesk users active on this device: {{ configuration.hotdesk.active_user_count }}.
            Manage active sign-ins from the saved Device detail page; Switch user identifiers are
            resolved server-side and never exposed here.
          </div>
        </DisclosurePanel>
      </div>
    </Disclosure>
  </section>
</template>
