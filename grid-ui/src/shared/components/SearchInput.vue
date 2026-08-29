<script setup lang="ts">
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import FormInput from './FormInput.vue'

withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    error?: string | string[] | null
    disabled?: boolean
    inputClass?: string
  }>(),
  {
    label: 'Search',
    placeholder: 'Search…',
    error: null,
    disabled: false,
    inputClass: '',
  },
)

defineEmits<{ 'update:modelValue': [value: string] }>()
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
    @update:model-value="$emit('update:modelValue', String($event))"
  >
    <template #leading>
      <MagnifyingGlassIcon class="size-4 text-slate-500" />
    </template>
  </FormInput>
</template>
