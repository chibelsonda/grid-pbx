<script setup lang="ts">
withDefaults(
  defineProps<{
    colorClass?: string
  }>(),
  {
    colorClass: 'text-callflow-node',
  },
)
</script>

<template>
  <svg
    data-callflow-connector-arrow
    aria-hidden="true"
    class="h-10 w-5 shrink-0"
    :class="colorClass"
    viewBox="0 0 20 48"
  >
    <line
      x1="10"
      y1="0"
      x2="10"
      y2="36"
      stroke="currentColor"
      stroke-width="8"
      vector-effect="non-scaling-stroke"
    />
    <path d="M0 32H20L10 48Z" fill="currentColor" />
  </svg>
</template>
