<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  label: string
  value: string
  detail: string
  icon: Component
  tone: 'primary' | 'success' | 'warning' | 'info'
}>()

const toneClasses = {
  primary: 'bg-brand-50 text-brand-500 ring-brand-100',
  success: 'bg-emerald-50 text-success ring-emerald-100',
  warning: 'bg-amber-50 text-warning ring-amber-100',
  info: 'bg-sky-50 text-info ring-sky-100',
}
</script>

<template>
  <article class="card-surface relative overflow-hidden p-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[11px] font-bold tracking-[0.07em] text-slate-400 uppercase">{{ label }}</p>
        <p class="mt-2 text-[28px] leading-none font-semibold tracking-tight text-slate-800">
          {{ value }}
        </p>
        <p class="mt-3 text-xs text-slate-500">{{ detail }}</p>
      </div>
      <span class="grid size-11 shrink-0 place-items-center rounded-full ring-1" :class="toneClasses[tone]">
        <component :is="icon" class="size-5" />
      </span>
    </div>
  </article>
</template>
