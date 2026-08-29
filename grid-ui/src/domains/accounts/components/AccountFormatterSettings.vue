<script setup lang="ts">
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import type { AccountFormatter } from '../types/account'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const formatters = defineModel<AccountFormatter[]>({ required: true })

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function addFormatter(): void {
  formatters.value.push({
    field: '',
    direction: 'both',
    match_invite_format: false,
    prefix: '',
    regex: '',
    strip: false,
    suffix: '',
    value: '',
  })
}
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div>
      <h3 class="text-xs font-semibold text-slate-700">Request formatters</h3>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Transform allowlisted Switch request fields without editing raw JSON.
      </p>
    </div>
    <button
      type="button"
      class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
      @click="addFormatter"
    >
      <PlusIcon class="size-3.5" /> Add formatter
    </button>
  </div>

  <p v-if="formatters.length === 0" class="rounded-md bg-slate-50 p-4 text-xs text-slate-500">
    No request formatters configured.
  </p>

  <section
    v-for="(formatter, index) in formatters"
    :key="index"
    class="grid gap-3 rounded-md border border-slate-200 p-4 sm:grid-cols-3"
  >
    <FormInput
      v-model="formatter.field"
      label="Switch field"
      class="sm:col-span-2"
      input-class="font-mono"
      placeholder="request"
      :error="error(`formatters.${index}.field`)"
    />
    <label class="grid gap-2">
      <span class="text-xs font-semibold text-slate-600">Direction</span>
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
      <span v-if="error(`formatters.${index}.direction`)" class="text-[10px] text-danger">
        {{ error(`formatters.${index}.direction`) }}
      </span>
    </label>
    <FormInput
      v-for="control in [
        { key: 'regex', label: 'Match regex', placeholder: '^(.*)$', mono: true },
        { key: 'value', label: 'Fixed value', placeholder: 'Optional replacement', mono: false },
        { key: 'prefix', label: 'Prefix', placeholder: '', mono: false },
        { key: 'suffix', label: 'Suffix', placeholder: '', mono: false },
      ] as const"
      :key="control.key"
      v-model="formatter[control.key]"
      :label="control.label"
      :input-class="control.mono ? 'font-mono' : ''"
      :placeholder="control.placeholder"
      :error="error(`formatters.${index}.${control.key}`)"
    />
    <div class="grid gap-2 sm:col-span-2 sm:grid-cols-2">
      <ToggleSwitch v-model="formatter.strip" label="Strip matched value" />
      <ToggleSwitch v-model="formatter.match_invite_format" label="Match INVITE format" />
    </div>
    <button
      type="button"
      class="inline-flex items-center justify-center gap-1 rounded-md border border-red-200 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50"
      @click="formatters.splice(index, 1)"
    >
      <TrashIcon class="size-3.5" /> Remove
    </button>
  </section>
</template>
