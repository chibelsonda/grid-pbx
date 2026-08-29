<script setup lang="ts">
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { ExtensionCallRecording } from '../types/extension'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const recording = defineModel<ExtensionCallRecording>({ required: true })
const targets = [
  { value: 'account', label: 'Account branch' },
  { value: 'endpoint', label: 'Endpoint branch' },
] as const
const directions = [
  { value: 'any', label: 'Any direction' },
  { value: 'inbound', label: 'Inbound' },
  { value: 'outbound', label: 'Outbound' },
] as const
const networks = [
  { value: 'any', label: 'Any network' },
  { value: 'onnet', label: 'On-net' },
  { value: 'offnet', label: 'Off-net' },
] as const

function error(target: string, direction: string, network: string, field: string): string | null {
  return props.fieldErrors[`call_recording.${target}.${direction}.${network}.${field}`]?.[0] ?? null
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="border-b border-slate-200 px-5 py-4">
      <h2 class="text-sm font-semibold text-slate-700">User call recording</h2>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Switch recording rules are editable; storage URLs remain server-owned and hidden.
      </p>
    </header>
    <TabGroup>
      <TabList class="grid grid-cols-2 border-b border-slate-200 bg-slate-50 p-1">
        <Tab v-for="target in targets" :key="target.value" v-slot="{ selected }" as="template">
          <button
            type="button"
            class="rounded-md px-3 py-2 text-xs font-semibold outline-none"
            :class="selected ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-600'"
          >
            {{ target.label }}
          </button>
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="target in targets" :key="target.value" class="grid gap-4 p-5">
          <section
            v-for="direction in directions"
            :key="direction.value"
            class="grid gap-3 rounded-lg border border-slate-200 p-4"
          >
            <h3 class="text-xs font-semibold text-slate-700">{{ direction.label }}</h3>
            <div class="grid gap-3 xl:grid-cols-3">
              <div
                v-for="network in networks"
                :key="network.value"
                class="grid content-start gap-3 rounded-md border border-slate-200 bg-slate-50/70 p-3"
              >
                <ToggleSwitch
                  v-model="recording[target.value][direction.value][network.value].enabled"
                  :label="network.label"
                />
                <template v-if="recording[target.value][direction.value][network.value].enabled">
                  <FormListbox
                    v-model="recording[target.value][direction.value][network.value].format"
                    :options="[
                      { value: 'mp3', label: 'MP3' },
                      { value: 'wav', label: 'WAV' },
                    ]"
                    :invalid="Boolean(error(target.value, direction.value, network.value, 'format'))"
                  />
                  <label class="grid gap-1">
                    <span class="text-[10px] font-semibold text-slate-600">Minimum seconds</span>
                    <input
                      v-model.number="recording[target.value][direction.value][network.value].record_min_sec"
                      type="number"
                      min="0"
                      max="3600"
                      class="field-control"
                      :class="validationControlClass(error(target.value, direction.value, network.value, 'record_min_sec'))"
                    />
                  </label>
                  <label class="grid gap-1">
                    <span class="text-[10px] font-semibold text-slate-600">Time limit</span>
                    <input
                      v-model.number="recording[target.value][direction.value][network.value].time_limit"
                      type="number"
                      min="5"
                      max="10800"
                      class="field-control"
                      :class="validationControlClass(error(target.value, direction.value, network.value, 'time_limit'))"
                    />
                  </label>
                  <FormListbox
                    v-model="recording[target.value][direction.value][network.value].record_sample_rate"
                    :options="[
                      { value: null, label: 'Switch sample rate' },
                      { value: 8000, label: '8 kHz' },
                      { value: 16000, label: '16 kHz' },
                      { value: 32000, label: '32 kHz' },
                      { value: 48000, label: '48 kHz' },
                    ]"
                    :invalid="Boolean(error(target.value, direction.value, network.value, 'record_sample_rate'))"
                  />
                  <ToggleSwitch
                    v-model="recording[target.value][direction.value][network.value].record_on_answer"
                    label="Start on answer"
                  />
                  <ToggleSwitch
                    v-model="recording[target.value][direction.value][network.value].record_on_bridge"
                    label="Start on bridge"
                  />
                </template>
              </div>
            </div>
          </section>
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </article>
</template>
