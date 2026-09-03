<script setup lang="ts">
import { computed, useId } from 'vue'
import { validationControlClass } from '@/shared/forms/validationStyles'

const props = withDefaults(
  defineProps<{
    modelValue: boolean | string[]
    label: string
    description?: string | null
    error?: string | string[] | null
    value?: string
    id?: string
    ariaLabel?: string
    disabled?: boolean
    variant?: 'card' | 'row' | 'compact' | 'inline'
    hideLabel?: boolean
  }>(),
  {
    description: null,
    error: null,
    value: undefined,
    id: undefined,
    ariaLabel: undefined,
    disabled: false,
    variant: 'card',
    hideLabel: false,
  },
)
const emit = defineEmits<{ 'update:modelValue': [value: boolean | string[]] }>()
const generatedId = useId()
const controlId = computed(() => props.id ?? `form-checkbox-${generatedId}`)
const errorMessage = computed(() =>
  Array.isArray(props.error) ? (props.error[0] ?? null) : props.error,
)
const checked = computed(() =>
  Array.isArray(props.modelValue)
    ? props.value !== undefined && props.modelValue.includes(props.value)
    : props.modelValue,
)
const labelClass = computed(
  () =>
    ({
      card: 'rounded-md border border-slate-200 px-4 py-3 hover:bg-slate-50',
      row: 'px-4 py-3 hover:bg-slate-50',
      compact: 'rounded-md border border-slate-300 px-3 py-2 hover:bg-slate-50',
      inline: '',
    })[props.variant],
)

function update(event: Event): void {
  const next = (event.target as HTMLInputElement).checked
  if (!Array.isArray(props.modelValue)) {
    emit('update:modelValue', next)
    return
  }

  const values = [...props.modelValue]
  const index = props.value === undefined ? -1 : values.indexOf(props.value)
  if (next && props.value !== undefined && index < 0) values.push(props.value)
  if (!next && index >= 0) values.splice(index, 1)
  emit('update:modelValue', values)
}
</script>

<template>
  <div>
    <label
      :for="controlId"
      class="flex cursor-pointer items-start gap-3"
      :class="[
        labelClass,
        disabled && 'cursor-not-allowed opacity-60',
        !['row', 'inline'].includes(variant) && validationControlClass(errorMessage),
      ]"
    >
      <input
        :id="controlId"
        type="checkbox"
        :checked="checked"
        :disabled="disabled"
        :value="value"
        :aria-label="ariaLabel ?? label"
        :aria-invalid="Boolean(errorMessage) || undefined"
        class="mt-0.5 size-4 rounded border-slate-300 text-brand-600 accent-brand-500"
        @change="update"
      />
      <span class="min-w-0">
        <span class="block text-xs font-semibold text-slate-700" :class="hideLabel && 'sr-only'">{{
          label
        }}</span>
        <span v-if="description" class="mt-0.5 block text-[11px] leading-4 text-slate-500">
          {{ description }}
        </span>
      </span>
    </label>
    <p v-if="errorMessage" class="mt-1 text-[11px] leading-4 text-danger">
      {{ errorMessage }}
    </p>
  </div>
</template>
