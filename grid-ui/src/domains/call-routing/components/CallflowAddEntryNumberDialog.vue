<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ExclamationCircleIcon,
  MagnifyingGlassIcon,
  ShoppingCartIcon,
} from '@heroicons/vue/24/outline'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import type { CallflowEditor } from '../types/callRouting'
import { useCallflowEntryPointDiscovery } from '../composables/useCallflowEntryPointDiscovery'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'

export type CallflowEntryNumberAddition =
  { type: 'phone_number'; id: string } | { type: 'extension'; value: string }

const props = withDefaults(
  defineProps<{
    open: boolean
    accountId?: string
    callflowId?: string | null
    phoneNumbers: CallflowEditor['phone_numbers']
    phoneNumberInventory?: CallflowEditor['phone_number_inventory']
    phoneNumberIds: string[]
    extensionNumbers: string[]
    preservedNumbers?: string[]
    saving?: boolean
    error?: string | null
    fieldErrors?: Record<string, string[]>
  }>(),
  {
    accountId: '',
    callflowId: null,
    preservedNumbers: () => [],
    saving: false,
    error: null,
    fieldErrors: () => ({}),
  },
)

const emit = defineEmits<{
  close: []
  add: [addition: CallflowEntryNumberAddition]
  'inventory-refreshed': []
}>()

