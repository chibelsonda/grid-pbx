<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue'
import { CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

defineProps<{
  show: boolean
  title: string
  message: string
}>()

defineEmits<{ dismiss: [] }>()
</script>

<template>
  <TransitionRoot
    :show="show"
    as="template"
    enter="transition duration-200 ease-out"
    enter-from="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    enter-to="translate-y-0 opacity-100 sm:translate-x-0"
    leave="transition duration-150 ease-in"
    leave-from="opacity-100"
    leave-to="opacity-0"
  >
    <div
      class="pointer-events-none fixed inset-x-4 top-[76px] z-[60] flex justify-center sm:justify-end"
    >
      <div
        data-testid="global-notification"
        role="status"
        aria-live="polite"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-emerald-200 bg-white px-4 py-3 shadow-xl ring-1 ring-slate-900/5"
      >
        <CheckCircleIcon class="mt-0.5 size-5 shrink-0 text-emerald-600" aria-hidden="true" />
        <div class="min-w-0 flex-1">
          <p class="text-xs font-semibold text-slate-800">{{ title }}</p>
          <p class="mt-0.5 text-[11px] text-slate-600">{{ message }}</p>
        </div>
        <button
          type="button"
          aria-label="Dismiss notification"
          class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
          @click="$emit('dismiss')"
        >
          <XMarkIcon class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>
  </TransitionRoot>
</template>
