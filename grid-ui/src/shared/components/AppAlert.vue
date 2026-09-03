<script setup lang="ts">
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { TransitionRoot } from '@headlessui/vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { AppNotificationTone } from '@/shared/types/appNotification'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    show?: boolean
    title?: string
    message: string
    tone?: AppNotificationTone
    resetKey?: string | number
    compact?: boolean
    dismissible?: boolean
    dismissLabel?: string
    autoClose?: boolean
    duration?: number
  }>(),
  {
    show: true,
    title: '',
    tone: 'info',
    compact: false,
    dismissible: true,
    dismissLabel: 'Dismiss alert',
    autoClose: false,
    duration: 4_000,
  },
)

const emit = defineEmits<{ dismiss: [] }>()
const visible = ref(false)
const paused = ref(false)
let dismissTimer: ReturnType<typeof window.setTimeout> | null = null

const toneClass = computed(
  () =>
    ({
      info: 'app-notification-info',
      success: 'app-notification-success',
      warning: 'app-notification-warning',
      error: 'app-notification-error',
    })[props.tone],
)
const alertIcon = computed(
  () =>
    ({
      info: InformationCircleIcon,
      success: CheckCircleIcon,
      warning: ExclamationTriangleIcon,
      error: XCircleIcon,
    })[props.tone],
)
const assertive = computed(() => props.tone === 'warning' || props.tone === 'error')
const widthClass = computed(() => (props.compact ? 'max-w-sm' : 'max-w-lg'))

function clearDismissTimer(): void {
  if (dismissTimer === null) return

  window.clearTimeout(dismissTimer)
  dismissTimer = null
}

function dismiss(): void {
  clearDismissTimer()
  visible.value = false
  emit('dismiss')
}

function scheduleDismiss(): void {
  clearDismissTimer()
  if (!visible.value || !props.autoClose || props.duration <= 0 || paused.value) return

  dismissTimer = window.setTimeout(dismiss, props.duration)
}

function pauseDismiss(): void {
  paused.value = true
  clearDismissTimer()
}

function resumeDismiss(): void {
  paused.value = false
  scheduleDismiss()
}

watch(
  [() => props.show, () => props.message, () => props.resetKey],
  ([show, message]) => {
    visible.value = show && message.trim().length > 0
    scheduleDismiss()
  },
  { immediate: true },
)

onBeforeUnmount(clearDismissTimer)
</script>

<template>
  <TransitionRoot
    :show="visible"
    as="template"
    enter="transition duration-200 ease-out"
    enter-from="translate-y-1 opacity-0"
    enter-to="translate-y-0 opacity-100"
    leave="transition duration-150 ease-in"
    leave-from="opacity-100"
    leave-to="opacity-0"
  >
    <div
      v-bind="$attrs"
      :data-tone="tone"
      :role="assertive ? 'alert' : 'status'"
      :aria-live="assertive ? 'assertive' : 'polite'"
      class="app-notification relative flex w-full items-start gap-3 overflow-hidden rounded-lg border px-3.5 py-3 shadow-card ring-1 ring-slate-900/5"
      :class="[toneClass, widthClass]"
      @mouseenter="pauseDismiss"
      @mouseleave="resumeDismiss"
      @focusin="pauseDismiss"
      @focusout="resumeDismiss"
    >
      <span class="app-alert-icon grid size-8 shrink-0 place-items-center rounded-full">
        <component :is="alertIcon" class="app-notification-accent size-4.5" aria-hidden="true" />
      </span>
      <div class="min-w-0 flex-1 self-center">
        <p v-if="title" class="app-notification-title text-xs font-semibold">{{ title }}</p>
        <p
          class="app-notification-message text-[11px] leading-5"
          :class="title && 'mt-0.5'"
        >
          {{ message }}
        </p>
      </div>
      <button
        v-if="dismissible"
        type="button"
        :aria-label="dismissLabel"
        class="app-notification-dismiss grid size-7 shrink-0 place-items-center rounded-full transition"
        @click="dismiss"
      >
        <XMarkIcon class="size-4" aria-hidden="true" />
      </button>
    </div>
  </TransitionRoot>
</template>
