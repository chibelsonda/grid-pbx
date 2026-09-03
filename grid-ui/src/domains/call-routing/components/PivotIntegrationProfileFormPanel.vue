<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  pivotIntegrationProfileSchema,
  type ValidPivotIntegrationProfileForm,
} from '../schemas/pivotIntegrationProfileSchema'
import type {
  PivotIntegrationProfile,
  PivotIntegrationProfileInput,
} from '../types/callflowIntegrationProfile'
import CallflowIntegrationProfileIdentityFields from './CallflowIntegrationProfileIdentityFields.vue'

const props = defineProps<{
  profile: PivotIntegrationProfile | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  save: [input: PivotIntegrationProfileInput]
}>()

const bodyFormatOptions: ListboxOptionValue[] = [
  { value: 'form', label: 'Form encoded' },
  { value: 'json', label: 'JSON' },
]
const form = reactive<ValidPivotIntegrationProfileForm>({
  name: props.profile?.name ?? '',
  is_active: props.profile?.is_active ?? true,
  voice_url: '',
  cdr_url: '',
  methods: props.profile?.configuration.methods.length
    ? [...props.profile.configuration.methods]
    : ['post'],
  formats: props.profile?.configuration.formats.length
    ? [...props.profile.configuration.formats]
    : ['switch'],
  req_body_format: 'json',
  req_timeout_ms: 5000,
  headers: [],
})
const validationErrors = ref<FormErrors>({})
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? errors.value[`settings.${field}`]?.[0] ?? null
}

function updateMethods(value: boolean | string[]): void {
  if (Array.isArray(value))
    form.methods = value.filter((method) => method === 'get' || method === 'post')
}

function updateFormats(value: boolean | string[]): void {
  if (Array.isArray(value)) {
    form.formats = value.filter((format) => format === 'switch' || format === 'twiml')
  }
}

function updateBodyFormat(value: ListboxValue): void {
  if (value === 'form' || value === 'json') form.req_body_format = value
}

function addHeader(): void {
  if (form.headers.length < 20) form.headers.push({ name: '', value: '' })
}

function submit(): void {
  const result = validateForm(pivotIntegrationProfileSchema, form)
  validationErrors.value = result.errors
  if (!result.success) return

  emit('save', {
    integration_type: 'pivot',
    name: result.data.name,
    is_active: result.data.is_active,
    settings: {
      voice_url: result.data.voice_url,
      cdr_url: result.data.cdr_url || null,
      methods: result.data.methods,
      formats: result.data.formats,
      req_body_format: result.data.req_body_format,
      req_timeout_ms: result.data.req_timeout_ms,
      custom_request_headers: Object.fromEntries(
        result.data.headers.map((header) => [header.name, header.value]),
      ),
    },
  })
}
</script>

<template>
  <CrudSlideOver
    :title="profile ? 'Replace Pivot profile' : 'Create Pivot profile'"
    eyebrow="GridPBX / Settings / Callflow integrations"
    description="Private URLs and request headers are encrypted at rest and never returned to the browser after saving."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the Pivot integration"
      />

      <CallflowIntegrationProfileIdentityFields
        v-model:name="form.name"
        v-model:is-active="form.is_active"
        action-label="Pivot"
        :name-error="fieldError('name')"
      />

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Private endpoints</h2>
          <p class="mt-1 text-[10px] leading-4 text-heading-description">
            Only public HTTPS destinations are accepted. Redirect and DNS egress controls remain an
            infrastructure responsibility.
          </p>
        </header>
        <div class="grid gap-4 p-5">
          <FormInput
            v-model="form.voice_url"
            label="Voice URL"
            type="url"
            required
            maxlength="2048"
            autocomplete="off"
            placeholder="https://voice.example.com/pivot"
            :error="fieldError('voice_url')"
          />
          <FormInput
            v-model="form.cdr_url"
            label="CDR callback URL"
            type="url"
            maxlength="2048"
            autocomplete="off"
            placeholder="Optional HTTPS callback"
            :error="fieldError('cdr_url')"
          />
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Allowed callflow choices</h2>
          <p class="mt-1 text-[10px] leading-4 text-heading-description">
            Editors can choose only the methods and response formats approved here.
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
          <fieldset class="grid gap-3">
            <legend class="text-xs font-semibold text-slate-600">Response formats</legend>
            <div class="grid gap-3 sm:grid-cols-2">
              <FormCheckbox
                :model-value="form.formats"
                value="switch"
                label="Switch"
                variant="compact"
                :error="fieldError('formats')"
                @update:model-value="updateFormats"
              />
              <FormCheckbox
                :model-value="form.formats"
                value="twiml"
                label="TwiML"
                variant="compact"
                :error="fieldError('formats')"
                @update:model-value="updateFormats"
              />
            </div>
          </fieldset>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid content-start gap-2">
              <span class="text-xs font-semibold text-slate-600">POST body format</span>
              <FormListbox
                :model-value="form.req_body_format"
                :options="bodyFormatOptions"
                aria-label="POST body format"
                :invalid="Boolean(fieldError('req_body_format'))"
                @update:model-value="updateBodyFormat"
              />
              <span v-if="fieldError('req_body_format')" class="text-[10px] text-danger">
                {{ fieldError('req_body_format') }}
              </span>
            </label>
            <FormInput
              v-model.number="form.req_timeout_ms"
              label="Request timeout (ms)"
              type="number"
              min="1"
              max="5000"
              required
              :error="fieldError('req_timeout_ms')"
            />
          </div>
          <p
            class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-[10px] leading-4 text-slate-600"
          >
            Pivot debug persistence is always disabled by GridPBX because request and response
            bodies can contain caller and call-control data.
          </p>
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Private request headers</h2>
            <p class="mt-1 text-[10px] leading-4 text-heading-description">
              Optional X- prefixed headers. Values are write-only after saving.
            </p>
          </div>
          <button
            type="button"
            :disabled="form.headers.length >= 20"
            class="ml-auto inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600 hover:border-brand-200 hover:bg-brand-50 disabled:opacity-50"
            @click="addHeader"
          >
            <PlusIcon class="size-4" /> Add header
          </button>
        </header>
        <div class="grid gap-3 p-5">
          <p v-if="form.headers.length === 0" class="text-xs text-slate-500">
            No private request headers configured.
          </p>
          <div
            v-for="(header, index) in form.headers"
            :key="index"
            class="grid gap-3 rounded-md border border-slate-200 bg-slate-50/50 p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_36px]"
          >
            <FormInput
              v-model="header.name"
              label="Header name"
              placeholder="X-Api-Key"
              autocomplete="off"
              :error="fieldError(`headers.${index}.name`)"
            />
            <FormInput
              v-model="header.value"
              label="Header value"
              type="password"
              autocomplete="new-password"
              :error="fieldError(`headers.${index}.value`)"
            />
            <button
              type="button"
              class="mt-7 grid size-9 place-items-center rounded-md border border-red-200 bg-white text-danger hover:bg-red-50"
              :aria-label="`Remove header ${index + 1}`"
              @click="form.headers.splice(index, 1)"
            >
              <TrashIcon class="size-4" />
            </button>
          </div>
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
          {{ saving ? 'Saving…' : profile ? 'Replace configuration' : 'Create profile' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
