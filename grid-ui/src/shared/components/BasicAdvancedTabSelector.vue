<script setup lang="ts">
import FormTabBar from './FormTabBar.vue'

withDefaults(defineProps<{ sticky?: boolean; ariaLabel?: string }>(), {
  sticky: false,
  ariaLabel: 'Form sections',
})

const selectedIndex = defineModel<number>({ default: 0 })
const tabs = [
  { key: 'basic', label: 'Basic' },
  { key: 'advanced', label: 'Advanced' },
] as const
</script>

<template>
  <FormTabBar
    v-model="selectedIndex"
    :tabs="tabs"
    :aria-label="ariaLabel"
    :sticky="sticky"
    variant="segmented"
  />
</template>
