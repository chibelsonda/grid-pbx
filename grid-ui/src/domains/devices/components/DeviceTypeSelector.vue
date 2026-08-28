<script setup lang="ts">
import { DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'
import { deviceTypes } from '../deviceForm'
import type { DeviceType } from '../types/device'

const deviceType = defineModel<DeviceType>({ required: true })
const emit = defineEmits<{ select: [deviceType: DeviceType] }>()

function selectDeviceType(value: DeviceType): void {
  deviceType.value = value
  emit('select', value)
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
        <DevicePhoneMobileIcon class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Device type</h2>
        <p class="text-[10px] text-slate-400">
          The selected endpoint type controls the available tabs and configuration.
        </p>
      </div>
    </header>
    <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
      <button
        v-for="option in deviceTypes"
        :key="option.value"
        type="button"
        class="rounded-lg border p-4 text-left transition"
        :class="
          deviceType === option.value
            ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-100'
            : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'
        "
        :aria-pressed="deviceType === option.value"
        @click="selectDeviceType(option.value)"
      >
        <span class="flex items-start gap-3">
          <span
            class="grid size-9 shrink-0 place-items-center rounded-lg transition"
            :class="
              deviceType === option.value
                ? 'bg-brand-500 text-white shadow-sm'
                : 'bg-slate-100 text-slate-500'
            "
          >
            <component :is="option.icon" class="size-5" aria-hidden="true" />
          </span>
          <span class="min-w-0">
            <span class="block text-xs font-semibold text-slate-700">{{ option.label }}</span>
            <span class="mt-1 block text-[10px] leading-4 text-slate-400">
              {{ option.description }}
            </span>
          </span>
        </span>
      </button>
    </div>
  </article>
</template>
