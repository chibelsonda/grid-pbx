<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import type { CallflowEditor } from '../types/callRouting'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'

export type CallflowEntryNumberAddition =
  | { type: 'phone_number'; id: string }
  | { type: 'extension'; value: string }

const props = withDefaults(
  defineProps<{
    open: boolean
    phoneNumbers: CallflowEditor['phone_numbers']
    phoneNumberIds: string[]
    extensionNumbers: string[]
    preservedNumbers?: string[]
    saving?: boolean
    error?: string | null
    fieldErrors?: Record<string, string[]>
  }>(),
  {
    preservedNumbers: () => [],
    saving: false,
    error: null,
    fieldErrors: () => ({}),
  },
)

const emit = defineEmits<{
  close: []
  add: [addition: CallflowEntryNumberAddition]
}>()

const type = ref<'phone_number' | 'extension'>('extension')
const phoneNumberId = ref('')
const extension = ref('')
const localError = ref<string | null>(null)
const availablePhoneNumbers = computed(() =>
  props.phoneNumbers.filter(
    ({ id, available, selected }) =>
      available && !selected && !props.phoneNumberIds.includes(id),
  ),
)
const phoneNumberOptions = computed<ListboxOptionValue[]>(() =>
  availablePhoneNumbers.value.map(({ id, number, state }) => ({
    value: id,
    label: number,
    description: state?.replaceAll('_', ' ') || 'Available',
  })),
)
const fieldError = computed(
  () =>
    localError.value ??
    (type.value === 'extension'
      ? props.fieldErrors.extension_numbers?.[0]
      : props.fieldErrors.phone_number_ids?.[0]) ??
    null,
)

watch(
  () => props.open,
  (open) => {
    if (!open) return

    type.value = props.fieldErrors.extension_numbers?.length
      ? 'extension'
      : availablePhoneNumbers.value.length
        ? 'phone_number'
        : 'extension'
    phoneNumberId.value = availablePhoneNumbers.value[0]?.id ?? ''
    extension.value = ''
    localError.value = null
  },
)

watch([type, phoneNumberId, extension], () => {
  localError.value = null
})

function setPhoneNumber(value: ListboxValue): void {
  if (typeof value === 'string') phoneNumberId.value = value
}

function submit(): void {
  if (type.value === 'phone_number') {
    if (!phoneNumberId.value) {
      localError.value = 'Choose an available account phone number.'
      return
    }

    emit('add', { type: 'phone_number', id: phoneNumberId.value })
    return
  }

  const value = extension.value.trim()

  if (!/^[0-9]{2,15}$/.test(value)) {
    localError.value = 'Use 2 to 15 digits for an internal extension number.'
    return
  }

  if (props.extensionNumbers.includes(value) || props.preservedNumbers.includes(value)) {
    localError.value = 'This number is already configured on the callflow.'
    return
  }

  emit('add', { type: 'extension', value })
}
</script>

<template>
  <CallflowNodeInfoDialog
    :open="open"
    title="Add number"
    breadcrumb="Callflow / Entry points"
    eyebrow="Callflow entry"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-2 rounded-md bg-slate-100 p-1" role="radiogroup">
        <button
          type="button"
          role="radio"
          :aria-checked="type === 'phone_number'"
          :disabled="availablePhoneNumbers.length === 0"
          class="h-9 rounded-md text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45"
          :class="
            type === 'phone_number'
              ? 'bg-white text-brand-700 shadow-sm'
              : 'text-slate-600 hover:bg-white/60'
          "
          @click="type = 'phone_number'"
        >
          Spare number
        </button>
        <button
          type="button"
          role="radio"
          :aria-checked="type === 'extension'"
          class="h-9 rounded-md text-xs font-semibold transition"
          :class="
            type === 'extension'
              ? 'bg-white text-brand-700 shadow-sm'
              : 'text-slate-600 hover:bg-white/60'
          "
          @click="type = 'extension'"
        >
          Extension
        </button>
      </div>

      <label v-if="type === 'phone_number'" class="grid gap-2">
        <span class="text-xs font-semibold text-slate-700">Available account number</span>
        <FormListbox
          :model-value="phoneNumberId"
          :options="phoneNumberOptions"
          aria-label="Available account number"
          placeholder="Choose a spare number"
          :invalid="Boolean(fieldError)"
          @update:model-value="setPhoneNumber"
        />
        <span v-if="availablePhoneNumbers.length === 0" class="text-[10px] text-slate-500">
          No unassigned projected phone numbers are available for this account.
        </span>
      </label>

      <FormInput
        v-else
        v-model="extension"
        label="Extension number"
        inputmode="numeric"
        autocomplete="off"
        maxlength="15"
        placeholder="e.g. 2999"
        :error="fieldError"
      />

      <FormErrorSummary
        v-if="error && Object.keys(fieldErrors).length === 0"
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="{}"
        title="Unable to add the number"
      />
      <p
        v-if="!error && type === 'phone_number' && fieldError"
        role="alert"
        class="text-xs text-danger"
      >
        {{ fieldError }}
      </p>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="h-9 rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ saving ? 'Adding…' : 'Add number' }}
        </button>
      </div>
    </form>
  </CallflowNodeInfoDialog>
</template>
