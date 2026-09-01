<script setup lang="ts">
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { onBeforeUnmount } from 'vue'
import FormInput from './FormInput.vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    error?: string | string[] | null
    disabled?: boolean
    inputClass?: string
    live?: boolean
    debounceMs?: number
  }>(),
  {
    label: 'Search',
    placeholder: 'Search…',
    error: null,
    disabled: false,
    inputClass: '',
    live: false,
    debounceMs: 300,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  search: [value: string]
}>()
let searchTimer: ReturnType<typeof window.setTimeout> | null = null

function update(value: string | number): void {
  const search = String(value)
  emit('update:modelValue', search)
  if (!props.live) return

  if (searchTimer !== null) window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    searchTimer = null
    emit('search', search)
  }, props.debounceMs)
}

onBeforeUnmount(() => {
  if (searchTimer !== null) window.clearTimeout(searchTimer)
})
</script>

<template>
  <FormInput
    :model-value="modelValue"
    :label="label"
    hide-label
    type="search"
    :placeholder="placeholder"
    :error="error"
    :disabled="disabled"
    :input-class="inputClass"
    @update:model-value="update"
  >
    <template #leading>
      <MagnifyingGlassIcon class="size-4 text-slate-500" />
    </template>
  </FormInput>
</template>
