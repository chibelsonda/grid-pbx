<script setup lang="ts">
import { Switch, SwitchDescription, SwitchGroup, SwitchLabel } from '@headlessui/vue'

withDefaults(
  defineProps<{
    modelValue: boolean
    label: string
    description?: string
    disabled?: boolean
  }>(),
  { description: '', disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
  <SwitchGroup as="div" class="flex items-center justify-between gap-4">
    <span class="min-w-0">
      <SwitchLabel as="span" class="block cursor-pointer text-xs font-semibold text-slate-600">
        {{ label }}
      </SwitchLabel>
      <SwitchDescription
        v-if="description"
        as="span"
        class="mt-0.5 block text-[10px] leading-4 text-slate-400"
      >
        {{ description }}
      </SwitchDescription>
    </span>
    <Switch
      :model-value="modelValue"
      :disabled="disabled"
      class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
      :class="modelValue ? 'bg-brand-500' : 'bg-slate-200'"
      @update:model-value="emit('update:modelValue', $event)"
    >
      <span class="sr-only">{{ label }}</span>
      <span
        aria-hidden="true"
        class="pointer-events-none inline-block size-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
        :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
      />
    </Switch>
  </SwitchGroup>
</template>
