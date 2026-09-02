<script setup lang="ts">
import { ref } from 'vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import FormInput from './FormInput.vue'

defineOptions({ inheritAttrs: false })

withDefaults(
  defineProps<{
    modelValue: string
    label: string
    description?: string | null
    error?: string | string[] | null
    inputClass?: string
    disabled?: boolean
    required?: boolean
  }>(),
  {
    description: null,
    error: null,
    inputClass: '',
    disabled: false,
    required: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
const passwordVisible = ref(false)

function update(value: string | number): void {
  emit('update:modelValue', String(value))
}
</script>

<template>
  <FormInput
    v-bind="$attrs"
    :model-value="modelValue"
    :label="label"
    :description="description"
    :error="error"
    :input-class="inputClass"
    :disabled="disabled"
    :required="required"
    :type="passwordVisible ? 'text' : 'password'"
    @update:model-value="update"
  >
    <template #trailing>
      <button
        type="button"
        :disabled="disabled"
        :aria-label="
          passwordVisible ? `Hide ${label.toLowerCase()}` : `Show ${label.toLowerCase()}`
        "
        :aria-pressed="passwordVisible"
        class="grid size-9 place-items-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50"
        @mousedown.prevent
        @click="passwordVisible = !passwordVisible"
      >
        <EyeSlashIcon v-if="passwordVisible" class="size-4.5" aria-hidden="true" />
        <EyeIcon v-else class="size-4.5" aria-hidden="true" />
      </button>
    </template>
  </FormInput>
</template>
