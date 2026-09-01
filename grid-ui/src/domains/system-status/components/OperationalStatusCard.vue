<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    iconClass?: string
  }>(),
  {
    iconClass: 'text-brand-600',
  },
)
</script>

<template>
  <article class="card-surface p-5" data-operational-status-card>
    <header class="flex items-start gap-2.5">
      <span
        class="flex size-5 shrink-0 items-center justify-center"
        :class="iconClass"
        aria-hidden="true"
        data-operational-status-card-icon
      >
        <slot name="icon" />
      </span>
      <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-800">{{ title }}</h2>
        <slot name="status" />
      </div>
    </header>
    <div class="mt-4" data-operational-status-card-body>
      <slot />
    </div>
  </article>
</template>
