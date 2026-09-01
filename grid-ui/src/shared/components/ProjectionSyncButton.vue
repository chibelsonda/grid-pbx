<script setup lang="ts">
import { ArrowPathIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{
    synchronizing: boolean
    disabled?: boolean
  }>(),
  { disabled: false },
)

defineEmits<{ sync: [] }>()
</script>

<template>
  <button
    type="button"
    :disabled="disabled || synchronizing"
    :aria-busy="synchronizing"
    class="inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
    @click="$emit('sync')"
  >
    <ArrowPathIcon class="size-4" :class="synchronizing && 'animate-spin'" aria-hidden="true" />
    {{ synchronizing ? 'Synchronizing…' : 'Sync from Switch' }}
  </button>
</template>
