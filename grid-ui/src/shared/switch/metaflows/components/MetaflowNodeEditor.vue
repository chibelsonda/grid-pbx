<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { metaflowDefinition, metaflowModuleOptions, newMetaflowNode } from '../catalog'
import type { MetaflowChild, MetaflowExtensionOption, MetaflowModule, MetaflowNode } from '../types'

defineOptions({ name: 'MetaflowNodeEditor' })
const props = defineProps<{
  path: string
  fieldErrors: Record<string, string[]>
  showKey?: boolean
  mediaOptions: Array<{ id: string; name: string | null }>
  callflowOptions: Array<{ id: string; name: string | null; description: string | null }>
  deviceOptions: Array<{ id: string; name: string | null }>
  extensionOptions: MetaflowExtensionOption[]
}>()
const node = defineModel<MetaflowNode | MetaflowChild>({ required: true })
const emit = defineEmits<{ remove: [] }>()
const branchKey = computed({
  get: () => ('key' in node.value ? node.value.key : ''),
  set: (value: string) => {
    if ('key' in node.value) node.value = { ...node.value, key: value }
  },
})

function error(field: string): string | null {
  return props.fieldErrors[`${props.path}.${field}`]?.[0] ?? null
}

function setModule(value: ListboxValue): void {
  if (!metaflowModuleOptions.some((module) => module.value === value)) return
  const module = value as MetaflowModule
  node.value = {
    ...node.value,
    module,
    data: { ...metaflowDefinition(module).defaults },
  }
}

function setData(field: string, value: ListboxValue): void {
  node.value = { ...node.value, data: { ...node.value.data, [field]: value } }
}

function setInputData(field: string, value: string | number, numeric = false): void {
  setData(field, numeric ? (value === '' ? null : Number(value)) : value)
}

function resourceOptions(resource?: string): ListboxOptionValue[] {
  if (resource === 'media') {
    return props.mediaOptions.map((item) => ({
      value: item.id,
      label: item.name || 'Untitled media',
    }))
  }
  if (resource === 'callflow') {
    return props.callflowOptions.map((item) => ({
      value: item.id,
      label: item.name || 'Untitled callflow',
      description: item.description,
    }))
  }
  if (resource === 'device') {
    return props.deviceOptions.map((item) => ({
      value: item.id,
      label: item.name || 'Unnamed device',
    }))
  }

  return props.extensionOptions.map((item) => ({
    value: item.id,
    label: item.display_name,
    description: item.extension ? `Extension ${item.extension}` : null,
  }))
}

function addChild(): void {
  node.value = {
    ...node.value,
    children: [...node.value.children, { key: 'success', ...newMetaflowNode() }],
  }
}

function replaceChild(index: number, child: MetaflowNode | MetaflowChild): void {
  node.value = {
    ...node.value,
    children: node.value.children.map((current, childIndex) =>
      childIndex === index ? (child as MetaflowChild) : current,
    ),
  }
}

function removeChild(index: number): void {
  node.value = {
    ...node.value,
    children: node.value.children.filter((_, childIndex) => childIndex !== index),
  }
}
</script>

<template>
  <div class="grid gap-3 rounded-md border border-slate-200 bg-white p-3 sm:grid-cols-2">
    <FormInput
      v-if="showKey"
      v-model="branchKey"
      class="sm:col-span-2"
      label="Branch key"
      maxlength="64"
      input-class="font-mono"
      :error="error('key')"
      placeholder="success"
    />

    <label class="grid gap-1 sm:col-span-2">
      <span class="text-[11px] font-semibold text-slate-500">Action</span>
      <FormListbox
        :model-value="node.module"
        :options="metaflowModuleOptions"
        :invalid="Boolean(error('module'))"
        aria-label="Metaflow action"
        @update:model-value="setModule"
      />
    </label>

    <template v-for="field in metaflowDefinition(node.module).fields" :key="field.key">
      <ToggleSwitch
        v-if="field.type === 'boolean'"
        :model-value="Boolean(node.data[field.key])"
        :label="field.label"
        class="rounded-md border border-slate-200 px-3 py-2.5"
        @update:model-value="setData(field.key, $event)"
      />
      <div v-else class="grid gap-1">
        <span
          v-if="field.type === 'select' || field.type === 'resource'"
          class="text-[11px] font-semibold text-slate-500"
          >{{ field.label }}</span
        >
        <FormListbox
          v-if="field.type === 'select' || field.type === 'resource'"
          :model-value="node.data[field.key] ?? null"
          :options="
            field.type === 'resource' ? resourceOptions(field.resource) : (field.options ?? [])
          "
          :invalid="Boolean(error(`data.${field.key}`))"
          :placeholder="field.type === 'resource' ? 'Select a projected resource' : 'Select…'"
          :aria-label="field.label"
          @update:model-value="setData(field.key, $event)"
        />
        <FormInput
          v-else-if="field.type === 'number'"
          :model-value="(node.data[field.key] as string | number | null) ?? null"
          :label="field.label"
          type="number"
          :min="field.min"
          :max="field.max"
          step="any"
          :error="error(`data.${field.key}`)"
          @update:model-value="setInputData(field.key, $event, true)"
        />
        <FormInput
          v-else
          :model-value="(node.data[field.key] as string | number | null) ?? null"
          :label="field.label"
          maxlength="2048"
          :error="error(`data.${field.key}`)"
          @update:model-value="setInputData(field.key, $event)"
        />
      </div>
    </template>

    <section class="grid gap-3 border-l-2 border-brand-100 pl-3 sm:col-span-2">
      <div class="flex items-center justify-between gap-3">
        <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
          Next branches
        </p>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
          @click="addChild"
        >
          <PlusIcon class="size-3.5" /> Add branch
        </button>
      </div>
      <MetaflowNodeEditor
        v-for="(child, index) in node.children"
        :key="index"
        :model-value="child"
        :path="`${path}.children.${index}`"
        :field-errors="fieldErrors"
        :media-options="mediaOptions"
        :callflow-options="callflowOptions"
        :device-options="deviceOptions"
        :extension-options="extensionOptions"
        show-key
        @update:model-value="replaceChild(index, $event)"
        @remove="removeChild(index)"
      />
      <p v-if="node.children.length === 0" class="text-[11px] text-slate-400">
        No child branches; this action ends the sequence.
      </p>
    </section>

    <button
      v-if="showKey"
      type="button"
      class="inline-flex items-center justify-center gap-1 rounded-md border border-red-100 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50 sm:col-span-2"
      @click="emit('remove')"
    >
      <TrashIcon class="size-3.5" /> Remove branch
    </button>
  </div>
</template>
