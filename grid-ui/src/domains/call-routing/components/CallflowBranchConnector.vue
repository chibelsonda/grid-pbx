<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  kind: 'parent-stem' | 'first' | 'middle' | 'last'
}>()

const horizontalRange = computed(() => {
  switch (props.kind) {
    case 'first':
      return { start: 50, end: 101 }
    case 'last':
      return { start: -1, end: 50 }
    default:
      return { start: -1, end: 101 }
  }
})
</script>

<template>
  <svg
    v-if="kind === 'parent-stem'"
    data-callflow-parent-stem
    aria-hidden="true"
    class="h-3 w-5 shrink-0 text-callflow-node"
    viewBox="0 0 20 12"
  >
    <line
      x1="10"
      y1="0"
      x2="10"
      y2="12"
      stroke="currentColor"
      stroke-width="8"
      vector-effect="non-scaling-stroke"
    />
  </svg>
  <svg
    v-else
    data-callflow-branch-bus
    :data-callflow-branch-position="kind"
    aria-hidden="true"
    class="absolute left-0 top-0 h-2 w-full overflow-visible text-callflow-node"
    viewBox="0 0 100 8"
    preserveAspectRatio="none"
  >
    <line
      :x1="horizontalRange.start"
      y1="4"
      :x2="horizontalRange.end"
      y2="4"
      stroke="currentColor"
      stroke-width="8"
      vector-effect="non-scaling-stroke"
    />
  </svg>
</template>
