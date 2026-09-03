<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  disaIntegrationProfileSchema,
  type ValidDisaIntegrationProfileForm,
} from '../schemas/disaIntegrationProfileSchema'
import type {
  DisaIntegrationProfile,
  DisaIntegrationProfileInput,
} from '../types/callflowIntegrationProfile'
import CallflowIntegrationProfileIdentityFields from './CallflowIntegrationProfileIdentityFields.vue'

const props = defineProps<{
  profile: DisaIntegrationProfile | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  save: [input: DisaIntegrationProfileInput]
}>()

const audioOptions: ListboxOptionValue[] = [
  { value: 'dialtone', label: 'Dial tone' },
  { value: 'ringing', label: 'Ringing tone' },
]
const form = reactive<ValidDisaIntegrationProfileForm>({
  name: props.profile?.name ?? '',
  is_active: props.profile?.is_active ?? true,
  pin: '',
  pin_confirmation: '',
  retries: props.profile?.configuration.retries ?? 2,
  interdigit_ms: props.profile?.configuration.interdigit_ms ?? 3000,
  max_digits: props.profile?.configuration.max_digits ?? 15,
  preconnect_audio: props.profile?.configuration.preconnect_audio ?? 'dialtone',
})
const validationErrors = ref<FormErrors>({})
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? errors.value[`settings.${field}`]?.[0] ?? null
}

function updateAudio(value: ListboxValue): void {
  if (value === 'dialtone' || value === 'ringing') form.preconnect_audio = value
}

function submit(): void {
  const result = validateForm(disaIntegrationProfileSchema, form)
  validationErrors.value = result.errors
  if (!result.success) return

  emit('save', {
    integration_type: 'disa',
    name: result.data.name,
    is_active: result.data.is_active,
    settings: {
      pin: result.data.pin,
      retries: result.data.retries,
      interdigit_ms: result.data.interdigit_ms,
      max_digits: result.data.max_digits,
      preconnect_audio: result.data.preconnect_audio,
    },
  })
}
</script>

<template>
  <CrudSlideOver
    :title="profile ? 'Replace DISA access policy' : 'Create DISA access policy'"
    eyebrow="GridPBX / Settings / Callflow integrations"
    description="The PIN is encrypted and write-only. Callflow editors select only this policy's public UUID."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the DISA integration"
      />

      <CallflowIntegrationProfileIdentityFields
        v-model:name="form.name"
        v-model:is-active="form.is_active"
        action-label="DISA"
        :name-error="fieldError('name')"
      />

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Write-only credential</h2>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            Replacing a policy requires a new PIN. Existing PINs are never returned to the browser.
          </p>
        </header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <FormInput
            v-model="form.pin"
            label="DISA PIN"
            type="password"
            inputmode="numeric"
            autocomplete="new-password"
            minlength="8"
            maxlength="12"
            required
            :error="fieldError('pin')"
          />
          <FormInput
            v-model="form.pin_confirmation"
            label="Confirm DISA PIN"
            type="password"
            inputmode="numeric"
            autocomplete="new-password"
            minlength="8"
            maxlength="12"
            required
            :error="fieldError('pin_confirmation')"
          />
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Bounded native policy</h2>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            Account call restrictions are always enforced and the original caller ID is always
            retained.
          </p>
        </header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <FormInput
            v-model.number="form.retries"
            label="Attempts per call"
            type="number"
            min="1"
            max="3"
            required
            :error="fieldError('retries')"
          />
          <FormInput
            v-model.number="form.interdigit_ms"
            label="Interdigit timeout (ms)"
            type="number"
            min="1000"
            max="5000"
            step="1000"
            required
            :error="fieldError('interdigit_ms')"
          />
          <FormInput
            v-model.number="form.max_digits"
            label="Maximum destination digits"
            type="number"
            min="3"
            max="15"
            required
            :error="fieldError('max_digits')"
          />
          <label class="grid content-start gap-2">
            <span class="text-xs font-semibold text-slate-600">Pre-connect audio</span>
            <FormListbox
              :model-value="form.preconnect_audio"
              :options="audioOptions"
              aria-label="Pre-connect audio"
              :invalid="Boolean(fieldError('preconnect_audio'))"
              @update:model-value="updateAudio"
            />
            <span v-if="fieldError('preconnect_audio')" class="text-[10px] text-danger">{{
              fieldError('preconnect_audio')
            }}</span>
          </label>
        </div>
      </article>

      <p
        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-[10px] leading-4 text-amber-900"
      >
        Saving this encrypted profile does not enable DISA by itself. The action remains locked
        until a live carrier/SBC guard reports persistent lockout, rate and concurrency limits,
        destination enforcement, redacted monitoring, and an available emergency stop.
      </p>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <button
          type="button"
          class="h-9 rounded-md border border-slate-300 px-4 text-xs font-semibold text-slate-600"
          :disabled="saving"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm disabled:opacity-60"
          :disabled="saving"
        >
          {{ saving ? 'Saving…' : 'Save DISA policy' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
