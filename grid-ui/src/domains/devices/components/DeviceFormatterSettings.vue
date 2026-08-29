<script setup lang="ts">
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { DeviceFormatter } from '../types/device'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const formatters = defineModel<DeviceFormatter[]>({ required: true })

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function invalidClass(field: string): string {
  return validationControlClass(error(field))
}

function addFormatter(): void {
  formatters.value.push({
    field: '',
    direction: 'both',
    match_invite_format: false,
    prefix: null,
    regex: null,
    strip: false,
    suffix: null,
    value: null,
  })
}
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div>
      <h4 class="text-xs font-semibold text-slate-700">SIP field formatters</h4>
      <p class="mt-1 text-[10px] leading-4 text-slate-400">
        Transform supported Switch fields without exposing raw JSON.
      </p>
    </div>
    <button
      type="button"
      class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
      @click="addFormatter"
    >
      <PlusIcon class="size-3.5" /> Add formatter
    </button>
  </div>

  <p v-if="formatters.length === 0" class="rounded-md bg-slate-50 p-3 text-[11px] text-slate-400">
    No field formatters configured.
  </p>

  <section
    v-for="(formatter, index) in formatters"
    :key="index"
    class="grid gap-3 rounded-md border border-slate-100 p-3 sm:grid-cols-3"
  >
    <label class="grid gap-1 sm:col-span-2">
      <span class="text-[11px] font-semibold text-slate-500">Switch field</span>
      <input
        v-model="formatter.field"
        class="field-control font-mono"
        :class="invalidClass(`formatters.${index}.field`)"
        :aria-invalid="Boolean(error(`formatters.${index}.field`))"
        placeholder="request"
      />
      <span v-if="error(`formatters.${index}.field`)" class="text-[10px] text-danger">
        {{ error(`formatters.${index}.field`) }}
      </span>
    </label>
    <label class="grid gap-1">
      <span class="text-[11px] font-semibold text-slate-500">Direction</span>
      <FormListbox
        v-model="formatter.direction"
        :invalid="Boolean(error(`formatters.${index}.direction`))"
        :options="[
          { value: null, label: 'Switch default' },
          { value: 'both', label: 'Both directions' },
          { value: 'inbound', label: 'Inbound' },
          { value: 'outbound', label: 'Outbound' },
        ]"
      />
    </label>
    <label class="grid gap-1">
      <span class="text-[11px] font-semibold text-slate-500">Match regex</span>
      <input v-model="formatter.regex" class="field-control font-mono" placeholder="^(.*)$" />
    </label>
    <label class="grid gap-1">
      <span class="text-[11px] font-semibold text-slate-500">Fixed value</span>
      <input v-model="formatter.value" class="field-control" placeholder="Optional replacement" />
    </label>
    <label class="grid gap-1">
      <span class="text-[11px] font-semibold text-slate-500">Prefix</span>
      <input v-model="formatter.prefix" class="field-control" />
    </label>
    <label class="grid gap-1">
      <span class="text-[11px] font-semibold text-slate-500">Suffix</span>
      <input v-model="formatter.suffix" class="field-control" />
    </label>
    <div class="grid gap-2 sm:col-span-2 sm:grid-cols-2">
      <ToggleSwitch v-model="formatter.strip" label="Strip matched value" />
      <ToggleSwitch v-model="formatter.match_invite_format" label="Match INVITE format" />
    </div>
    <button
      type="button"
      class="inline-flex items-center justify-center gap-1 rounded-md border border-red-100 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50"
      @click="formatters.splice(index, 1)"
    >
      <TrashIcon class="size-3.5" /> Remove
    </button>
  </section>
</template>
