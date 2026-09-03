<script setup lang="ts">
import AppAlert from '@/shared/components/AppAlert.vue'
import type { AppNotificationTone } from '@/shared/types/appNotification'

const props = withDefaults(
  defineProps<{
    show: boolean
    title: string
    message: string
    tone?: AppNotificationTone
    notificationKey?: string | number
  }>(),
  { tone: 'info' },
)

defineEmits<{ dismiss: [] }>()
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-4 top-[76px] z-[60] flex justify-center sm:justify-end"
  >
    <AppAlert
      data-testid="global-notification"
      :show="show"
      :title="title"
      :message="message"
      :tone="tone"
      :reset-key="notificationKey"
      compact
      auto-close
      dismiss-label="Dismiss notification"
      class="pointer-events-auto shadow-xl"
      @dismiss="$emit('dismiss')"
    />
  </div>
</template>
