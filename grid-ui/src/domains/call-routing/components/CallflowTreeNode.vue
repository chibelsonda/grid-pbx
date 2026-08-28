<script setup lang="ts">
import { computed } from 'vue'
import { ArrowDownIcon } from '@heroicons/vue/24/outline'
import type { CallflowNode } from '../types/callRouting'

defineOptions({ name: 'CallflowTreeNode' })
const props = withDefaults(defineProps<{ node: CallflowNode; branch?: string; depth?: number }>(), {
  branch: '',
  depth: 1,
})
const children = computed(() => Object.entries(props.node.children))

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}
</script>

<template>
  <div class="relative">
    <div
      v-if="branch"
      class="mb-2 flex items-center gap-2 pl-3 text-[10px] font-semibold text-slate-400"
    >
      <ArrowDownIcon class="size-3" />
      <span>{{ branch === '_' ? 'Default branch' : `Branch: ${branch}` }}</span>
    </div>
    <div class="rounded-md border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div class="flex items-center gap-3">
        <span
          class="grid size-8 place-items-center rounded-md bg-brand-50 text-xs font-bold text-brand-600"
          >{{ depth }}</span
        >
        <div>
          <p class="text-xs font-semibold text-slate-700">{{ humanize(node.module) }}</p>
          <p class="mt-0.5 font-mono text-[9px] text-slate-400">{{ node.module }}</p>
          <p v-if="node.target" class="mt-1 text-[10px] font-semibold text-brand-600">
            {{ node.target.label }}
          </p>
          <p
            v-else-if="node.reference_status === 'unresolved'"
            class="mt-1 text-[10px] font-semibold text-amber-600"
          >
            Referenced target is not projected
          </p>
        </div>
      </div>
    </div>
    <div v-if="children.length" class="ml-4 grid gap-3 border-l border-slate-200 py-3 pl-4">
      <CallflowTreeNode
        v-for="[childBranch, child] in children"
        :key="childBranch"
        :node="child"
        :branch="childBranch"
        :depth="depth + 1"
      />
    </div>
  </div>
</template>
