<script setup lang="ts">
import BasicAdvancedTabSelector from './BasicAdvancedTabSelector.vue'

const selectedIndex = defineModel<number>({ default: 0 })
</script>

<template>
  <BasicAdvancedTabSelector v-model="selectedIndex" class="mb-5" />
  <div v-show="selectedIndex === 0" role="tabpanel" class="grid gap-5"><slot name="basic" /></div>
  <div v-show="selectedIndex === 1" role="tabpanel" class="grid gap-5">
    <slot name="advanced" />
  </div>
</template>
