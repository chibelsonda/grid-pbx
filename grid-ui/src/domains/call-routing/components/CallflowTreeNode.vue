<script setup lang="ts">
import { computed } from 'vue'
import {
  ArrowDownIcon,
  ArrowPathRoundedSquareIcon,
  ClockIcon,
  DevicePhoneMobileIcon,
  ListBulletIcon,
  MusicalNoteIcon,
  QueueListIcon,
  QuestionMarkCircleIcon,
  Squares2X2Icon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import type { CallflowNode, CallflowNodeSelection } from '../types/callRouting'

defineOptions({ name: 'CallflowTreeNode' })
const props = withDefaults(
  defineProps<{
    node: CallflowNode
    depth?: number
    path?: string[]
    selectedPath?: string[]
  }>(),
  {
    depth: 1,
    path: () => [],
  },
)
const emit = defineEmits<{ select: [selection: CallflowNodeSelection] }>()
const depth = computed(() => props.depth)
const selected = computed(
  () =>
    props.selectedPath !== undefined &&
    props.selectedPath.length === props.path.length &&
    props.selectedPath.every((segment, index) => segment === props.path[index]),
)
const children = computed(() => Object.entries(props.node.children))

function selectNode(): void {
  emit('select', { node: props.node, path: [...props.path] })
}

function childPath(childKey: string): string[] {
  return [...props.path, childKey]
}

function forwardSelection(selection: CallflowNodeSelection): void {
  emit('select', selection)
}

const moduleIcon = computed(() => {
  switch (props.node.module) {
    case 'user':
      return UserIcon
    case 'device':
      return DevicePhoneMobileIcon
    case 'play':
      return MusicalNoteIcon
    case 'menu':
      return ListBulletIcon
    case 'group':
    case 'acdc_member':
    case 'acdc_queue':
      return QueueListIcon
    case 'temporal_route':
      return ClockIcon
    case 'callflow':
      return ArrowPathRoundedSquareIcon
    default:
      return props.node.reference_status === 'unresolved' ? QuestionMarkCircleIcon : Squares2X2Icon
  }
})
const branchClass = computed(() => {
  switch (props.node.branch?.kind) {
    case 'schedule_match':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'key':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'preserved':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    default:
      return 'border-slate-300 bg-white text-slate-600'
  }
})

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}
</script>

<template>
  <div class="flex min-w-52 flex-col items-center">
    <div v-if="node.branch" class="mb-2 flex flex-col items-center gap-1">
      <ArrowDownIcon class="size-3 text-slate-400" />
      <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold" :class="branchClass">
        {{ node.branch.label }}
      </span>
    </div>
    <button
      type="button"
      role="treeitem"
      :aria-level="depth"
      :aria-selected="selected"
      :aria-label="`${humanize(node.module)}${node.target ? `: ${node.target.label}` : ''}`"
      class="w-52 rounded-lg border bg-white text-left shadow-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      :class="
        selected
          ? 'border-brand-500 ring-2 ring-brand-100'
          : node.reference_status === 'unresolved'
            ? 'border-amber-300'
            : 'border-slate-300 hover:border-brand-300'
      "
      @click="selectNode"
    >
      <header class="flex items-center gap-3 border-b border-slate-200 px-3 py-2.5">
        <span
          class="grid size-8 shrink-0 place-items-center rounded-md"
          :class="
            node.reference_status === 'unresolved'
              ? 'bg-amber-50 text-amber-600'
              : 'bg-brand-50 text-brand-600'
          "
        >
          <component :is="moduleIcon" class="size-4" />
        </span>
        <div class="min-w-0">
          <p class="truncate text-xs font-semibold text-slate-800">{{ humanize(node.module) }}</p>
          <p class="mt-0.5 font-mono text-[9px] text-slate-500">{{ node.module }}</p>
        </div>
        <span class="ml-auto text-[9px] font-bold text-slate-400">{{ depth }}</span>
      </header>
      <div class="min-h-12 px-3 py-2.5">
        <p v-if="node.target" class="truncate text-[10px] font-semibold text-brand-700">
          {{ node.target.label }}
        </p>
        <p
          v-else-if="node.reference_status === 'unresolved'"
          class="text-[10px] font-semibold leading-4 text-amber-700"
        >
          Target is not projected
        </p>
        <p v-else class="text-[10px] leading-4 text-slate-500">Inline Switch action</p>
      </div>
    </button>

    <div v-if="children.length" role="group" class="flex flex-col items-center">
      <div class="h-5 w-px bg-slate-300"></div>
      <div class="flex items-center gap-3">
        <div v-if="children.length > 1" class="h-px w-8 bg-slate-300"></div>
        <span
          class="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[9px] font-semibold text-slate-500"
        >
          {{ children.length }} {{ children.length === 1 ? 'path' : 'paths' }}
        </span>
        <div v-if="children.length > 1" class="h-px w-8 bg-slate-300"></div>
      </div>
      <div class="flex items-start gap-5 pt-2">
        <CallflowTreeNode
          v-for="[childKey, child] in children"
          :key="childKey"
          :node="child"
          :depth="depth + 1"
          :path="childPath(childKey)"
          :selected-path="selectedPath"
          @select="forwardSelection"
        />
      </div>
    </div>
  </div>
</template>
