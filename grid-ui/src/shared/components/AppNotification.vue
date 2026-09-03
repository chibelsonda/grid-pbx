<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { computed } from 'vue'
import type { AppNotificationTone } from '@/shared/types/appNotification'

const props = withDefaults(
  defineProps<{
    show: boolean
    title: string
    message: string
    tone?: AppNotificationTone
  }>(),
  { tone: 'info' },
)

const toneClass = computed(
  () =>
    ({
      info: 'app-notification-info',
      success: 'app-notification-success',
      warning: 'app-notification-warning',
      error: 'app-notification-error',
    })[props.tone],
)
const notificationIcon = computed(
  () =>
    ({
      info: InformationCircleIcon,
      success: CheckCircleIcon,
      warning: ExclamationTriangleIcon,
      error: XCircleIcon,
    })[props.tone],
)
const assertive = computed(() => props.tone === 'warning' || props.tone === 'error')

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
        :data-tone="tone"
        :role="assertive ? 'alert' : 'status'"
        :aria-live="assertive ? 'assertive' : 'polite'"
        class="app-notification pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 shadow-xl ring-1 ring-slate-900/5"
        :class="toneClass"
      >
        <component
          :is="notificationIcon"
          class="app-notification-accent mt-0.5 size-5 shrink-0"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="app-notification-title text-xs font-semibold">{{ title }}</p>
          <p class="app-notification-message mt-0.5 text-[11px]">{{ message }}</p>
        </div>
        <button
          type="button"
          aria-label="Dismiss notification"
          class="app-notification-dismiss shrink-0 rounded-md p-1 transition"
          @click="$emit('dismiss')"
        >
          <XMarkIcon class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>
  </TransitionRoot>
</template>
