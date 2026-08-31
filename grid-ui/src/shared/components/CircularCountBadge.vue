<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    count: number
    max?: number
    label?: string
  }>(),
  { max: 99, label: undefined },
)

const displayCount = computed(() =>
  props.count > props.max ? `${props.max}+` : String(props.count),
)
</script>

<template>
  <span
    class="inline-grid size-8 shrink-0 place-items-center rounded-full border text-[10px] leading-none font-bold tabular-nums"
    :aria-label="label ?? String(count)"
    :title="count > max ? String(count) : undefined"
  >
    {{ displayCount }}
  </span>
</template>
