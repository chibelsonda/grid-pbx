<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, type CSSProperties } from 'vue'
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
const root = ref<HTMLElement | null>(null)
const optionsStyle = ref<CSSProperties>({ visibility: 'hidden' })
let isPositioning = false

function positionOptions(): void {
  const trigger = root.value?.querySelector<HTMLElement>('[data-form-listbox-trigger]')

  if (!trigger) {
    return
  }

  const viewportPadding = 8
  const menuGap = 4
  const preferredMaxHeight = 240
  const minimumUsefulHeight = 120
  const triggerRect = trigger.getBoundingClientRect()
  const roomBelow = window.innerHeight - triggerRect.bottom - menuGap - viewportPadding
  const roomAbove = triggerRect.top - menuGap - viewportPadding
  const openAbove = roomBelow < minimumUsefulHeight && roomAbove > roomBelow
  const availableHeight = Math.max(
    96,
    Math.min(preferredMaxHeight, openAbove ? roomAbove : roomBelow),
  )
  const width = Math.min(Math.max(triggerRect.width, 192), window.innerWidth - viewportPadding * 2)
  const left = Math.min(
    Math.max(triggerRect.left, viewportPadding),
    Math.max(viewportPadding, window.innerWidth - width - viewportPadding),
  )

  optionsStyle.value = {
    bottom: openAbove ? `${window.innerHeight - triggerRect.top + menuGap}px` : 'auto',
    left: `${left}px`,
    maxHeight: `${availableHeight}px`,
    top: openAbove ? 'auto' : `${triggerRect.bottom + menuGap}px`,
    visibility: 'visible',
    width: `${width}px`,
  }
}

function startPositioning(): void {
  if (!isPositioning) {
    window.addEventListener('resize', positionOptions)
    window.addEventListener('scroll', positionOptions, true)
    isPositioning = true
  }

  void nextTick(positionOptions)
}

function stopPositioning(): void {
  if (isPositioning) {
    window.removeEventListener('resize', positionOptions)
    window.removeEventListener('scroll', positionOptions, true)
    isPositioning = false
  }

  optionsStyle.value = { visibility: 'hidden' }
}

function updateValue(value: ListboxValue): void {
  stopPositioning()
  emit('update:modelValue', value)
}

onBeforeUnmount(stopPositioning)
</script>

<template>
  <div ref="root" class="relative min-w-0">
    <Listbox
      :model-value="modelValue"
      :disabled="disabled"
      as="div"
      class="contents"
      @update:model-value="updateValue($event as ListboxValue)"
    >
      <ListboxButton
        data-form-listbox-trigger
        :aria-label="ariaLabel"
        :aria-invalid="invalid || undefined"
        class="relative w-full rounded-md border border-slate-300 bg-white pr-9 pl-3 text-left text-xs text-slate-700 shadow-sm transition hover:border-slate-300 focus:border-brand-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60"
        :class="[size === 'small' ? 'h-9' : 'h-10', buttonClass, validationControlClass(invalid)]"
        @click="startPositioning"
        @keydown="startPositioning"
      >
        <span class="block truncate">{{ selected?.label ?? placeholder }}</span>
        <ChevronUpDownIcon
          class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500"
          aria-hidden="true"
        />
      </ListboxButton>

      <Teleport to="body">
        <TransitionRoot
          leave="transition ease-in duration-100"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <ListboxOptions
            :style="optionsStyle"
            class="fixed z-[100] overflow-auto rounded-md border border-slate-300 bg-white py-1 text-xs shadow-2xl ring-1 ring-slate-900/5 focus:outline-none"
            @vue:mounted="startPositioning"
            @vue:unmounted="stopPositioning"
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
                  class="mt-0.5 block truncate text-[11px] text-slate-500"
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
      </Teleport>
    </Listbox>
  </div>
</template>
