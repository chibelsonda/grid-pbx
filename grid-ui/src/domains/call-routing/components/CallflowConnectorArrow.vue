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
    class="h-9 w-5 shrink-0"
    :class="colorClass"
    viewBox="0 0 20 44"
  >
    <line
      x1="10"
      y1="0"
      x2="10"
      y2="32"
      stroke="currentColor"
      stroke-width="6"
      vector-effect="non-scaling-stroke"
    />
    <path d="M1 30H19L10 44Z" fill="currentColor" />
  </svg>
</template>
