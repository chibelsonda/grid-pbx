<script setup lang="ts">
import { computed } from 'vue'
import { ClockIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(
  defineProps<{
    lastSynchronizedAt: string | null
    status?: string | null
    detail?: string | null
  }>(),
  { status: null, detail: null },
)

const failed = computed(() => props.status === 'error' || props.status === 'failed')
const synchronizedAt = computed(() => {
  if (!props.lastSynchronizedAt) return null

  const value = new Date(props.lastSynchronizedAt)
  if (Number.isNaN(value.getTime())) return null

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(value)
})
const label = computed(() => {
  if (failed.value) {
    return synchronizedAt.value
      ? `Last synchronization failed · Last successful ${synchronizedAt.value}`
      : 'Last synchronization failed'
  }

  return synchronizedAt.value ? `Last synchronized ${synchronizedAt.value}` : 'Not synchronized yet'
})
</script>

<template>
  <p
    data-testid="projection-freshness"
    class="inline-flex items-center gap-1.5 text-[10px] leading-4 font-medium"
    :class="failed ? 'text-danger' : 'text-slate-500'"
    role="status"
    aria-live="polite"
  >
    <ExclamationCircleIcon v-if="failed" class="size-3.5 shrink-0" aria-hidden="true" />
    <ClockIcon v-else class="size-3.5 shrink-0 text-slate-400" aria-hidden="true" />
    <span
      >{{ label }}<template v-if="detail"> · {{ detail }}</template></span
    >
  </p>
</template>
