<script setup lang="ts">
import { ref } from 'vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    label: string
    name: string
    autocomplete: string
    error?: string | string[] | null
    description?: string | null
    required?: boolean
  }>(),
  {
    error: null,
    description: null,
    required: false,
  },
)

const model = defineModel<string>({ required: true })
const visible = ref(false)
</script>

<template>
  <FormInput
    v-bind="$attrs"
    v-model="model"
    :label="props.label"
    :type="visible ? 'text' : 'password'"
    :name="props.name"
    :autocomplete="props.autocomplete"
    :required="props.required"
    :error="props.error"
    :description="props.description"
  >
    <template #trailing>
      <button
        type="button"
        class="grid size-9 place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500"
        :aria-label="
          visible ? `Hide ${props.label.toLowerCase()}` : `Show ${props.label.toLowerCase()}`
        "
        :aria-pressed="visible"
        @click="visible = !visible"
      >
        <EyeSlashIcon v-if="visible" class="size-5" aria-hidden="true" />
        <EyeIcon v-else class="size-5" aria-hidden="true" />
      </button>
    </template>
  </FormInput>
</template>
