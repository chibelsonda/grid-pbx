<script setup lang="ts">
import { computed, useId } from 'vue'
import { validationControlClass } from '@/shared/forms/validationStyles'

const props = withDefaults(
  defineProps<{
    modelValue: File | null
    label: string
    description?: string | null
    error?: string | string[] | null
    id?: string
    ariaLabel?: string
    accept?: string
    required?: boolean
    disabled?: boolean
  }>(),
  {
    description: null,
    error: null,
    id: undefined,
    ariaLabel: undefined,
    accept: undefined,
    required: false,
    disabled: false,
  },
)
const emit = defineEmits<{
  'update:modelValue': [value: File | null]
  change: [value: File | null]
}>()
const generatedId = useId()
const controlId = computed(() => props.id ?? `form-file-${generatedId}`)
const errorMessage = computed(() =>
  Array.isArray(props.error) ? (props.error[0] ?? null) : props.error,
)

function update(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  emit('update:modelValue', file)
  emit('change', file)
}
</script>

<template>
  <div class="grid content-start gap-2">
    <label :for="controlId" class="text-xs font-semibold text-slate-600">
      {{ label }} <span v-if="required" aria-hidden="true" class="text-danger">*</span>
    </label>
    <input
      :id="controlId"
      type="file"
      :accept="accept"
      :required="required"
      :disabled="disabled"
      :aria-label="ariaLabel ?? label"
      :aria-invalid="Boolean(errorMessage) || undefined"
      class="w-full rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white"
      :class="validationControlClass(errorMessage)"
      @change="update"
    />
    <p v-if="description" class="text-[10px] leading-4 text-slate-500">{{ description }}</p>
    <p v-if="errorMessage" class="text-[10px] leading-4 text-danger">{{ errorMessage }}</p>
  </div>
</template>
