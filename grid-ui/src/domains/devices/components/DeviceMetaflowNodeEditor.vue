<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { metaflowDefinition, metaflowModuleOptions, newMetaflowNode } from '../deviceMetaflows'
import type {
  DeviceMetaflowChild,
  DeviceMetaflowModule,
  DeviceMetaflowNode,
  ExtensionOption,
} from '../types/device'

defineOptions({ name: 'DeviceMetaflowNodeEditor' })
const props = defineProps<{
  node: DeviceMetaflowNode | DeviceMetaflowChild
  path: string
  fieldErrors: Record<string, string[]>
  showKey?: boolean
  mediaOptions: Array<{ id: string; name: string | null }>
  callflowOptions: Array<{ id: string; name: string | null; description: string | null }>
  deviceOptions: Array<{ id: string; name: string | null }>
  extensionOptions: ExtensionOption[]
}>()
const emit = defineEmits<{ remove: [] }>()
const branchKey = computed({
  get: () => ('key' in props.node ? props.node.key : ''),
  set: (value: string) => {
    if ('key' in props.node) props.node.key = value
  },
})

function error(field: string): string | null {
  return props.fieldErrors[`${props.path}.${field}`]?.[0] ?? null
}

function setModule(value: ListboxValue): void {
  if (!metaflowModuleOptions.some((module) => module.value === value)) return
  props.node.module = value as DeviceMetaflowModule
  props.node.data = { ...metaflowDefinition(props.node.module).defaults }
}

function setData(field: string, value: ListboxValue): void {
  props.node.data[field] = value
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
  props.node.children.push({ key: 'success', ...newMetaflowNode() })
}
</script>

<template>
  <div class="grid gap-3 rounded-md border border-slate-200 bg-white p-3 sm:grid-cols-2">
    <label v-if="showKey" class="grid gap-1 sm:col-span-2">
      <span class="text-[11px] font-semibold text-slate-500">Branch key</span>
      <input
        v-model="branchKey"
        maxlength="64"
        class="field-control font-mono"
        :class="validationControlClass(error('key'))"
        :aria-invalid="Boolean(error('key'))"
        placeholder="success"
      />
    </label>

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
        @update:model-value="node.data[field.key] = $event"
      />
      <label v-else class="grid gap-1">
        <span class="text-[11px] font-semibold text-slate-500">{{ field.label }}</span>
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
        <input
          v-else-if="field.type === 'number'"
          v-model.number="node.data[field.key]"
          type="number"
          :min="field.min"
          :max="field.max"
          step="any"
          class="field-control"
          :class="validationControlClass(error(`data.${field.key}`))"
          :aria-invalid="Boolean(error(`data.${field.key}`))"
        />
        <input
          v-else
          v-model="node.data[field.key]"
          maxlength="2048"
          class="field-control"
          :class="validationControlClass(error(`data.${field.key}`))"
          :aria-invalid="Boolean(error(`data.${field.key}`))"
        />
      </label>
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
      <DeviceMetaflowNodeEditor
        v-for="(child, index) in node.children"
        :key="index"
        :node="child"
        :path="`${path}.children.${index}`"
        :field-errors="fieldErrors"
        :media-options="mediaOptions"
        :callflow-options="callflowOptions"
        :device-options="deviceOptions"
        :extension-options="extensionOptions"
        show-key
        @remove="node.children.splice(index, 1)"
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