const type = ref<'phone_number' | 'extension'>('extension')
const phoneNumberId = ref('')
const extension = ref('')
const directorySearch = ref('')
const localError = ref<string | null>(null)
let availabilityTimer: number | null = null
const {
  directory,
  suggestedExtension,
  availability,
  loadingDirectory,
  checkingAvailability,
  refreshingInventory,
  discoveryError,
  loadDirectory,
  checkAvailability,
  clearAvailability,
  refreshInventory,
  reset: resetDiscovery,
} = useCallflowEntryPointDiscovery(
  () => props.accountId,
  () => props.callflowId,
)
const availablePhoneNumbers = computed(() =>
  props.phoneNumbers.filter(
    ({ id, available, selected }) => available && !selected && !props.phoneNumberIds.includes(id),
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
    (type.value === 'extension' &&
    availability.value?.number === extension.value.trim() &&
    !availability.value.available
      ? availability.value.reason
      : null) ??
    null,
)
const inventoryStatus = computed(() => props.phoneNumberInventory?.status ?? 'stale')
const inventoryMessage = computed(() => {
  if (inventoryStatus.value === 'syncing') return 'Phone-number inventory is synchronizing.'
  if (inventoryStatus.value === 'error') {
    return props.phoneNumberInventory?.error_message ?? 'The last inventory refresh failed.'
  }
  if (props.phoneNumberInventory?.last_successful_at) {
    return `Last refreshed ${new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(props.phoneNumberInventory.last_successful_at))}.`
  }

  return 'Phone-number inventory has not been synchronized yet.'
})
const extensionFormatValid = computed(() => /^[0-9]{2,15}$/.test(extension.value.trim()))
const extensionLocallyUsed = computed(() => {
  const value = extension.value.trim()

  return props.extensionNumbers.includes(value) || props.preservedNumbers.includes(value)
})

watch(
  () => props.open,
  (open) => {
    if (!open) {
      cancelAvailabilityTimer()
      resetDiscovery()
      return
    }

    type.value = props.fieldErrors.extension_numbers?.length
      ? 'extension'
      : availablePhoneNumbers.value.length
        ? 'phone_number'
        : 'extension'
    phoneNumberId.value = availablePhoneNumbers.value[0]?.id ?? ''
    extension.value = ''
    directorySearch.value = ''
    localError.value = null
    resetDiscovery()
  },
)

watch([type, phoneNumberId], () => {
  localError.value = null
  if (type.value === 'phone_number') {
    cancelAvailabilityTimer()
    clearAvailability()
  }
})

watch(
  () => availablePhoneNumbers.value.map(({ id }) => id),
  (availableIds) => {
    if (!props.open || type.value !== 'phone_number') return
    if (availableIds.includes(phoneNumberId.value)) return

    phoneNumberId.value = availableIds[0] ?? ''
  },
)

watch(extension, (value) => {
  localError.value = null
  clearAvailability()
  cancelAvailabilityTimer()

  const normalized = value.trim()
  if (!props.open || type.value !== 'extension' || !/^[0-9]{2,15}$/.test(normalized)) return
  if (props.extensionNumbers.includes(normalized) || props.preservedNumbers.includes(normalized))
    return

  availabilityTimer = window.setTimeout(() => void checkAvailability(normalized), 350)
})

onBeforeUnmount(() => {
  cancelAvailabilityTimer()
})

function cancelAvailabilityTimer(): void {
  if (availabilityTimer === null) return

  window.clearTimeout(availabilityTimer)
  availabilityTimer = null
}

function setPhoneNumber(value: ListboxValue): void {
  if (typeof value === 'string') phoneNumberId.value = value
}

async function submit(): Promise<void> {
  cancelAvailabilityTimer()

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

  if (props.accountId) {
    const result = await checkAvailability(value)
    if (!props.open || type.value !== 'extension' || extension.value.trim() !== value) return
    if (!result) {
      localError.value = discoveryError.value ?? 'Unable to verify this extension.'
      return
    }
    if (!result.available) {
      localError.value = result.reason ?? 'This extension is already in use.'
      return
    }
  }

  emit('add', { type: 'extension', value })
}

async function refreshPhoneNumbers(): Promise<void> {
  if (!(await refreshInventory())) return
  emit('inventory-refreshed')
}

function useSuggestion(): void {
  if (suggestedExtension.value) extension.value = suggestedExtension.value
}

function browseExtensions(): void {
  if (directory.value.length === 0) void loadDirectory()
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
          class="h-9 rounded-md text-xs font-semibold transition"
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

      <div v-if="type === 'phone_number'" class="grid gap-3">
        <label v-if="availablePhoneNumbers.length" class="grid gap-2">
          <span class="text-xs font-semibold text-slate-700">Available account number</span>
          <FormListbox
            :model-value="phoneNumberId"
            :options="phoneNumberOptions"
            aria-label="Available account number"
            placeholder="Choose a spare number"
            :invalid="Boolean(fieldError)"
            @update:model-value="setPhoneNumber"
          />
        </label>
        <div v-else class="rounded-md border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs font-semibold text-slate-700">No spare numbers are projected</p>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">{{ inventoryMessage }}</p>
          <button
            v-if="accountId"
            type="button"
            :disabled="refreshingInventory"
            class="mt-3 inline-flex h-8 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 shadow-sm hover:bg-slate-100 disabled:opacity-50"
            @click="refreshPhoneNumbers"
          >
            <ArrowPathIcon class="size-3.5" :class="refreshingInventory && 'animate-spin'" />
            {{ refreshingInventory ? 'Refreshing…' : 'Refresh inventory' }}
          </button>
        </div>
        <div
          class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5"
        >
          <ShoppingCartIcon class="mt-0.5 size-4 shrink-0 text-amber-700" />
          <p class="text-[10px] leading-4 text-amber-800">
            Number purchasing is unavailable until a carrier search and activation provider is
            configured.
          </p>
        </div>
      </div>

      <div v-else class="grid gap-3">
        <FormInput
          v-model="extension"
          label="Extension number"
          inputmode="numeric"
          autocomplete="off"
          maxlength="15"
          placeholder="e.g. 2999"
          :error="fieldError"
        />
        <div
          v-if="
            checkingAvailability ||
            (!fieldError && availability && extensionFormatValid && !extensionLocallyUsed)
          "
          class="flex items-start gap-2 text-[10px] leading-4"
          :class="availability?.available ? 'text-emerald-700' : 'text-slate-500'"
        >
          <ArrowPathIcon v-if="checkingAvailability" class="mt-0.5 size-3.5 animate-spin" />
          <CheckCircleIcon v-else-if="availability?.available" class="mt-0.5 size-3.5" />
          <ExclamationCircleIcon v-else class="mt-0.5 size-3.5 text-danger" />
          <span>
            {{
              checkingAvailability
                ? 'Checking account availability…'
                : availability?.available
                  ? `Extension ${availability.number} is available.`
                  : availability?.reason
            }}
          </span>
        </div>
        <button
          v-if="availability && !availability.available && suggestedExtension"
          type="button"
          class="w-fit text-[10px] font-semibold text-brand-700 hover:text-brand-800"
          @click="useSuggestion"
        >
          Use suggested extension {{ suggestedExtension }}
        </button>

        <Disclosure
          v-slot="{ open: directoryOpen }"
          as="div"
          class="rounded-md border border-slate-200"
        >
          <DisclosureButton
            class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold text-slate-700"
            @click="browseExtensions"
          >
            <span>Browse extensions already in use</span>
            <ChevronDownIcon class="size-4 transition" :class="directoryOpen && 'rotate-180'" />
          </DisclosureButton>
          <DisclosurePanel class="grid gap-3 border-t border-slate-200 p-3">
            <div class="relative">
              <MagnifyingGlassIcon
                class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400"
              />
              <input
                v-model="directorySearch"
                type="search"
                aria-label="Search used extensions"
                placeholder="Search extension or callflow"
                class="h-9 w-full rounded-md border border-slate-300 bg-white pr-3 pl-9 text-xs text-slate-700 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                @input="loadDirectory(directorySearch)"
              />
            </div>
            <div
              v-if="loadingDirectory"
              class="flex items-center gap-2 py-3 text-[10px] text-slate-500"
            >
              <ArrowPathIcon class="size-3.5 animate-spin" /> Loading extensions…
            </div>
            <ul
              v-else-if="directory.length"
              class="max-h-44 divide-y divide-slate-100 overflow-y-auto"
            >
              <li
                v-for="entry in directory"
                :key="`${entry.source}-${entry.number}`"
                class="flex items-center justify-between gap-3 py-2 text-xs"
              >
                <div class="min-w-0">
                  <p class="font-mono font-semibold text-slate-700">{{ entry.number }}</p>
                  <p class="truncate text-[10px] text-slate-500">{{ entry.label }}</p>
                </div>
                <span
                  class="shrink-0 text-[9px] font-semibold tracking-wide text-slate-400 uppercase"
                >
                  {{ entry.current ? 'Current callflow' : 'In use' }}
                </span>
              </li>
            </ul>
            <p v-else class="py-2 text-[10px] text-slate-500">
              No occupied extensions match this search.
            </p>
          </DisclosurePanel>
        </Disclosure>
      </div>

      <p v-if="discoveryError && !localError" role="alert" class="text-xs text-danger">
        {{ discoveryError }}
      </p>

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
          :disabled="saving || checkingAvailability"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ saving ? 'Adding…' : 'Add number' }}
        </button>
      </div>
    </form>
  </CallflowNodeInfoDialog>
</template>
