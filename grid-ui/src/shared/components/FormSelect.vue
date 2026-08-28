<script setup lang="ts">
import { Comment, computed, Fragment, Text, useAttrs, useSlots, type VNode } from 'vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'

defineOptions({ inheritAttrs: false })

withDefaults(
  defineProps<{
    modelValue: ListboxValue
    disabled?: boolean
    required?: boolean
  }>(),
  { disabled: false, required: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: ListboxValue]
  change: [value: ListboxValue]
}>()
const slots = useSlots()
const attrs = useAttrs()

function textOf(children: VNode['children']): string {
  if (typeof children === 'string' || typeof children === 'number') return String(children).trim()
  if (!Array.isArray(children)) return ''

  return children
    .map((child) => {
      if (typeof child === 'string' || typeof child === 'number') return String(child)
      return textOf((child as VNode).children)
    })
    .join('')
    .trim()
}

function collect(nodes: VNode[], result: ListboxOptionValue[] = []): ListboxOptionValue[] {
  for (const node of nodes) {
    if (node.type === Comment || node.type === Text) continue
    if (node.type === Fragment && Array.isArray(node.children)) {
      collect(node.children as VNode[], result)
      continue
    }
    if (node.type === 'option') {
      result.push({
        value: (node.props?.value ?? null) as ListboxValue,
        label: textOf(node.children),
        disabled: Boolean(node.props?.disabled),
      })
      continue
    }
    if (Array.isArray(node.children)) collect(node.children as VNode[], result)
  }

  return result
}

const options = computed(() => collect(slots.default?.() ?? []))
const buttonClass = computed(() => String(attrs.class ?? ''))
const ariaLabel = computed(() => String(attrs['aria-label'] ?? 'Select an option'))
const invalid = computed(() => attrs['aria-invalid'] === true || attrs['aria-invalid'] === 'true')

function update(value: ListboxValue): void {
  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<template>
  <FormListbox
    :model-value="modelValue"
    :options="options"
    :disabled="disabled"
    :invalid="invalid"
    :aria-label="ariaLabel"
    :button-class="buttonClass"
    :aria-required="required || undefined"
    @update:model-value="update"
  />
</template>
