<script setup lang="ts">
import { ArrowDownIcon, ArrowUpIcon } from '@heroicons/vue/20/solid'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { CallflowDestination } from '../types/callRouting'

const props = defineProps<{
  modelValue: string[]
  options: CallflowDestination[]
  error: string | null
}>()
const emit = defineEmits<{ 'update:modelValue': [ids: string[]] }>()

function move(index: number, offset: -1 | 1): void {
  const destination = index + offset
  if (destination < 0 || destination >= props.modelValue.length) return

  const ids = [...props.modelValue]
  const currentId = ids[index]
  const destinationId = ids[destination]
  if (currentId === undefined || destinationId === undefined) return

  ids[index] = destinationId
  ids[destination] = currentId
  emit('update:modelValue', ids)
}

function option(id: string): CallflowDestination | null {
  return props.options.find((candidate) => candidate.id === id) ?? null
}
</script>

<template>
  <div class="grid gap-4">
    <div
      class="overflow-hidden rounded-md border border-slate-200"
      :class="validationControlClass(error)"
      :aria-invalid="Boolean(error)"
    >
      <FormCheckbox
        v-for="rule in options"
        :key="rule.id"
        class="border-b border-slate-200 last:border-b-0"
        :model-value="modelValue"
        :value="rule.id"
        :label="rule.label"
        :aria-label="`Use Temporal Rule ${rule.label}`"
        :description="rule.detail"
        variant="row"
        @update:model-value="emit('update:modelValue', $event as string[])"
      />
      <p v-if="options.length === 0" class="p-5 text-xs text-slate-500">
        No projected Temporal Rules are available.
      </p>
    </div>

    <div v-if="modelValue.length" class="rounded-md border border-slate-200 bg-slate-50/60 p-4">
      <p class="text-xs font-semibold text-slate-700">Evaluation order</p>
      <p class="mt-1 text-[10px] text-slate-500">
        Switch evaluates direct rules from top to bottom.
      </p>
      <ol class="mt-3 grid gap-2">
        <li
          v-for="(id, index) in modelValue"
          :key="id"
          class="flex items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2"
        >
          <span
            class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-semibold text-brand-700"
          >
            {{ index + 1 }}
          </span>
          <span class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-700">
            {{ option(id)?.label ?? 'Unavailable Temporal Rule' }}
          </span>
          <button
            type="button"
            :aria-label="`Move ${option(id)?.label ?? 'Temporal Rule'} up`"
            :disabled="index === 0"
            class="grid size-7 place-items-center rounded border border-slate-200 text-slate-600 disabled:opacity-35"
            @click="move(index, -1)"
          >
            <ArrowUpIcon class="size-3.5" />
          </button>
          <button
            type="button"
            :aria-label="`Move ${option(id)?.label ?? 'Temporal Rule'} down`"
            :disabled="index === modelValue.length - 1"
            class="grid size-7 place-items-center rounded border border-slate-200 text-slate-600 disabled:opacity-35"
            @click="move(index, 1)"
          >
            <ArrowDownIcon class="size-3.5" />
          </button>
        </li>
      </ol>
    </div>

    <p v-if="error" class="text-[10px] text-danger">{{ error }}</p>
  </div>
</template>
