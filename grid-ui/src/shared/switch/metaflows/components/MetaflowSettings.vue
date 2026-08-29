<script setup lang="ts">
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { newMetaflowNode } from '../catalog'
import type { MetaflowAction, MetaflowChild, MetaflowExtensionOption, MetaflowNode } from '../types'
import MetaflowNodeEditor from './MetaflowNodeEditor.vue'

const props = defineProps<{
  fieldErrors: Record<string, string[]>
  lockedActionCount: number
  mediaOptions: Array<{ id: string; name: string | null }>
  callflowOptions: Array<{ id: string; name: string | null; description: string | null }>
  deviceOptions: Array<{ id: string; name: string | null }>
  extensionOptions: MetaflowExtensionOption[]
}>()
const actions = defineModel<MetaflowAction[]>({ required: true })
const triggerOptions: ListboxOptionValue[] = [
  { value: 'number', label: 'Number', description: 'Exact DTMF sequence' },
  { value: 'pattern', label: 'Pattern', description: 'Regular-expression trigger' },
]

function error(index: number, field: string): string | null {
  return props.fieldErrors[`metaflows.actions.${index}.${field}`]?.[0] ?? null
}

function add(): void {
  actions.value = [...actions.value, { trigger_type: 'number', trigger: '', ...newMetaflowNode() }]
}

function setTriggerType(index: number, value: ListboxValue): void {
  if (value !== 'number' && value !== 'pattern') return
  actions.value = actions.value.map((action, actionIndex) =>
    actionIndex === index ? { ...action, trigger_type: value } : action,
  )
}

function setTrigger(index: number, trigger: string | number): void {
  actions.value = actions.value.map((action, actionIndex) =>
    actionIndex === index ? { ...action, trigger: String(trigger) } : action,
  )
}

function replaceAction(index: number, action: MetaflowNode | MetaflowChild): void {
  actions.value = actions.value.map((current, actionIndex) =>
    actionIndex === index ? (action as MetaflowAction) : current,
  )
}

function removeAction(index: number): void {
  actions.value = actions.value.filter((_, actionIndex) => actionIndex !== index)
}
</script>

<template>
  <div class="grid gap-3 sm:col-span-3">
    <div class="flex items-center justify-between">
      <div>
        <h4 class="text-xs font-semibold text-slate-700">Guided action trees</h4>
        <p class="mt-1 text-[10px] text-slate-400">
          Build recursive branches and select projected resources without raw Switch identifiers.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
        @click="add"
      >
        <PlusIcon class="size-3.5" /> Add action
      </button>
    </div>

    <div
      v-if="lockedActionCount"
      class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-800"
    >
      {{ lockedActionCount }} unsupported or unprojected action tree(s) are preserved read-only.
    </div>

    <article
      v-for="(action, index) in actions"
      :key="index"
      class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2"
    >
      <label class="grid gap-1">
        <span class="text-[11px] font-semibold text-slate-500">Trigger type</span>
        <FormListbox
          :model-value="action.trigger_type"
          :options="triggerOptions"
          :invalid="Boolean(error(index, 'trigger_type'))"
          aria-label="Metaflow trigger type"
          @update:model-value="setTriggerType(index, $event)"
        />
      </label>
      <FormInput
        :model-value="action.trigger"
        label="Trigger"
        maxlength="255"
        input-class="font-mono"
        :error="error(index, 'trigger')"
        :placeholder="action.trigger_type === 'pattern' ? '^9([0-9]+)$' : '1'"
        @update:model-value="setTrigger(index, $event)"
      />
      <MetaflowNodeEditor
        :model-value="action"
        :path="`metaflows.actions.${index}`"
        :field-errors="fieldErrors"
        :media-options="mediaOptions"
        :callflow-options="callflowOptions"
        :device-options="deviceOptions"
        :extension-options="extensionOptions"
        class="sm:col-span-2"
        @update:model-value="replaceAction(index, $event)"
      />
      <button
        type="button"
        class="inline-flex items-center justify-center gap-1 rounded-md border border-red-100 bg-white px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50 sm:col-span-2"
        @click="removeAction(index)"
      >
        <TrashIcon class="size-3.5" /> Remove action tree
      </button>
    </article>

    <p v-if="actions.length === 0" class="rounded-md bg-slate-50 p-3 text-[11px] text-slate-500">
      No editable metaflow action trees are configured.
    </p>
  </div>
</template>
