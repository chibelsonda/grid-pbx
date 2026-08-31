<script setup lang="ts">
import { ref } from 'vue'
import { BuildingOffice2Icon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import FormTabBar from '@/shared/components/FormTabBar.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import type { AccountCallRecording } from '../types/account'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const recording = defineModel<AccountCallRecording>({ required: true })
const selectedTarget = ref(0)
const targets = [
  {
    key: 'account',
    value: 'account',
    label: 'Account defaults',
    description: 'Rules applied at the account level.',
    icon: BuildingOffice2Icon,
  },
  {
    key: 'endpoint',
    value: 'endpoint',
    label: 'Endpoint defaults',
    description: 'Defaults inherited by compatible users and devices.',
    icon: DevicePhoneMobileIcon,
  },
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
      <h2 class="text-sm font-semibold text-slate-700">Call-recording defaults</h2>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Configure account and endpoint inheritance without exposing external storage URLs.
      </p>
    </header>

    <FormTabBar
      v-model="selectedTarget"
      :tabs="targets"
      aria-label="Call-recording targets"
      class="rounded-none border-x-0 border-t-0"
    />

    <div
      v-for="(target, index) in targets"
      v-show="selectedTarget === index"
      :key="target.value"
      role="tabpanel"
      class="grid gap-4 p-5"
    >
      <p class="text-[10px] text-slate-500">{{ target.description }}</p>
      <section
        v-for="direction in directions"
        :key="direction.value"
        class="grid gap-3 rounded-lg border border-slate-200 p-4"
      >
        <h3 class="text-xs font-semibold text-slate-700">{{ direction.label }}</h3>
        <div class="grid gap-3 xl:grid-cols-3">
          <article
            v-for="network in networks"
            :key="network.value"
            class="grid content-start gap-3 rounded-md border border-slate-200 bg-slate-50/70 p-3"
          >
            <ToggleSwitch
              v-model="recording[target.value][direction.value][network.value].enabled"
              :label="network.label"
            />
            <div
              v-if="recording[target.value][direction.value][network.value].enabled"
              class="grid gap-3 border-t border-slate-200 pt-3"
            >
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Format</span>
                <FormListbox
                  v-model="recording[target.value][direction.value][network.value].format"
                  :invalid="Boolean(error(target.value, direction.value, network.value, 'format'))"
                  :options="[
                    { value: 'mp3', label: 'MP3' },
                    { value: 'wav', label: 'WAV' },
                  ]"
                />
                <span
                  v-if="error(target.value, direction.value, network.value, 'format')"
                  class="text-[10px] text-danger"
                  >{{ error(target.value, direction.value, network.value, 'format') }}</span
                >
              </label>
              <FormInput
                v-model.number="
                  recording[target.value][direction.value][network.value].record_min_sec
                "
                label="Minimum seconds"
                type="number"
                min="0"
                max="3600"
                :error="error(target.value, direction.value, network.value, 'record_min_sec')"
              />
              <FormInput
                v-model.number="recording[target.value][direction.value][network.value].time_limit"
                label="Time limit"
                type="number"
                min="5"
                max="10800"
                :error="error(target.value, direction.value, network.value, 'time_limit')"
              />
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Sample rate</span>
                <FormListbox
                  v-model="
                    recording[target.value][direction.value][network.value].record_sample_rate
                  "
                  :invalid="
                    Boolean(
                      error(target.value, direction.value, network.value, 'record_sample_rate'),
                    )
                  "
                  :options="[
                    { value: null, label: 'Switch default' },
                    { value: 8000, label: '8 kHz' },
                    { value: 16000, label: '16 kHz' },
                    { value: 32000, label: '32 kHz' },
                    { value: 48000, label: '48 kHz' },
                  ]"
                />
                <span
                  v-if="error(target.value, direction.value, network.value, 'record_sample_rate')"
                  class="text-[10px] text-danger"
                  >{{
                    error(target.value, direction.value, network.value, 'record_sample_rate')
                  }}</span
                >
              </label>
              <ToggleSwitch
                v-model="recording[target.value][direction.value][network.value].record_on_answer"
                label="Start on answer"
              />
              <ToggleSwitch
                v-model="recording[target.value][direction.value][network.value].record_on_bridge"
                label="Start on bridge"
              />
            </div>
          </article>
        </div>
      </section>
    </div>
  </article>
</template>
