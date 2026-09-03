<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { PrinterIcon, TrashIcon } from '@heroicons/vue/24/outline'
import BasicAdvancedFormTabs from '@/shared/components/BasicAdvancedFormTabs.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, { type ListboxValue } from '@/shared/components/FormListbox.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { useFaxBoxForm } from '../composables/useFaxBoxForm'
import { useFaxBoxFormOptions } from '../composables/useFaxBoxFormOptions'
import type { FaxBox, FaxBoxInput, FaxBoxOptions } from '../types/fax'
const props = defineProps<{
  record: FaxBox | null
  options: FaxBoxOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: FaxBoxInput]; remove: [] }>()
const confirmDelete = ref(false)
const selectedTab = ref(0)
const { form, validate, validationErrors } = useFaxBoxForm(props.record)
const { ownerOptions, callerIdOptions, timezoneOptions } = useFaxBoxFormOptions(
  () => props.options,
  () => form.owner_id,
  () => form.caller_id,
  () => form.fax_timezone,
)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const basicFields = new Set([
  'name',
  'owner_id',
  'inbound_notification_emails',
  'outbound_notification_emails',
])

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

function hasBasicError(fieldErrors: Record<string, string[]>): boolean {
  return Object.entries(fieldErrors).some(
    ([field, messages]) => Boolean(messages[0]) && basicFields.has(field.split('.')[0] ?? field),
  )
}

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    if (Object.keys(fieldErrors).length === 0) return
    selectedTab.value = hasBasicError(fieldErrors) ? 0 : 1
  },
  { deep: true },
)

function setOwner(value: ListboxValue): void {
  if (value === null || typeof value === 'string') form.owner_id = value
}

function setCallerId(value: ListboxValue): void {
  if (value === null || typeof value === 'string') form.caller_id = value
}

function setTimezone(value: ListboxValue): void {
  if (value === null || typeof value === 'string') form.fax_timezone = value
}

function save(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)

    return
  }

  selectedTab.value = hasBasicError(validationErrors.value) ? 0 : 1
}
</script>
<template>
  <CrudSlideOver
    :title="!canManage ? 'View fax box' : record ? 'Edit fax box' : 'Create fax box'"
    eyebrow="GridPBX / Fax"
    description="Configure inbound fax identity, email delivery, and ownership."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="save">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the fax box"
      />
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <BasicAdvancedFormTabs v-model="selectedTab">
          <template #basic>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                  ><PrinterIcon class="size-5"
                /></span>
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Fax box</h2>
                  <p class="text-[10px] text-heading-description">Identity and account ownership.</p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model="form.name"
                  label="Name"
                  aria-label="Fax-box name"
                  class="sm:col-span-2"
                  maxlength="128"
                  :error="fieldError('name')"
                /><label class="grid gap-2 sm:col-span-2"
                  ><span class="text-xs font-semibold text-slate-600">Owner</span
                  ><FormListbox
                    :model-value="form.owner_id"
                    :options="ownerOptions"
                    aria-label="Fax-box owner"
                    :invalid="Boolean(fieldError('owner_id'))"
                    @update:model-value="setOwner"
                  /><span v-if="fieldError('owner_id')" class="text-[10px] text-danger">{{
                    fieldError('owner_id')
                  }}</span></label
                >
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Notification emails</h2>
                <p class="mt-1 text-[10px] text-heading-description">
                  Recipients for inbound and outbound Fax status.
                </p>
              </header>
              <div class="grid gap-4 p-5">
                <FormInput
                  v-model="form.inboundEmailsText"
                  label="Inbound notification emails"
                  placeholder="ops@example.com, owner@example.com"
                  :error="fieldError('inbound_notification_emails')"
                />
                <FormInput
                  v-model="form.outboundEmailsText"
                  label="Outbound notification emails"
                  :error="fieldError('outbound_notification_emails')"
                />
              </div>
            </article>
          </template>
          <template #advanced>
            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Fax identity</h2>
                <p class="mt-1 text-[10px] text-heading-description">
                  Caller ID and the identity printed on sent faxes.
                </p>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Caller ID number</span
                  ><FormListbox
                    :model-value="form.caller_id"
                    :options="callerIdOptions"
                    aria-label="Caller ID number"
                    :invalid="Boolean(fieldError('caller_id'))"
                    @update:model-value="setCallerId"
                  /><span v-if="fieldError('caller_id')" class="text-[10px] text-danger">{{
                    fieldError('caller_id')
                  }}</span></label
                ><FormInput
                  v-model="form.caller_name"
                  label="Caller ID name"
                  maxlength="128"
                  :error="fieldError('caller_name')"
                /><FormInput
                  v-model="form.fax_header"
                  label="Fax header"
                  maxlength="128"
                  :error="fieldError('fax_header')"
                /><FormInput
                  v-model="form.fax_identity"
                  label="Fax identity number"
                  maxlength="64"
                  :error="fieldError('fax_identity')"
                />
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">SMTP delivery</h2>
                <p class="mt-1 text-[10px] text-heading-description">
                  The generated address is read-only; custom addresses must be unique.
                </p>
              </header>
              <div class="grid gap-4 p-5">
                <div
                  v-if="record?.smtp_email_address"
                  class="rounded-md bg-slate-50 p-3 text-xs text-slate-600"
                >
                  <span class="font-semibold">Generated address:</span>
                  {{ record.smtp_email_address }}
                </div>
                <FormInput
                  v-model="form.custom_smtp_email_address"
                  label="Custom SMTP address"
                  type="email"
                  :error="fieldError('custom_smtp_email_address')"
                />
                <FormTextarea
                  v-model="form.smtpPermissionsText"
                  label="Allowed sender patterns"
                  size="compact"
                  placeholder="One regular expression per line"
                  :error="fieldError('smtp_permission_list')"
                />
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-700">Fax options</h2>
                <p class="mt-1 text-[10px] text-heading-description">
                  Retry, timezone, and T.38 behavior from the installed schema.
                </p>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormInput
                  v-model.number="form.retries"
                  label="Retries"
                  aria-label="Fax retries"
                  type="number"
                  min="0"
                  max="4"
                  :error="fieldError('retries')"
                /><label class="grid gap-2"
                  ><span class="text-xs font-semibold text-slate-600">Timezone</span
                  ><FormListbox
                    :model-value="form.fax_timezone"
                    :options="timezoneOptions"
                    aria-label="Fax timezone"
                    :invalid="Boolean(fieldError('fax_timezone'))"
                    @update:model-value="setTimezone"
                  /><span v-if="fieldError('fax_timezone')" class="text-[10px] text-danger">{{
                    fieldError('fax_timezone')
                  }}</span></label
                ><ToggleSwitch
                  v-model="form.t38_enabled"
                  label="Enable T.38 when supported"
                  class="sm:col-span-2"
                  :invalid="Boolean(fieldError('t38_enabled'))"
                />
              </div>
            </article>
          </template>
        </BasicAdvancedFormTabs>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete fax box
        </button>
      </div>
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 px-5 text-xs font-semibold"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save fax box' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete fax box"
    description="Historical faxes will be retained. Delete this fax box after checking its routing dependencies?"
    confirm-label="Delete fax box"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
