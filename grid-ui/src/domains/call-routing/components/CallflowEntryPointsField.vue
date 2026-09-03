<script setup lang="ts">
import { ref, watch } from 'vue'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { CallflowEditor } from '../types/callRouting'

const props = withDefaults(
  defineProps<{
    phoneNumbers: CallflowEditor['phone_numbers']
    phoneNumberIds: string[]
    extensionNumbers: string[]
    preservedNumbers?: string[]
    phoneError?: string | null
    extensionError?: string | null
  }>(),
  {
    preservedNumbers: () => [],
    phoneError: null,
    extensionError: null,
  },
)

const emit = defineEmits<{
  'update:phoneNumberIds': [value: string[]]
  'update:extensionNumbers': [value: string[]]
}>()

const extensionDraft = ref('')
const draftError = ref<string | null>(null)

watch(extensionDraft, () => {
  draftError.value = null
})

function addExtension(): void {
  const extension = extensionDraft.value.trim()

  if (!/^[0-9]{2,15}$/.test(extension)) {
    draftError.value = 'Use 2 to 15 digits for an internal extension number.'
    return
  }

  if (props.extensionNumbers.includes(extension) || props.preservedNumbers.includes(extension)) {
    draftError.value = 'This number is already configured on the callflow.'
    return
  }

  emit('update:extensionNumbers', [...props.extensionNumbers, extension])
  extensionDraft.value = ''
}

function removeExtension(extension: string): void {
  emit(
    'update:extensionNumbers',
    props.extensionNumbers.filter((candidate) => candidate !== extension),
  )
}
</script>

<template>
  <section
    class="overflow-hidden rounded-md border border-slate-200"
    :class="validationControlClass(phoneError || extensionError)"
  >
    <header class="border-b border-slate-200 bg-slate-50 px-4 py-3">
      <h2 class="text-xs font-semibold text-slate-700">Callflow entry numbers</h2>
      <p class="mt-1 text-[10px] leading-4 text-heading-description">
        Add an unused internal extension or choose an inventory-backed phone number.
      </p>
    </header>

    <div class="grid gap-4 border-b border-slate-200 p-4">
      <div class="flex items-end gap-2">
        <FormInput
          v-model="extensionDraft"
          class="min-w-0 flex-1"
          label="Internal extension number"
          inputmode="numeric"
          autocomplete="off"
          maxlength="15"
          placeholder="e.g. 2999"
          :error="draftError ?? extensionError"
          @keydown.enter.prevent="addExtension"
        />
        <button
          type="button"
          class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
          @click="addExtension"
        >
          <PlusIcon class="size-4" /> Add
        </button>
      </div>

      <div v-if="extensionNumbers.length" class="flex flex-wrap gap-2">
        <span
          v-for="extension in extensionNumbers"
          :key="extension"
          class="inline-flex h-8 items-center gap-2 rounded-md border border-brand-200 bg-brand-50 px-2.5 font-mono text-xs font-semibold text-brand-700"
        >
          {{ extension }}
          <button
            type="button"
            :aria-label="`Remove extension number ${extension}`"
            class="rounded-sm text-brand-500 hover:bg-brand-100 hover:text-brand-800"
            @click="removeExtension(extension)"
          >
            <XMarkIcon class="size-3.5" />
          </button>
        </span>
      </div>

      <div v-if="preservedNumbers.length" class="rounded-md bg-slate-50 px-3 py-2">
        <p class="text-[10px] font-semibold text-slate-600">Managed or preserved entries</p>
        <p class="mt-1 font-mono text-[10px] text-slate-500">
          {{ preservedNumbers.join(', ') }}
        </p>
        <p class="mt-1 text-[10px] leading-4 text-slate-500">
          These entries belong to another workflow or use an unsupported format and remain
          unchanged.
        </p>
      </div>
    </div>

    <div>
      <header class="border-b border-slate-200 bg-white px-4 py-3">
        <h3 class="text-xs font-semibold text-slate-700">Phone-number entry points</h3>
        <p class="mt-1 text-[10px] text-heading-description">
          Choose from the account's projected phone-number inventory.
        </p>
      </header>
      <div v-if="phoneNumbers.length" class="max-h-72 divide-y divide-slate-100">
        <FormCheckbox
          v-for="phoneNumber in phoneNumbers"
          :key="phoneNumber.id"
          :model-value="phoneNumberIds"
          :value="phoneNumber.id"
          :label="phoneNumber.number"
          :description="
            phoneNumber.available
              ? phoneNumber.selected
                ? 'Currently enters this callflow'
                : phoneNumber.state?.replaceAll('_', ' ') || 'Available'
              : `Assigned to ${phoneNumber.assigned_callflow?.name ?? 'another callflow'}`
          "
          :disabled="!phoneNumber.available"
          variant="row"
          @update:model-value="emit('update:phoneNumberIds', $event as string[])"
        />
      </div>
      <p v-else class="p-4 text-xs text-slate-500">
        No projected phone numbers are available for this account.
      </p>
      <p v-if="phoneError" class="px-4 pb-3 text-[10px] text-danger">
        {{ phoneError }}
      </p>
    </div>
  </section>
</template>
