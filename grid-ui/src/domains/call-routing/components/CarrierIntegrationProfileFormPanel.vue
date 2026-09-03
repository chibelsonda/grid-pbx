<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  carrierIntegrationProfileSchema,
  type ValidCarrierIntegrationProfileForm,
} from '../schemas/carrierIntegrationProfileSchema'
import type {
  CarrierIntegrationProfile,
  CarrierIntegrationProfileInput,
} from '../types/callflowIntegrationProfile'
import CallflowIntegrationProfileIdentityFields from './CallflowIntegrationProfileIdentityFields.vue'

const props = defineProps<{
  type: 'global_carrier' | 'account_carrier'
  profile: CarrierIntegrationProfile | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  save: [input: CarrierIntegrationProfileInput]
}>()

const isGlobal = computed(() => props.type === 'global_carrier')
const label = computed(() => (isGlobal.value ? 'Global Carrier' : 'Account Carrier'))
const form = reactive<ValidCarrierIntegrationProfileForm>({
  integration_type: props.type,
  name: props.profile?.name ?? '',
  is_active: props.profile?.is_active ?? true,
  route_scope: props.profile?.configuration.route_scope ?? (isGlobal.value ? 'global' : 'account'),
})
const validationErrors = ref<FormErrors>({})
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const scopeOptions = [
  {
    value: 'account',
    label: 'Current account resources',
    description: 'Use resources owned by this Switch account.',
  },
  {
    value: 'reseller',
    label: 'Projected reseller resources',
    description: 'Resolve the nearest projected reseller privately on the server.',
  },
]

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? errors.value[`settings.${field}`]?.[0] ?? null
}

function submit(): void {
  const result = validateForm(carrierIntegrationProfileSchema, form)
  validationErrors.value = result.errors
  if (!result.success) return

  emit('save', {
    integration_type: result.data.integration_type,
    name: result.data.name,
    is_active: result.data.is_active,
    settings:
      result.data.integration_type === 'account_carrier'
        ? { scope: result.data.route_scope as 'account' | 'reseller' }
        : {},
  })
}
</script>

<template>
  <CrudSlideOver
    :title="profile ? `Replace ${label} profile` : `Create ${label} profile`"
    eyebrow="GridPBX / Settings / Callflow integrations"
    description="Carrier routing is enabled only through an explicit account-scoped authorization profile."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the carrier integration"
      />

      <CallflowIntegrationProfileIdentityFields
        v-model:name="form.name"
        v-model:is-active="form.is_active"
        :action-label="label"
        :name-error="fieldError('name')"
      />

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Routing authorization</h2>
          <p class="mt-1 text-[10px] leading-4 text-heading-description">
            GridPBX stores no dial target, carrier identifier, or raw Switch account identifier in
            this profile.
          </p>
        </header>
        <div class="grid gap-4 p-5">
          <div
            v-if="isGlobal"
            class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-[10px] leading-4 text-amber-900"
          >
            This authorizes the Switch-managed global carrier pool as a terminal callflow action.
            Carrier selection and outbound policy remain controlled by Switch.
          </div>
          <label v-else class="grid gap-2">
            <span class="text-xs font-semibold text-slate-700">Resource scope</span>
            <FormListbox
              :model-value="form.route_scope"
              :options="scopeOptions"
              aria-label="Carrier resource scope"
              :invalid="Boolean(fieldError('scope') ?? fieldError('route_scope'))"
              @update:model-value="form.route_scope = $event as 'account' | 'reseller'"
            />
            <span
              v-if="fieldError('scope') ?? fieldError('route_scope')"
              class="text-[10px] font-medium text-danger"
            >
              {{ fieldError('scope') ?? fieldError('route_scope') }}
            </span>
          </label>
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
          {{ saving ? 'Saving…' : profile ? 'Replace authorization' : 'Create profile' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
