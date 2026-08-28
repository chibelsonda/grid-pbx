<script setup lang="ts">
import FormListbox from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { DeviceConfiguration } from '../types/device'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const recording = defineModel<DeviceConfiguration['call_recording']>({ required: true })
const directions = [
  { value: 'any', label: 'Any direction', description: 'Fallback for inbound and outbound calls' },
  { value: 'inbound', label: 'Inbound', description: 'Calls arriving at this device' },
  { value: 'outbound', label: 'Outbound', description: 'Calls originating from this device' },
] as const
const networks = [
  { value: 'any', label: 'Any network', description: 'Fallback for on-net and off-net calls' },
  { value: 'onnet', label: 'On-net', description: 'Calls within the Switch deployment' },
  { value: 'offnet', label: 'Off-net', description: 'Calls using external resources or carriers' },
] as const

function error(direction: string, network: string, field: string): string | null {
  return props.fieldErrors[`call_recording.${direction}.${network}.${field}`]?.[0] ?? null
}

function invalidClass(direction: string, network: string, field: string): string {
  return validationControlClass(error(direction, network, field))
}
</script>

<template>
  <div class="grid gap-5">
    <section
      v-for="direction in directions"
      :key="direction.value"
      class="grid gap-4 rounded-lg border border-slate-200 p-4"
    >
      <div>
        <h3 class="text-xs font-semibold text-slate-700">{{ direction.label }}</h3>
        <p class="mt-1 text-[10px] text-slate-400">{{ direction.description }}</p>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <article
          v-for="network in networks"
          :key="network.value"
          class="grid content-start gap-4 rounded-lg border border-slate-100 bg-slate-50/60 p-4"
        >
          <ToggleSwitch
            v-model="recording[direction.value][network.value].enabled"
            :label="network.label"
            :description="network.description"
          />

          <div
            v-if="recording[direction.value][network.value].enabled"
            class="grid gap-4 border-t border-slate-200 pt-4"
          >
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Format</span>
              <FormListbox
                v-model="recording[direction.value][network.value].format"
                :invalid="Boolean(error(direction.value, network.value, 'format'))"
                :options="[
                  { value: 'mp3', label: 'MP3' },
                  { value: 'wav', label: 'WAV' },
                ]"
              />
              <span
                v-if="error(direction.value, network.value, 'format')"
                class="text-[10px] text-danger"
                >{{ error(direction.value, network.value, 'format') }}</span
              >
            </label>

            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Minimum duration</span>
              <input
                v-model.number="recording[direction.value][network.value].record_min_sec"
                type="number"
                min="0"
                max="3600"
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                :class="invalidClass(direction.value, network.value, 'record_min_sec')"
                :aria-invalid="Boolean(error(direction.value, network.value, 'record_min_sec'))"
                placeholder="Seconds"
              />
              <span
                v-if="error(direction.value, network.value, 'record_min_sec')"
                class="text-[10px] text-danger"
                >{{ error(direction.value, network.value, 'record_min_sec') }}</span
              >
            </label>

            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Time limit</span>
              <input
                v-model.number="recording[direction.value][network.value].time_limit"
                type="number"
                min="5"
                max="10800"
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                :class="invalidClass(direction.value, network.value, 'time_limit')"
                :aria-invalid="Boolean(error(direction.value, network.value, 'time_limit'))"
                placeholder="Seconds"
              />
              <span
                v-if="error(direction.value, network.value, 'time_limit')"
                class="text-[10px] text-danger"
                >{{ error(direction.value, network.value, 'time_limit') }}</span
              >
            </label>

            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Sample rate</span>
              <FormListbox
                v-model="recording[direction.value][network.value].record_sample_rate"
                :invalid="Boolean(error(direction.value, network.value, 'record_sample_rate'))"
                placeholder="Switch default"
                :options="[
                  { value: null, label: 'Switch default' },
                  { value: 8000, label: '8 kHz' },
                  { value: 16000, label: '16 kHz' },
                  { value: 32000, label: '32 kHz' },
                  { value: 48000, label: '48 kHz' },
                ]"
              />
              <span
                v-if="error(direction.value, network.value, 'record_sample_rate')"
                class="text-[10px] text-danger"
                >{{ error(direction.value, network.value, 'record_sample_rate') }}</span
              >
            </label>

            <ToggleSwitch
              v-model="recording[direction.value][network.value].record_on_answer"
              label="Start on answer"
            />
            <ToggleSwitch
              v-model="recording[direction.value][network.value].record_on_bridge"
              label="Start on bridge"
            />
          </div>
        </article>
      </div>
    </section>

    <p
      class="rounded-md border border-amber-100 bg-amber-50 px-4 py-3 text-[10px] leading-4 text-amber-700"
    >
      External recording storage URLs remain unavailable until the administrator SSRF and credential
      policy is configured.
    </p>
  </div>
</template>
