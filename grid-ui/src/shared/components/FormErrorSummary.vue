<script setup lang="ts">
import { computed } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { apiFieldErrorMessages, type ApiFieldErrors } from '@/shared/api/apiError'

const props = withDefaults(
  defineProps<{
    error?: string | null
    fieldErrors?: ApiFieldErrors
    title?: string
    errorId?: string | null
  }>(),
  {
    error: null,
    fieldErrors: () => ({}),
    title: 'Please review the highlighted fields',
    errorId: null,
  },
)

const messages = computed(() => apiFieldErrorMessages(props.fieldErrors))
const visible = computed(() => Boolean(props.error) || messages.value.length > 0)
</script>

<template>
  <section
    v-if="visible"
    role="alert"
    aria-live="assertive"
    data-testid="form-error-summary"
    class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800 shadow-sm"
  >
    <div class="flex items-start gap-3">
      <ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0 text-red-600" aria-hidden="true" />
      <div class="min-w-0">
        <h2 class="text-sm font-semibold">{{ title }}</h2>
        <p v-if="error" class="mt-1 text-xs leading-5">{{ error }}</p>
        <ul v-if="messages.length" class="mt-1.5 list-disc space-y-1 pl-4 text-xs leading-5">
          <li v-for="message in messages" :key="message">{{ message }}</li>
        </ul>
        <p v-if="errorId" class="mt-2 text-[10px] font-medium text-red-700">
          Support reference: {{ errorId }}
        </p>
      </div>
    </div>
  </section>
</template>
