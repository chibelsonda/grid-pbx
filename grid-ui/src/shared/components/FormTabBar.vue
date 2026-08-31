<script setup lang="ts">
import type { Component } from 'vue'
import { Tab, TabGroup, TabList } from '@headlessui/vue'

withDefaults(
  defineProps<{
    tabs: readonly { key: string; label: string; icon?: Component }[]
    ariaLabel?: string
    sticky?: boolean
  }>(),
  {
    ariaLabel: 'Form sections',
    sticky: false,
  },
)

const selectedIndex = defineModel<number>({ default: 0 })
</script>

<template>
  <TabGroup as="template" :selected-index="selectedIndex" @change="selectedIndex = $event">
    <TabList
      :aria-label="ariaLabel"
      class="card-surface flex gap-1 overflow-x-auto px-4 pt-3"
      :class="{ 'sticky top-0 z-30 bg-white/95 backdrop-blur': sticky }"
    >
      <Tab v-for="tab in tabs" :key="tab.key" v-slot="{ selected }" as="template">
        <button
          type="button"
          class="inline-flex h-10 shrink-0 items-center gap-2 border-b-2 px-3 text-xs font-semibold outline-none transition focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2"
          :class="
            selected
              ? 'border-brand-500 text-brand-700'
              : 'border-transparent text-slate-500 hover:text-slate-700'
          "
        >
          <component :is="tab.icon" v-if="tab.icon" class="size-4" />
          {{ tab.label }}
        </button>
      </Tab>
    </TabList>
  </TabGroup>
</template>
