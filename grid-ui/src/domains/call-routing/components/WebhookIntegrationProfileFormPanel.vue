<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  webhookIntegrationProfileSchema,
  type ValidWebhookIntegrationProfileForm,
} from '../schemas/webhookIntegrationProfileSchema'
import type {
  WebhookIntegrationProfile,
  WebhookIntegrationProfileInput,
} from '../types/callflowIntegrationProfile'
import CallflowIntegrationProfileIdentityFields from './CallflowIntegrationProfileIdentityFields.vue'

const props = defineProps<{
  profile: WebhookIntegrationProfile | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  save: [input: WebhookIntegrationProfileInput]
}>()

const form = reactive<ValidWebhookIntegrationProfileForm>({
  name: props.profile?.name ?? '',
  is_active: props.profile?.is_active ?? true,
  uri: '',
  methods: props.profile?.configuration.methods.length
    ? [...props.profile.configuration.methods]
    : ['post'],
  max_retries: props.profile?.configuration.max_retries ?? 3,
})
const validationErrors = ref<FormErrors>({})
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? errors.value[`settings.${field}`]?.[0] ?? null
}

function updateMethods(value: boolean | string[]): void {
  if (Array.isArray(value)) {
    form.methods = value.filter((method) => method === 'get' || method === 'post')
  }
}

function submit(): void {
  const result = validateForm(webhookIntegrationProfileSchema, form)
  validationErrors.value = result.errors
  if (!result.success) return

  emit('save', {
    integration_type: 'webhook',
    name: result.data.name,
    is_active: result.data.is_active,
    settings: {
      uri: result.data.uri,
      methods: result.data.methods,
      max_retries: result.data.max_retries,
    },
  })
}
</script>

<template>
  <CrudSlideOver
    :title="profile ? 'Replace Webhook profile' : 'Add Webhook profile'"
    eyebrow="GridPBX / Settings / Callflow integrations"
    description="The private HTTPS destination is encrypted at rest and never returned after saving."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <p
        v-if="error"
        role="alert"
        class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ error }}
      </p>

      <CallflowIntegrationProfileIdentityFields
        v-model:name="form.name"
        v-model:is-active="form.is_active"
        action-label="Webhook"
        :name-error="fieldError('name')"
      />

      <p
        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-[10px] leading-4 text-amber-900"
      >
        Activating this profile enables the Webhook action for this account. Before activation,
        verify deployment egress, redirects, TLS, timeouts, signed minimal payloads, replay
        protection, retries, retention, and the emergency disable procedure.
      </p>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Private destination</h2>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            Use a controlled public HTTPS receiver. The Switch sends call context to this endpoint;
            DNS, redirects, TLS verification, and egress filtering must also be enforced by the
            deployment.
          </p>
        </header>
        <div class="p-5">
          <FormInput
            v-model="form.uri"
            label="Webhook URL"
            type="url"
            required
            maxlength="2048"
            autocomplete="off"
            placeholder="https://events.example.com/calls"
            :error="fieldError('uri')"
          />
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Allowed callflow choices</h2>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            Editors can use only the methods and retry ceiling approved here.
          </p>
        </header>
        <div class="grid gap-5 p-5">
          <fieldset class="grid gap-3">
            <legend class="text-xs font-semibold text-slate-600">Request methods</legend>
            <div class="grid gap-3 sm:grid-cols-2">
              <FormCheckbox
                :model-value="form.methods"
                value="get"
                label="GET"
                variant="compact"
                :error="fieldError('methods')"
                @update:model-value="updateMethods"
              />
              <FormCheckbox
                :model-value="form.methods"
                value="post"
                label="POST"
                variant="compact"
                :error="fieldError('methods')"
                @update:model-value="updateMethods"
              />
            </div>
          </fieldset>
          <FormInput
            v-model.number="form.max_retries"
            label="Maximum attempts"
            type="number"
            min="1"
            max="5"
            required
            :error="fieldError('max_retries')"
          />
        </div>
      </article>

      <div
        class="sticky bottom-0 flex justify-end gap-3 border-t border-slate-200 bg-slate-50/95 py-3 backdrop-blur"
      >
        <button
          type="button"
          :disabled="saving"
          class="h-9 rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-600 disabled:opacity-50"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-wait disabled:opacity-60"
        >
          {{ saving ? 'Saving…' : profile ? 'Replace configuration' : 'Add profile' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
