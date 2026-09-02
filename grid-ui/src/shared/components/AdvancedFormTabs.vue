<script setup lang="ts">
import type { Component } from 'vue'
import FormTabBar from './FormTabBar.vue'

withDefaults(
  defineProps<{
    tabs: readonly { key: string; label: string; icon?: Component }[]
    ariaLabel?: string
    active?: boolean
    compact?: boolean
    sticky?: boolean
  }>(),
  {
    ariaLabel: 'Advanced form sections',
    active: true,
    compact: false,
    sticky: false,
  },
)

const selectedIndex = defineModel<number>({ default: 0 })
</script>

<template>
  <article :class="active ? 'card-surface overflow-visible' : 'contents'">
    <slot v-if="active" name="header" />
    <FormTabBar
      v-model="selectedIndex"
      :tabs="tabs"
      :aria-label="ariaLabel"
      :compact="compact"
      :sticky="sticky"
      :show-tab-list="active"
      embedded
    >
      <div :class="active ? 'grid gap-5 p-4 sm:p-5' : 'contents'">
        <slot />
      </div>
    </FormTabBar>
  </article>
</template>
