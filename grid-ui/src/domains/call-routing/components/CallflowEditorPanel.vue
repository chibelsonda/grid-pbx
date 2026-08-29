<script setup lang="ts">
import { computed, watch } from 'vue'
import {
  ArrowPathRoundedSquareIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import CallflowMenuBranchesField from './CallflowMenuBranchesField.vue'
import { useCallflowForm } from '../composables/useCallflowForm'
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
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: CallflowUpdate] }>()
const { form, validate, validationErrors } = useCallflowForm(
  () => props.record,
  () => props.editor,
)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const options = computed(() => props.editor?.destinations[form.destination_type] ?? [])
const selectedOption = computed(() =>
  options.value.find((option) => option.id === form.destination_id),
)
const destinationTypeOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.destination_types ?? []).map(({ value, label }) => ({
    value,
    label,
    disabled: props.editor?.destinations[value].length === 0,
  })),
)
const destinationOptions = computed<ListboxOptionValue[]>(() =>
  options.value.map(({ id, label, detail }) => ({ value: id, label, description: detail })),
)
const fallbackOptions = computed(
  () => props.editor?.destinations[form.fallback_destination_type] ?? [],
)
const fallbackDestinationOptions = computed<ListboxOptionValue[]>(() =>
  fallbackOptions.value.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const temporalMatchOptions = computed(
  () => props.editor?.destinations[form.temporal_match_destination_type] ?? [],
)
const temporalMatchDestinationOptions = computed<ListboxOptionValue[]>(() =>
  temporalMatchOptions.value.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const selectedTemporalRules = computed(
  () => props.editor?.temporal_rule_sets[form.destination_id] ?? [],
)

watch(
  () => form.destination_type,
  () => {
    if (!options.value.some(({ id }) => id === form.destination_id)) {
      form.destination_id = options.value[0]?.id ?? ''
    }
  },
)

watch(
  () => form.fallback_destination_type,
  () => {
    if (!fallbackOptions.value.some(({ id }) => id === form.fallback_destination_id)) {
      form.fallback_destination_id = fallbackOptions.value[0]?.id ?? ''
    }
  },
)

watch(
  () => form.temporal_match_destination_type,
  () => {
    if (!temporalMatchOptions.value.some(({ id }) => id === form.temporal_match_destination_id)) {
      form.temporal_match_destination_id = temporalMatchOptions.value[0]?.id ?? ''
    }
  },
)

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) emit('save', result.data)
}

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function setDestinationType(value: ListboxValue): void {
  if (typeof value === 'string') form.destination_type = value as CallflowDestinationType
}

function setDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.destination_id = value
}

function setFallbackDestinationType(value: ListboxValue): void {
  if (typeof value === 'string') {
    form.fallback_destination_type = value as CallflowDestinationType
  }
}

function setFallbackDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.fallback_destination_id = value
}

function setTemporalMatchDestinationType(value: ListboxValue): void {
  if (typeof value === 'string') {
    form.temporal_match_destination_type = value as CallflowDestinationType
  }
}

function setTemporalMatchDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.temporal_match_destination_id = value
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
        <ShieldCheckIcon class="mx-auto size-10 text-slate-400" />
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

    <form v-else-if="editor" class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="error && Object.keys(fieldErrors).length === 0"
        class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ error }}
      </div>

      <fieldset :disabled="saving" class="grid gap-5 disabled:opacity-75">
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
                aria-label="Route name"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('name'))"
                :aria-invalid="Boolean(fieldError('name'))"
              />
              <span v-if="fieldError('name')" class="text-[10px] text-danger">{{
                fieldError('name')
              }}</span>
            </label>
            <div v-if="record">
              <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                Entry points
              </p>
              <p class="mt-1 font-mono text-xs text-slate-600">
                {{
                  record.numbers.join(', ') || record.patterns.join(', ') || 'No direct entry point'
                }}
              </p>
            </div>
          </div>
        </article>

        <article
          class="card-surface overflow-hidden"
          :class="validationControlClass(fieldError('phone_number_ids'))"
          :aria-invalid="Boolean(fieldError('phone_number_ids'))"
        >
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
          <p v-if="fieldError('phone_number_ids')" class="px-5 pb-4 text-[10px] text-danger">
            {{ fieldError('phone_number_ids') }}
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
              <FormListbox
                :model-value="form.destination_type"
                :options="destinationTypeOptions"
                aria-label="Destination type"
                :invalid="Boolean(fieldError('destination_type'))"
                @update:model-value="setDestinationType"
              />
              <span v-if="fieldError('destination_type')" class="text-[10px] text-danger">{{
                fieldError('destination_type')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Destination</span>
              <FormListbox
                :model-value="form.destination_id"
                :options="destinationOptions"
                aria-label="Destination"
                :placeholder="
                  options.length ? 'Select a destination' : 'No projected targets available'
                "
                :invalid="Boolean(fieldError('destination_id'))"
                @update:model-value="setDestination"
              />
              <span v-if="fieldError('destination_id')" class="text-[10px] text-danger">{{
                fieldError('destination_id')
              }}</span>
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

        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Fallback destination</h2>
            <p class="mt-1 text-[10px] leading-4 text-slate-400">
              The wildcard branch runs when the root destination does not complete the call.
            </p>
          </header>
          <div v-if="editor.fallback.editable" class="grid gap-5 p-5">
            <ToggleSwitch
              v-model="form.fallback_enabled"
              label="Use a fallback destination"
              description="Create or replace the root node's wildcard branch."
            />
            <div v-if="form.fallback_enabled" class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Fallback type</span>
                <FormListbox
                  :model-value="form.fallback_destination_type"
                  :options="destinationTypeOptions"
                  aria-label="Fallback type"
                  :invalid="Boolean(fieldError('fallback_destination_type'))"
                  @update:model-value="setFallbackDestinationType"
                />
                <span
                  v-if="fieldError('fallback_destination_type')"
                  class="text-[10px] text-danger"
                  >{{ fieldError('fallback_destination_type') }}</span
                >
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Fallback destination</span>
                <FormListbox
                  :model-value="form.fallback_destination_id"
                  :options="fallbackDestinationOptions"
                  aria-label="Fallback destination"
                  :placeholder="
                    fallbackOptions.length
                      ? 'Select a fallback destination'
                      : 'No projected targets available'
                  "
                  :invalid="Boolean(fieldError('fallback_destination_id'))"
                  @update:model-value="setFallbackDestination"
                />
                <span
                  v-if="fieldError('fallback_destination_id')"
                  class="text-[10px] text-danger"
                  >{{ fieldError('fallback_destination_id') }}</span
                >
              </label>
            </div>
          </div>
          <div v-else class="flex gap-3 bg-amber-50 p-5 text-xs leading-5 text-amber-800">
            <ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" />
            <p>{{ editor.fallback.blocked_reason }}</p>
          </div>
        </article>

        <article
          v-if="form.destination_type === 'menu'"
          class="card-surface overflow-hidden"
          :class="validationControlClass(fieldError('menu_branches'))"
          :aria-invalid="Boolean(fieldError('menu_branches'))"
        >
          <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Menu key routes</h2>
            <p class="mt-1 text-[10px] leading-4 text-slate-400">
              Route digits, Star, or timeout. Configure the default action in the fallback section.
            </p>
          </header>
          <div v-if="editor.menu_branches.editable" class="p-5">
            <CallflowMenuBranchesField
              :branches="form.menu_branches"
              :editor="editor"
              :errors="errors"
              @update:branches="form.menu_branches = $event"
            />
          </div>
          <div v-else class="flex gap-3 bg-amber-50 p-5 text-xs leading-5 text-amber-800">
            <ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" />
            <p>{{ editor.menu_branches.blocked_reason }}</p>
          </div>
        </article>

        <article
          v-if="form.destination_type === 'temporal_rule_set'"
          class="card-surface overflow-hidden"
          :class="validationControlClass(fieldError('temporal_match_destination_id'))"
          :aria-invalid="Boolean(fieldError('temporal_match_destination_id'))"
        >
          <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Schedule routes</h2>
            <p class="mt-1 text-[10px] leading-4 text-slate-500">
              Switch evaluates the selected Rule Set in its configured order. A match follows the
              route below; no match follows the fallback destination.
            </p>
          </header>
          <div v-if="editor.temporal_match.editable" class="grid gap-5 p-5">
            <div class="rounded-md border border-slate-200 bg-slate-50/60 p-4">
              <p class="text-xs font-semibold text-slate-700">Rule evaluation order</p>
              <ol v-if="selectedTemporalRules.length" class="mt-3 grid gap-2">
                <li
                  v-for="rule in selectedTemporalRules"
                  :key="rule.id ?? `unresolved-${rule.position}`"
                  class="flex items-center gap-3 text-xs"
                >
                  <span
                    class="grid size-6 shrink-0 place-items-center rounded-full border border-slate-300 bg-white text-[10px] font-semibold text-slate-600"
                  >
                    {{ rule.position + 1 }}
                  </span>
                  <span :class="rule.resolved ? 'text-slate-700' : 'font-semibold text-amber-700'">
                    {{ rule.label }}
                  </span>
                </li>
              </ol>
              <p v-else class="mt-2 text-[10px] text-amber-700">
                This Rule Set has no projected member rules.
              </p>
            </div>

            <ToggleSwitch
              v-model="form.temporal_match_enabled"
              label="Route matching calls"
              description="Use the literal rule_set branch required by the Switch temporal-route contract."
            />

            <div v-if="form.temporal_match_enabled" class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Match destination type</span>
                <FormListbox
                  :model-value="form.temporal_match_destination_type"
                  :options="destinationTypeOptions"
                  aria-label="Schedule match destination type"
                  :invalid="Boolean(fieldError('temporal_match_destination_type'))"
                  @update:model-value="setTemporalMatchDestinationType"
                />
                <span
                  v-if="fieldError('temporal_match_destination_type')"
                  class="text-[10px] text-danger"
                >
                  {{ fieldError('temporal_match_destination_type') }}
                </span>
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Match destination</span>
                <FormListbox
                  :model-value="form.temporal_match_destination_id"
                  :options="temporalMatchDestinationOptions"
                  aria-label="Schedule match destination"
                  :placeholder="
                    temporalMatchOptions.length
                      ? 'Select a match destination'
                      : 'No projected targets available'
                  "
                  :invalid="Boolean(fieldError('temporal_match_destination_id'))"
                  @update:model-value="setTemporalMatchDestination"
                />
                <span
                  v-if="fieldError('temporal_match_destination_id')"
                  class="text-[10px] text-danger"
                >
                  {{ fieldError('temporal_match_destination_id') }}
                </span>
              </label>
            </div>

            <div
              v-if="editor.temporal_match.preserved_branch_count"
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-800"
            >
              {{ editor.temporal_match.preserved_branch_count }} additional legacy temporal
              {{
                editor.temporal_match.preserved_branch_count === 1 ? 'branch is' : 'branches are'
              }}
              preserved read-only.
            </div>
          </div>
          <div v-else class="flex gap-3 bg-amber-50 p-5 text-xs leading-5 text-amber-800">
            <ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" />
            <p>{{ editor.temporal_match.blocked_reason }}</p>
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
            GridPBX fetches the latest route from Switch before saving. The root destination
            changes, while every existing child and unsupported branch is preserved.
          </template>
        </aside>
      </fieldset>

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
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Saving route…' : editor.mode === 'create' ? 'Create route' : 'Save route' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
