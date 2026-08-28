<script setup lang="ts">
import { computed } from 'vue'
import {
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
  TransitionRoot,
} from '@headlessui/vue'
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid'
import { validationControlClass } from '@/shared/forms/validationStyles'

export type ListboxValue = string | number | boolean | null
export type ListboxOptionValue = {
  value: ListboxValue
  label: string
  description?: string | null
  disabled?: boolean
}

const props = withDefaults(
  defineProps<{
    modelValue: ListboxValue
    options: ListboxOptionValue[]
    ariaLabel?: string
    placeholder?: string
    disabled?: boolean
    invalid?: boolean
    size?: 'small' | 'medium'
    buttonClass?: string
  }>(),
  {
    ariaLabel: 'Select an option',
    placeholder: 'Select…',
    disabled: false,
    invalid: false,
    size: 'medium',
    buttonClass: '',
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: ListboxValue] }>()
const selected = computed(() => props.options.find((option) => option.value === props.modelValue))
</script>

<template>
  <Listbox
    :model-value="modelValue"
    :disabled="disabled"
    as="div"
    class="relative min-w-0"
    @update:model-value="emit('update:modelValue', $event as ListboxValue)"
  >
    <ListboxButton
      :aria-label="ariaLabel"
      :aria-invalid="invalid || undefined"
      class="relative w-full rounded-md border border-slate-200 bg-white pr-9 pl-3 text-left text-xs text-slate-700 shadow-sm transition hover:border-slate-300 focus:border-brand-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60"
      :class="[size === 'small' ? 'h-9' : 'h-10', buttonClass, validationControlClass(invalid)]"
    >
      <span class="block truncate">{{ selected?.label ?? placeholder }}</span>
      <ChevronUpDownIcon
        class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400"
        aria-hidden="true"
      />
    </ListboxButton>

    <TransitionRoot
      leave="transition ease-in duration-100"
      leave-from="opacity-100"
      leave-to="opacity-0"
    >
      <ListboxOptions
        class="absolute z-40 mt-1 max-h-60 w-full min-w-max overflow-auto rounded-md border border-slate-200 bg-white py-1 text-xs shadow-xl focus:outline-none"
      >
        <ListboxOption
          v-for="option in options"
          :key="`${typeof option.value}:${String(option.value)}`"
          v-slot="{ active, selected: isSelected, disabled: isDisabled }"
          :value="option.value"
          :disabled="option.disabled"
          as="template"
        >
          <li
            class="relative cursor-default py-2 pr-9 pl-3 select-none"
            :class="[
              active ? 'bg-brand-50 text-brand-700' : 'text-slate-700',
              isDisabled ? 'opacity-40' : 'cursor-pointer',
            ]"
          >
            <span class="block truncate" :class="isSelected ? 'font-semibold' : 'font-normal'">
              {{ option.label }}
            </span>
            <span
              v-if="option.description"
              class="mt-0.5 block truncate text-[10px] text-slate-400"
            >
              {{ option.description }}
            </span>
            <CheckIcon
              v-if="isSelected"
              class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-brand-600"
              aria-hidden="true"
            />
          </li>
        </ListboxOption>
      </ListboxOptions>
    </TransitionRoot>
  </Listbox>
</template>
