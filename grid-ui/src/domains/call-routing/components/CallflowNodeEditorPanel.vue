<script setup lang="ts">
import { computed } from 'vue'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { findCallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import { useCallflowNodeForm } from '../composables/useCallflowNodeForm'
import type {
  CallflowEditor,
  CallflowCapturedNumberBranchKey,
  CallflowNodeEditorContext,
  CallflowTreeBranchKey,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeUpdateInput,
} from '../types/callRouting'

const props = defineProps<{
  context: CallflowNodeEditorContext
  editor: CallflowEditor | null
  loading: boolean
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  save: [input: CallflowTreeNodeCreateInput | CallflowTreeNodeUpdateInput]
}>()
const { form, validationErrors, destinations, branches, usesCapturedNumberBranch, validate } =
  useCallflowNodeForm(
    () => props.context,
    () => props.editor,
  )
const action = computed(() => findCallflowAction(props.context.module))
const actionIcon = computed(() => callflowActionIcon(props.context.module))
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const destinationOptions = computed<ListboxOptionValue[]>(() =>
  destinations.value.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const branchOptions = computed<ListboxOptionValue[]>(() => branches.value)
const title = computed(() =>
  props.context.operation === 'create'
    ? `Add ${action.value?.label ?? 'callflow action'}`
    : `Edit ${action.value?.label ?? 'callflow action'}`,
)

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function setBranch(value: ListboxValue): void {
  if (branches.value.some((option) => option.value === value)) {
    form.branch = value as CallflowTreeBranchKey
  }
}

function setCapturedNumberBranch(value: string | number | null): void {
  const branch = String(value ?? '').trim()
  const defaultAvailable = branches.value.some(({ value }) => value === '_')

  form.branch =
    branch === '' ? (defaultAvailable ? '_' : null) : (branch as CallflowCapturedNumberBranchKey)
}

function setDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.destination_id = value
}

function submit(): void {
  const input = validate()
  if (input) emit('save', input)
}
</script>

<template>
  <CrudSlideOver
    :title="title"
    eyebrow="GridPBX / Callflows / Action"
    :description="
      context.operation === 'create'
        ? 'Attach one schema-aligned action to an empty branch of the selected node.'
        : 'Change this action target while preserving its module settings and complete subtree.'
    "
    width="medium"
    @close="emit('close')"
  >
    <div
      v-if="loading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-500"
    >
      Loading available destinations…
    </div>
    <form v-else class="grid gap-5" novalidate @submit.prevent="submit">
      <section class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
            <component :is="actionIcon" class="size-4" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">
              {{ action?.label ?? context.module }}
            </h2>
            <p class="mt-0.5 font-mono text-[10px] text-slate-500">{{ context.module }}</p>
          </div>
        </header>
        <div class="grid gap-5 p-5">
          <FormInput
            v-if="context.operation === 'create' && usesCapturedNumberBranch"
            :model-value="form.branch === '_' ? '' : (form.branch ?? '')"
            label="Captured number branch"
            description="Enter the exact captured dial string, or leave empty for the default continuation."
            placeholder="1000"
            :error="fieldError('branch')"
            @update:model-value="setCapturedNumberBranch"
          />
          <label v-else-if="context.operation === 'create'" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-700">Parent branch</span>
            <FormListbox
              :model-value="form.branch"
              :options="branchOptions"
              aria-label="Parent branch"
              :invalid="Boolean(fieldError('branch'))"
              placeholder="Select an empty branch"
              @update:model-value="setBranch"
            />
            <span v-if="fieldError('branch')" class="text-[10px] font-medium text-danger">
              {{ fieldError('branch') }}
            </span>
          </label>

          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-700">Destination</span>
            <FormListbox
              :model-value="form.destination_id"
              :options="destinationOptions"
              aria-label="Action destination"
              :invalid="Boolean(fieldError('destination_id'))"
              :disabled="destinationOptions.length === 0"
              placeholder="Select a destination"
              @update:model-value="setDestination"
            />
            <span v-if="fieldError('destination_id')" class="text-[10px] font-medium text-danger">
              {{ fieldError('destination_id') }}
            </span>
            <span v-else-if="destinationOptions.length === 0" class="text-[10px] text-amber-700">
              No synchronized destinations are available for this action.
            </span>
          </label>
        </div>
      </section>

      <div
        class="flex gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          GridPBX sends public identifiers only. Switch-specific IDs and existing action data stay
          on the server.
        </p>
      </div>

      <p v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </p>

      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-700"
          :disabled="saving"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="saving || destinationOptions.length === 0"
        >
          {{ saving ? 'Saving…' : context.operation === 'create' ? 'Add action' : 'Save target' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
