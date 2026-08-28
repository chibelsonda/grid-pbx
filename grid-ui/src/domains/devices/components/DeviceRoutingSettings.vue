<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import { useDelimitedStringList } from '@/shared/forms/useDelimitedStringList'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { DeviceConfiguration, DeviceSipHeader } from '../types/device'

const props = defineProps<{
  fieldErrors: Record<string, string[]>
  mediaOptions: Array<{ id: string; name: string | null }>
  supportsSip: boolean
}>()
const configuration = defineModel<DeviceConfiguration>({ required: true })
const headerDirections = ['in', 'out'] as const

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

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function invalidClass(field: string): string {
  return validationControlClass(error(field))
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
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Music on hold</span>
            <FormListbox
              v-model="configuration.music_on_hold.media_id"
              :invalid="Boolean(error('music_on_hold.media_id'))"
              :options="[
                { value: null, label: 'Inherit account music' },
                ...mediaOptions.map((media) => ({
                  value: media.id,
                  label: media.name || 'Untitled media',
                })),
              ]"
            />
            <span v-if="error('music_on_hold.media_id')" class="text-[11px] text-danger">
              {{ error('music_on_hold.media_id') }}
            </span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Static outbound flags</span>
            <textarea
              v-model="staticFlags"
              rows="2"
              class="field-control min-h-20 py-2"
              :class="invalidClass('outbound_flags.static')"
              placeholder="fax, trusted"
            />
            <span class="text-[10px] text-slate-400">Separate flags with commas or new lines.</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Dynamic outbound flags</span>
            <textarea
              v-model="dynamicFlags"
              rows="2"
              class="field-control min-h-20 py-2"
              :class="invalidClass('outbound_flags.dynamic')"
              placeholder="Optional runtime flags"
            />
            <span class="text-[10px] text-slate-400">Resolved by the Switch at call time.</span>
          </label>
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
              <input
                v-model="header.name"
                maxlength="128"
                class="field-control font-mono"
                :class="invalidClass(`sip.custom_sip_headers.${direction}.${index}.name`)"
                placeholder="X-Header"
                :aria-label="`${direction} SIP header name`"
              />
              <input
                v-model="header.value"
                maxlength="1024"
                class="field-control"
                :class="invalidClass(`sip.custom_sip_headers.${direction}.${index}.value`)"
                placeholder="Value"
                :aria-label="`${direction} SIP header value`"
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
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">System dial plans</span>
            <input
              v-model="systemDialPlans"
              class="field-control"
              :class="invalidClass('dial_plan.system')"
              placeholder="System plan names, comma separated"
            />
          </label>
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
            <label class="grid gap-1 sm:col-span-2">
              <span class="text-[11px] font-semibold text-slate-500">Regex pattern</span>
              <input
                v-model="rule.pattern"
                maxlength="512"
                class="field-control font-mono"
                :class="invalidClass(`dial_plan.rules.${index}.pattern`)"
                placeholder="^([2-9][0-9]{6})$"
              />
            </label>
            <label class="grid gap-1 sm:col-span-2">
              <span class="text-[11px] font-semibold text-slate-500">Description</span>
              <input v-model="rule.description" maxlength="255" class="field-control" />
            </label>
            <label class="grid gap-1">
              <span class="text-[11px] font-semibold text-slate-500">Prefix</span>
              <input v-model="rule.prefix" maxlength="64" class="field-control" />
            </label>
            <label class="grid gap-1">
              <span class="text-[11px] font-semibold text-slate-500">Suffix</span>
              <input v-model="rule.suffix" maxlength="64" class="field-control" />
            </label>
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
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Digit timeout (ms)</span>
            <input
              v-model.number="configuration.metaflows.digit_timeout"
              type="number"
              min="0"
              max="60000"
              class="field-control"
              :class="invalidClass('metaflows.digit_timeout')"
            />
          </label>
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
            flow(s) and {{ configuration.metaflows.pattern_flow_count }} pattern flow(s). Action
            editing belongs in the visual callflow editor and is not exposed as raw JSON here.
          </div>
          <div class="rounded-md bg-slate-50 p-3 text-[11px] text-slate-500 sm:col-span-3">
            Hotdesk users active on this device: {{ configuration.hotdesk.active_user_count }}.
            Membership is managed from People & Extensions so user resource identifiers are never
            exposed by this form.
          </div>
        </DisclosurePanel>
      </div>
    </Disclosure>
  </section>
</template>
