<script setup lang="ts">
import { computed } from 'vue'
import type { AgentAvailabilityStatus } from '../types/queue'

const props = defineProps<{ status: AgentAvailabilityStatus | null }>()
const presentation = computed(() => {
  switch (props.status) {
    case 'ready':
    case 'logged_in':
      return { label: 'Available', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' }
    case 'connecting':
      return { label: 'Connecting', className: 'border-sky-200 bg-sky-50 text-sky-700' }
    case 'connected':
      return { label: 'On call', className: 'border-blue-200 bg-blue-50 text-blue-700' }
    case 'outbound':
      return { label: 'Outbound', className: 'border-blue-200 bg-blue-50 text-blue-700' }
    case 'wrapup':
      return { label: 'Wrap-up', className: 'border-amber-200 bg-amber-50 text-amber-700' }
    case 'paused':
      return { label: 'Paused', className: 'border-amber-200 bg-amber-50 text-amber-700' }
    case 'logged_out':
      return { label: 'Logged out', className: 'border-slate-200 bg-slate-50 text-slate-600' }
    default:
      return { label: 'Unknown', className: 'border-slate-200 bg-white text-slate-500' }
  }
})
</script>

<template>
  <span
    class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold"
    :class="presentation.className"
  >
    {{ presentation.label }}
  </span>
</template>
