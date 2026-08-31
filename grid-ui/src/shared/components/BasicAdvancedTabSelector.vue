<script setup lang="ts">
import { Squares2X2Icon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import FormTabBar from './FormTabBar.vue'

withDefaults(defineProps<{ sticky?: boolean; ariaLabel?: string }>(), {
  sticky: false,
  ariaLabel: 'Form sections',
})

const selectedIndex = defineModel<number>({ default: 0 })
const tabs = [
  { key: 'basic', label: 'Basic', icon: Squares2X2Icon },
  { key: 'advanced', label: 'Advanced', icon: WrenchScrewdriverIcon },
] as const
</script>

<template>
  <FormTabBar v-model="selectedIndex" :tabs="tabs" :aria-label="ariaLabel" :sticky="sticky" />
</template>
