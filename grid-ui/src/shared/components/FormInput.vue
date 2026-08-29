<script setup lang="ts">
import { computed, useAttrs, useId, useSlots } from 'vue'
import { validationControlClass } from '@/shared/forms/validationStyles'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string | number | null
    label: string
    description?: string | null
    error?: string | string[] | null
    id?: string
    inputClass?: string
    hideLabel?: boolean
    disabled?: boolean
    required?: boolean
    invalid?: boolean
    modelModifiers?: { number?: boolean; trim?: boolean }
  }>(),
  {
    description: null,
    error: null,
    id: undefined,
    inputClass: '',
    hideLabel: false,
    disabled: false,
    required: false,
    invalid: false,
    modelModifiers: () => ({}),
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
  change: [value: string | number]
}>()
const attrs = useAttrs()
const slots = useSlots()
const generatedId = useId()
const controlId = computed(() => props.id ?? `form-input-${generatedId}`)
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

function valueFrom(event: Event): string | number {
  let value: string | number = (event.target as HTMLInputElement).value

  if (props.modelModifiers.trim) value = value.trim()
  if (props.modelModifiers.number && value !== '') {
    const number = Number(value)
    if (!Number.isNaN(number)) value = number
  }

  return value
}

function update(event: Event): void {
  emit('update:modelValue', valueFrom(event))
}

function change(event: Event): void {
  emit('change', valueFrom(event))
}
</script>

<template>
  <div class="grid gap-2" :class="rootClass">
    <label
      :for="controlId"
      class="text-xs font-semibold text-slate-600"
      :class="hideLabel && 'sr-only'"
    >
      {{ label }}
      <span v-if="required" aria-hidden="true" class="text-danger">*</span>
    </label>
    <div class="relative">
      <input
        v-bind="controlAttrs"
        :id="controlId"
        :value="modelValue ?? ''"
        :disabled="disabled"
        :required="required"
        :aria-label="String(attrs['aria-label'] ?? label)"
        :aria-describedby="describedBy"
        :aria-invalid="isInvalid || undefined"
        class="field-control"
        :class="[
          slots.leading && 'pl-9',
          slots.trailing && 'pr-10',
          inputClass,
          validationControlClass(isInvalid),
        ]"
        @input="update"
        @change="change"
      />
      <div
        v-if="slots.leading"
        class="pointer-events-none absolute inset-y-0 left-3 flex items-center"
      >
        <slot name="leading" />
      </div>
      <div v-if="slots.trailing" class="absolute inset-y-0 right-1 flex items-center">
        <slot name="trailing" />
      </div>
    </div>
    <p v-if="description" :id="descriptionId" class="text-[10px] leading-4 text-slate-500">
      {{ description }}
    </p>
    <p v-if="errorMessage" :id="errorId" class="text-[10px] leading-4 text-danger">
      {{ errorMessage }}
    </p>
  </div>
</template>
