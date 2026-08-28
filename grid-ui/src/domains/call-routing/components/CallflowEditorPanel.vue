<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type {
  Callflow,
  CallflowDestinationType,
  CallflowEditor,
  CallflowUpdate,
} from '../types/callRouting'

const props = defineProps<{
  record: Callflow | null
  editor: CallflowEditor | null
  loading: boolean
  saving: boolean
  error: string | null
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: CallflowUpdate] }>()
const form = reactive<CallflowUpdate>({
  name: '',
  destination_type: 'extension',
  destination_id: '',
  phone_number_ids: [],
})
const options = computed(() => props.editor?.destinations[form.destination_type] ?? [])
const selectedOption = computed(() =>
  options.value.find((option) => option.id === form.destination_id),
)

watch(
  [() => props.record, () => props.editor],
  ([record, editor]) => {
    if (!editor) return

    form.name = record?.name ?? record?.numbers[0] ?? ''
    const currentType = record?.flow?.target?.type
    const firstAvailable = editor.destination_types.find(
      ({ value }) => editor.destinations[value].length > 0,
    )?.value
    form.destination_type = currentType ?? firstAvailable ?? 'extension'
    form.destination_id =
      currentType &&
      editor.destinations[currentType].some(({ id }) => id === record?.flow?.target?.id)
        ? (record?.flow?.target?.id ?? '')
        : (editor.destinations[form.destination_type][0]?.id ?? '')
    form.phone_number_ids = editor.phone_numbers
      .filter(({ selected }) => selected)
      .map(({ id }) => id)
  },
  { immediate: true },
)

watch(
  () => form.destination_type,
  () => {
    if (!options.value.some(({ id }) => id === form.destination_id)) {
      form.destination_id = options.value[0]?.id ?? ''
    }
  },
)

function submit(): void {
  if (!form.destination_id || !form.name.trim()) return

  emit('save', {
    name: form.name.trim(),
    destination_type: form.destination_type as CallflowDestinationType,
    destination_id: form.destination_id,
    phone_number_ids: [...form.phone_number_ids],
  })
}

function humanizePhoneState(state: string | null): string {
  return state
    ? state.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Available'
}
</script>

<template>
  <CrudSlideOver
    :title="editor?.mode === 'create' ? 'Create call route' : 'Edit guided route'"
    :eyebrow="`GridPBX / Call Routing / ${editor?.mode === 'create' ? 'Create' : 'Edit'}`"
    :description="
      editor?.mode === 'create'
        ? 'Create a phone-number route using safe public GridPBX references.'
        : 'Choose one safe root destination using public GridPBX references.'
    "
    width="medium"
    @close="emit('close')"
  >
    <div
      v-if="loading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading routing targets…
    </div>

    <div
      v-else-if="error && !editor"
      class="rounded-md border border-red-100 bg-red-50 p-5 text-xs text-danger"
    >
      {{ error }}
    </div>

    <div
      v-else-if="!canManage"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <ShieldCheckIcon class="mx-auto size-10 text-slate-300" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Read-only account access</h2>
        <p class="mt-2 text-xs text-slate-500">
          Your organization role can inspect routing but cannot change Switch configuration.
        </p>
      </div>
    </div>

    <div
      v-else-if="editor && !editor.editable"
      class="rounded-md border border-amber-100 bg-amber-50 p-5 text-xs text-amber-800"
    >
      <ExclamationTriangleIcon class="mb-3 size-6" />
      {{ editor.blocked_reason }}
    </div>

    <form v-else-if="editor" class="grid gap-5" @submit.prevent="submit">
      <div
        v-if="error"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ error }}
      </div>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
            <ArrowPathRoundedSquareIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Route identity</h2>
            <p class="text-[10px] text-slate-400">
              {{ record ? 'Name and non-phone entry points' : 'Name shown throughout GridPBX' }}
            </p>
          </div>
        </header>
        <div class="grid gap-4 p-5">
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Route name</span>
            <input
              v-model="form.name"
              required
              maxlength="128"
              class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            />
          </label>
          <div v-if="record">
            <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">Entry points</p>
            <p class="mt-1 font-mono text-xs text-slate-600">
              {{
                record.numbers.join(', ') || record.patterns.join(', ') || 'No direct entry point'
              }}
            </p>
          </div>
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Phone-number entry points</h2>
          <p class="mt-1 text-[10px] leading-4 text-slate-400">
            Select the inventory numbers that should enter this route. Extensions and patterns are
            preserved.
          </p>
        </header>
        <div v-if="editor.phone_numbers.length" class="divide-y divide-slate-100 px-5">
          <label
            v-for="phoneNumber in editor.phone_numbers"
            :key="phoneNumber.id"
            class="flex items-start gap-3 py-4"
            :class="phoneNumber.available ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
          >
            <input
              v-model="form.phone_number_ids"
              type="checkbox"
              :value="phoneNumber.id"
              :disabled="!phoneNumber.available"
              class="mt-0.5 size-4 accent-brand-500"
            />
            <span class="min-w-0">
              <span class="block font-mono text-xs font-semibold text-slate-700">
                {{ phoneNumber.number }}
              </span>
              <span v-if="phoneNumber.available" class="mt-1 block text-[10px] text-slate-400">
                {{
                  phoneNumber.selected
                    ? 'Currently enters this route'
                    : humanizePhoneState(phoneNumber.state)
                }}
              </span>
              <span v-else class="mt-1 block text-[10px] font-semibold text-amber-600">
                Assigned to {{ phoneNumber.assigned_callflow?.name ?? 'another route' }}
              </span>
            </span>
          </label>
        </div>
        <p v-else class="p-5 text-xs text-slate-400">
          No projected phone numbers are available for this account.
        </p>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Root destination</h2>
          <p class="mt-1 text-[10px] text-slate-400">
            Only projected, account-scoped targets are available.
          </p>
        </header>
        <div class="grid gap-5 p-5">
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Destination type</span>
            <select
              v-model="form.destination_type"
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            >
              <option
                v-for="type in editor.destination_types"
                :key="type.value"
                :value="type.value"
                :disabled="editor.destinations[type.value].length === 0"
              >
                {{ type.label }}
              </option>
            </select>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Destination</span>
            <select
              v-model="form.destination_id"
              required
              class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            >
              <option value="" disabled>
                {{ options.length ? 'Select a destination' : 'No projected targets available' }}
              </option>
              <option v-for="option in options" :key="option.id" :value="option.id">
                {{ option.label }}{{ option.detail ? ` · ${option.detail}` : '' }}
              </option>
            </select>
          </label>
          <div
            v-if="selectedOption"
            class="flex items-start gap-3 rounded-md border border-emerald-100 bg-emerald-50 p-4"
          >
            <CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-emerald-600" />
            <div>
              <p class="text-xs font-semibold text-emerald-800">Resolved GridPBX target</p>
              <p class="mt-1 text-[10px] text-emerald-700">
                {{ selectedOption.label }} is mapped server-side; its Switch identifier is never
                sent to the browser.
              </p>
            </div>
          </div>
        </div>
      </article>

      <aside
        class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <template v-if="editor.mode === 'create'">
          GridPBX creates the route in Switch first, then projects it into MySQL and assigns the
          selected numbers.
        </template>
        <template v-else>
          GridPBX fetches the latest route from Switch before saving. The root destination changes,
          while every existing child and unsupported branch is preserved.
        </template>
      </aside>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="
            saving ||
            !form.destination_id ||
            !form.name.trim() ||
            (editor.mode === 'create' && form.phone_number_ids.length === 0)
          "
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Saving route…' : editor.mode === 'create' ? 'Create route' : 'Save route' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
