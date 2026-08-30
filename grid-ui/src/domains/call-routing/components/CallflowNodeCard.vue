<script setup lang="ts">
import type { Component } from 'vue'
import { ArrowsPointingOutIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{
    variant?: 'editor' | 'palette'
    label: string
    module: string
    icon: Component
    borderClass: string
    iconClass: string
    detail?: string | null
    movable?: boolean
  }>(),
  {
    variant: 'editor',
    detail: null,
    movable: false,
  },
)
</script>

<template>
  <div
    class="relative h-full w-full overflow-hidden rounded-md border bg-callflow-node text-white shadow-sm"
    :class="borderClass"
  >
    <svg
      aria-hidden="true"
      class="pointer-events-none absolute inset-x-0 top-0 h-10 w-full"
      viewBox="0 0 144 40"
      preserveAspectRatio="none"
    >
      <path d="M0 0H144V25C103 35 43 35 0 26Z" fill="currentColor" class="text-white/[0.06]" />
      <g fill="currentColor" class="text-white/20">
        <circle v-for="index in 18" :key="index" :cx="10 + index * 5" cy="7" r="1" />
      </g>
    </svg>

    <ArrowsPointingOutIcon
      v-if="movable && variant === 'editor'"
      aria-hidden="true"
      class="absolute top-1 right-1 size-3 text-white/70"
    />

    <div
      class="absolute inset-x-1 flex items-center justify-center"
      :class="variant === 'editor' ? 'top-5 gap-1.5' : 'top-2.5 flex-col gap-1'"
    >
      <component
        :is="icon"
        class="shrink-0 drop-shadow-sm"
        :class="[iconClass, variant === 'editor' ? 'size-6' : 'size-5']"
      />
      <span
        class="line-clamp-2 text-center font-semibold tracking-wide text-white uppercase"
        :class="
          variant === 'editor'
            ? 'max-w-24 text-[9px] leading-3'
            : 'max-w-[68px] text-[8px] leading-[10px]'
        "
      >
        {{ label }}
      </span>
    </div>

    <footer
      v-if="variant === 'editor'"
      class="absolute inset-x-0 bottom-0 flex h-5 items-center justify-center border-t border-white/10 bg-black/15 px-1.5"
    >
      <span class="block max-w-full truncate text-center text-[8px] font-medium text-slate-200">
        {{ detail || module }}
      </span>
    </footer>
  </div>
</template>
