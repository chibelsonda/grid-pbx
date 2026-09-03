<script setup lang="ts">
import { ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import { computed, ref, useId } from 'vue'
import { validationControlClass } from '@/shared/forms/validationStyles'

const props = withDefaults(
  defineProps<{
    modelValue: File | null
    label: string
    description?: string | null
    error?: string | string[] | null
    id?: string
    ariaLabel?: string
    accept?: string
    required?: boolean
    disabled?: boolean
    dropzone?: boolean
    dropPrompt?: string
  }>(),
  {
    description: null,
    error: null,
    id: undefined,
    ariaLabel: undefined,
    accept: undefined,
    required: false,
    disabled: false,
    dropzone: false,
    dropPrompt: 'Drag and drop a file here',
  },
)
const emit = defineEmits<{
  'update:modelValue': [value: File | null]
  change: [value: File | null]
}>()
const generatedId = useId()
const controlId = computed(() => props.id ?? `form-file-${generatedId}`)
const descriptionId = computed(() => `${controlId.value}-description`)
const errorId = computed(() => `${controlId.value}-error`)
const errorMessage = computed(() =>
  Array.isArray(props.error) ? (props.error[0] ?? null) : props.error,
)
const describedBy = computed(
  () =>
    [props.description ? descriptionId.value : null, errorMessage.value ? errorId.value : null]
      .filter(Boolean)
      .join(' ') || undefined,
)
const dragging = ref(false)

function selectFile(file: File | null): void {
  emit('update:modelValue', file)
  emit('change', file)
}

function update(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  selectFile(file)
}

function enterDropzone(): void {
  if (!props.disabled) dragging.value = true
}

function leaveDropzone(event: DragEvent): void {
  const dropzone = event.currentTarget as HTMLElement
  if (!event.relatedTarget || !dropzone.contains(event.relatedTarget as Node)) {
    dragging.value = false
  }
}

function drop(event: DragEvent): void {
  dragging.value = false
  if (props.disabled) return

  selectFile(event.dataTransfer?.files.item(0) ?? null)
}
</script>

<template>
  <div class="grid content-start gap-2">
    <label :for="controlId" class="text-xs font-semibold text-slate-600">
      {{ label }} <span v-if="required" aria-hidden="true" class="text-danger">*</span>
    </label>
    <div
      v-if="dropzone"
      data-testid="file-dropzone"
      class="relative grid min-h-36 place-items-center rounded-md border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center transition focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-100"
      :class="[
        validationControlClass(errorMessage),
        dragging ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-100' : '',
        disabled ? 'cursor-not-allowed opacity-60' : 'hover:border-brand-300 hover:bg-brand-50/50',
      ]"
      @dragenter.prevent="enterDropzone"
      @dragover.prevent="enterDropzone"
      @dragleave="leaveDropzone"
      @drop.prevent="drop"
    >
      <input
        :id="controlId"
        type="file"
        :accept="accept"
        :required="required && !modelValue"
        :disabled="disabled"
        :aria-label="ariaLabel ?? label"
        :aria-required="required || undefined"
        :aria-invalid="Boolean(errorMessage) || undefined"
        :aria-describedby="describedBy"
        class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
        @change="update"
      />
      <div class="pointer-events-none grid justify-items-center gap-2">
        <ArrowUpTrayIcon class="size-7 text-brand-500" aria-hidden="true" />
        <p class="text-xs font-semibold text-slate-700">{{ dropPrompt }}</p>
        <p class="text-[11px] text-slate-500">or choose a file from your device</p>
        <p
          v-if="modelValue"
          class="max-w-full truncate rounded-md bg-white px-3 py-1.5 text-[11px] font-semibold text-brand-700 shadow-sm"
        >
          {{ modelValue.name }}
        </p>
      </div>
    </div>
    <input
      v-else
      :id="controlId"
      type="file"
      :accept="accept"
      :required="required"
      :disabled="disabled"
      :aria-label="ariaLabel ?? label"
      :aria-invalid="Boolean(errorMessage) || undefined"
      :aria-describedby="describedBy"
      class="w-full rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white"
      :class="validationControlClass(errorMessage)"
      @change="update"
    />
    <p v-if="description" :id="descriptionId" class="text-[11px] leading-4 text-slate-500">
      {{ description }}
    </p>
    <p v-if="errorMessage" :id="errorId" class="text-[11px] leading-4 text-danger">
      {{ errorMessage }}
    </p>
  </div>
</template>
