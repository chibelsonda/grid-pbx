<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

defineProps<{
  label: string
  to?: RouteLocationRaw
}>()

defineEmits<{
  click: []
}>()
</script>

<template>
  <RouterLink
    v-if="to"
    :to="to"
    :aria-label="label"
    class="inline-flex items-center gap-1.5 font-semibold text-slate-500 transition hover:text-brand-600 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
  >
    <ArrowLeftIcon class="size-3.5" />
    <span>{{ label }}</span>
  </RouterLink>
  <button
    v-else
    type="button"
    :aria-label="label"
    class="inline-flex items-center gap-1.5 font-semibold text-slate-500 transition hover:text-brand-600 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
    @click="$emit('click')"
  >
    <ArrowLeftIcon class="size-3.5" />
    <span>{{ label }}</span>
  </button>
</template>
