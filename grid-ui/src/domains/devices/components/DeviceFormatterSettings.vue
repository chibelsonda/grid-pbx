<script setup lang="ts">
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import type { DeviceFormatter } from '../types/device'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const formatters = defineModel<DeviceFormatter[]>({ required: true })

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
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
      <p class="mt-1 text-[10px] leading-4 text-heading-description">
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
    <FormInput
      v-model="formatter.field"
      label="Switch field"
      input-class="font-mono"
      placeholder="request"
      class="sm:col-span-2"
      :error="error(`formatters.${index}.field`)"
    />
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
    <FormInput
      v-model="formatter.regex"
      label="Match regex"
      input-class="font-mono"
      placeholder="^(.*)$"
      :error="error(`formatters.${index}.regex`)"
    />
    <FormInput
      v-model="formatter.value"
      label="Fixed value"
      placeholder="Optional replacement"
      :error="error(`formatters.${index}.value`)"
    />
    <FormInput
      v-model="formatter.prefix"
      label="Prefix"
      :error="error(`formatters.${index}.prefix`)"
    />
    <FormInput
      v-model="formatter.suffix"
      label="Suffix"
      :error="error(`formatters.${index}.suffix`)"
    />
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
