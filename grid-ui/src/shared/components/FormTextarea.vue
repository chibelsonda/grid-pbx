<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue'
import { validationControlClass } from '@/shared/forms/validationStyles'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string | null
    label: string
    description?: string | null
    error?: string | string[] | null
    id?: string
    textareaClass?: string
    size?: 'compact' | 'medium' | 'large'
    disabled?: boolean
    required?: boolean
    invalid?: boolean
    modelModifiers?: { trim?: boolean }
  }>(),
  {
    description: null,
    error: null,
    id: undefined,
    textareaClass: '',
    size: 'medium',
    disabled: false,
    required: false,
    invalid: false,
    modelModifiers: () => ({}),
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [value: string]
}>()
const attrs = useAttrs()
const generatedId = useId()
const controlId = computed(() => props.id ?? `form-textarea-${generatedId}`)
const descriptionId = computed(() => `${controlId.value}-description`)
const errorId = computed(() => `${controlId.value}-error`)
const errorMessage = computed(() =>
  Array.isArray(props.error) ? (props.error[0] ?? null) : props.error,
)
const isInvalid = computed(
  () =>
    props.invalid ||
    Boolean(errorMessage.value) ||
    attrs['aria-invalid'] === true ||
    attrs['aria-invalid'] === 'true',
)
const rootClass = computed(() => attrs.class)
const controlAttrs = computed(() =>
  Object.fromEntries(
    Object.entries(attrs).filter(
      ([key]) => !['class', 'aria-describedby', 'aria-invalid'].includes(key),
    ),
  ),
)
const describedBy = computed(
  () =>
    [
      attrs['aria-describedby'],
      props.description ? descriptionId.value : null,
      errorMessage.value ? errorId.value : null,
    ]
      .filter(Boolean)
      .join(' ') || undefined,
)

function valueFrom(event: Event): string {
  const value = (event.target as HTMLTextAreaElement).value

  return props.modelModifiers.trim ? value.trim() : value
}

function update(event: Event): void {
  emit('update:modelValue', valueFrom(event))
}

function change(event: Event): void {
  emit('change', valueFrom(event))
}
</script>

<template>
  <div class="grid content-start gap-2" :class="rootClass">
    <label :for="controlId" class="text-xs font-semibold text-slate-600">
      {{ label }}
      <span v-if="required" aria-hidden="true" class="text-danger">*</span>
    </label>
    <textarea
      v-bind="controlAttrs"
      :id="controlId"
      :value="modelValue ?? ''"
      :disabled="disabled"
      :required="required"
      :aria-label="String(attrs['aria-label'] ?? label)"
      :aria-describedby="describedBy"
      :aria-invalid="isInvalid || undefined"
      class="field-control py-2"
      :class="[
        size === 'compact' ? 'min-h-10' : size === 'large' ? 'min-h-48' : 'min-h-24',
        textareaClass,
        validationControlClass(isInvalid),
      ]"
      @input="update"
      @change="change"
    />
    <p v-if="description" :id="descriptionId" class="text-[10px] leading-4 text-slate-500">
      {{ description }}
    </p>
    <p v-if="errorMessage" :id="errorId" class="text-[10px] leading-4 text-danger">
      {{ errorMessage }}
    </p>
  </div>
</template>
