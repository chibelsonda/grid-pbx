<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import type {
  CallflowDestinationType,
  CallflowEditor,
  CallflowMenuBranchInput,
  CallflowMenuBranchKey,
} from '../types/callRouting'

const props = defineProps<{
  branches: CallflowMenuBranchInput[]
  editor: CallflowEditor
  errors: Record<string, string[]>
}>()
const emit = defineEmits<{ 'update:branches': [branches: CallflowMenuBranchInput[]] }>()

const editableDefinitions = computed(() =>
  props.editor.menu_branches.branches.filter(({ editable }) => editable),
)
const lockedDefinitions = computed(() =>
  props.editor.menu_branches.branches.filter(({ editable, target }) => !editable && target === null),
)
const canAdd = computed(
  () => editableDefinitions.value.some(({ key }) => !props.branches.some((row) => row.key === key)),
)

function fieldError(index: number, field: string): string | null {
  return props.errors[`menu_branches.${index}.${field}`]?.[0] ?? null
}

function destinationTypeOptions(): ListboxOptionValue[] {
  return props.editor.destination_types.map(({ value, label }) => ({
    value,
    label,
    disabled: props.editor.destinations[value].length === 0,
  }))
}

function destinationOptions(type: CallflowDestinationType): ListboxOptionValue[] {
  return props.editor.destinations[type].map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  }))
}

function keyOptions(index: number): ListboxOptionValue[] {
  const current = props.branches[index]?.key
  const used = new Set(props.branches.map(({ key }) => key))

  return editableDefinitions.value.map(({ key, label }) => ({
    value: key,
    label,
    disabled: key !== current && used.has(key),
  }))
}

function replace(index: number, patch: Partial<CallflowMenuBranchInput>): void {
  emit(
    'update:branches',
    props.branches.map((branch, branchIndex) =>
      branchIndex === index ? { ...branch, ...patch } : branch,
    ),
  )
}

function setKey(index: number, value: ListboxValue): void {
  if (typeof value === 'string') replace(index, { key: value as CallflowMenuBranchKey })
}

function setDestinationType(index: number, value: ListboxValue): void {
  if (typeof value !== 'string') return
  const type = value as CallflowDestinationType
  replace(index, {
    destination_type: type,
    destination_id: props.editor.destinations[type][0]?.id ?? '',
  })
}

function setDestination(index: number, value: ListboxValue): void {
  if (typeof value === 'string') replace(index, { destination_id: value })
}

function addBranch(): void {
  const definition = editableDefinitions.value.find(
    ({ key }) => !props.branches.some((branch) => branch.key === key),
  )
  const type = props.editor.destination_types.find(
    ({ value }) => props.editor.destinations[value].length > 0,
  )?.value

  if (!definition || !type) return

  emit('update:branches', [
    ...props.branches,
    {
      key: definition.key,
      destination_type: type,
      destination_id: props.editor.destinations[type][0]?.id ?? '',
    },
  ])
}

function removeBranch(index: number): void {
  emit(
    'update:branches',
    props.branches.filter((_, branchIndex) => branchIndex !== index),
  )
}
</script>

<template>
  <div class="grid gap-4">
    <div
      v-for="(branch, index) in branches"
      :key="`${branch.key}:${index}`"
      class="grid gap-3 rounded-md border border-slate-200 bg-slate-50/60 p-4"
    >
      <div class="grid gap-3 sm:grid-cols-[9rem_1fr_1fr_auto] sm:items-end">
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Menu key</span>
          <FormListbox
            :model-value="branch.key"
            :options="keyOptions(index)"
            :aria-label="`Menu branch key ${index + 1}`"
            :invalid="Boolean(fieldError(index, 'key'))"
            @update:model-value="setKey(index, $event)"
          />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Destination type</span>
          <FormListbox
            :model-value="branch.destination_type"
            :options="destinationTypeOptions()"
            :aria-label="`Menu branch type ${index + 1}`"
            :invalid="Boolean(fieldError(index, 'destination_type'))"
            @update:model-value="setDestinationType(index, $event)"
          />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Destination</span>
          <FormListbox
            :model-value="branch.destination_id"
            :options="destinationOptions(branch.destination_type)"
            :aria-label="`Menu branch destination ${index + 1}`"
            placeholder="Select a destination"
            :invalid="Boolean(fieldError(index, 'destination_id'))"
            @update:model-value="setDestination(index, $event)"
          />
        </label>
        <button
          type="button"
          class="grid size-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-500 hover:border-red-300 hover:text-danger"
          :aria-label="`Remove Menu branch ${index + 1}`"
          @click="removeBranch(index)"
        >
          <TrashIcon class="size-4" />
        </button>
      </div>
      <p v-if="fieldError(index, 'key')" class="text-[10px] text-danger">
        {{ fieldError(index, 'key') }}
      </p>
      <p v-if="fieldError(index, 'destination_id')" class="text-[10px] text-danger">
        {{ fieldError(index, 'destination_id') }}
      </p>
    </div>

    <p v-if="branches.length === 0" class="text-xs text-slate-500">
      No keyed routes are configured. The default action remains in the fallback section above.
    </p>

    <button
      type="button"
      :disabled="!canAdd"
      class="inline-flex h-10 w-fit items-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:border-brand-400 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
      @click="addBranch"
    >
      <PlusIcon class="size-4" />
      Add key route
    </button>

    <div
      v-if="lockedDefinitions.length || editor.menu_branches.legacy_hash_present || editor.menu_branches.unknown_branch_keys.length"
      class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-800"
    >
      <p v-if="lockedDefinitions.length">
        Read-only keys preserved: {{ lockedDefinitions.map(({ label }) => label).join(', ') }}.
      </p>
      <p v-if="editor.menu_branches.legacy_hash_present">
        Legacy # branch preserved. New # branches are intentionally unavailable.
      </p>
      <p v-if="editor.menu_branches.unknown_branch_keys.length">
        Unknown branches preserved: {{ editor.menu_branches.unknown_branch_keys.join(', ') }}.
      </p>
    </div>
    <p v-if="errors.menu_branches?.[0]" class="text-[10px] text-danger">
      {{ errors.menu_branches[0] }}
    </p>
  </div>
</template>
