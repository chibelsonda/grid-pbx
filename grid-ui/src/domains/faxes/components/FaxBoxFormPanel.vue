<script setup lang="ts">
import { computed, ref } from 'vue'
import { PrinterIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox, { type ListboxValue } from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
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
const { form, validate, validationErrors } = useFaxBoxForm(props.record)
const { ownerOptions, callerIdOptions, timezoneOptions } = useFaxBoxFormOptions(
  () => props.options,
  () => form.owner_id,
  () => form.caller_id,
  () => form.fax_timezone,
)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

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

  if (result.success) emit('save', result.data)
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
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><PrinterIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Fax box</h2>
              <p class="text-[10px] text-slate-400">Identity and account ownership.</p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Name</span
              ><input
                v-model="form.name"
                aria-label="Fax-box name"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('name'))"
                :aria-invalid="Boolean(fieldError('name'))"
              /><span v-if="fieldError('name')" class="text-[10px] text-danger">{{
                fieldError('name')
              }}</span></label
            ><label class="grid gap-2 sm:col-span-2"
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
            ><label class="grid gap-2"
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
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Caller ID name</span
              ><input
                v-model="form.caller_name"
                aria-label="Caller ID name"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('caller_name'))"
                :aria-invalid="Boolean(fieldError('caller_name'))"
              /><span v-if="fieldError('caller_name')" class="text-[10px] text-danger">{{
                fieldError('caller_name')
              }}</span></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Fax header</span
              ><input
                v-model="form.fax_header"
                aria-label="Fax header"
                maxlength="128"
                class="field-control"
                :class="validationControlClass(fieldError('fax_header'))"
                :aria-invalid="Boolean(fieldError('fax_header'))"
              /><span v-if="fieldError('fax_header')" class="text-[10px] text-danger">{{
                fieldError('fax_header')
              }}</span></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Fax identity number</span
              ><input
                v-model="form.fax_identity"
                aria-label="Fax identity number"
                maxlength="64"
                class="field-control"
                :class="validationControlClass(fieldError('fax_identity'))"
                :aria-invalid="Boolean(fieldError('fax_identity'))"
              /><span v-if="fieldError('fax_identity')" class="text-[10px] text-danger">{{
                fieldError('fax_identity')
              }}</span></label
            ><label class="grid gap-2"
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
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Retries</span
              ><input
                v-model.number="form.retries"
                aria-label="Fax retries"
                type="number"
                min="0"
                max="4"
                class="field-control"
                :class="validationControlClass(fieldError('retries'))"
                :aria-invalid="Boolean(fieldError('retries'))"
              /><span v-if="fieldError('retries')" class="text-[10px] text-danger">{{
                fieldError('retries')
              }}</span></label
            ><ToggleSwitch
              v-model="form.t38_enabled"
              label="Enable T.38 when supported"
              class="sm:col-span-2"
              :invalid="Boolean(fieldError('t38_enabled'))"
            />
          </div>
        </article>
        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Email delivery</h2>
            <p class="mt-1 text-[10px] text-slate-400">
              The generated SMTP address is read-only; custom addresses must be unique.
            </p>
          </header>
          <div class="grid gap-4 p-5">
            <div
              v-if="record?.smtp_email_address"
              class="rounded-md bg-slate-50 p-3 text-xs text-slate-600"
            >
              <span class="font-semibold">Generated address:</span> {{ record.smtp_email_address }}
            </div>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Custom SMTP address</span
              ><input
                v-model="form.custom_smtp_email_address"
                aria-label="Custom SMTP address"
                type="email"
                class="field-control"
                :class="validationControlClass(fieldError('custom_smtp_email_address'))"
                :aria-invalid="Boolean(fieldError('custom_smtp_email_address'))"
              /><span
                v-if="fieldError('custom_smtp_email_address')"
                class="text-[10px] text-danger"
                >{{ fieldError('custom_smtp_email_address') }}</span
              ></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Allowed sender patterns</span
              ><textarea
                v-model="form.smtpPermissionsText"
                aria-label="Allowed sender patterns"
                rows="3"
                class="field-control min-h-24 py-2"
                :class="validationControlClass(fieldError('smtp_permission_list'))"
                :aria-invalid="Boolean(fieldError('smtp_permission_list'))"
                placeholder="One regular expression per line"
              /><span v-if="fieldError('smtp_permission_list')" class="text-[10px] text-danger">{{
                fieldError('smtp_permission_list')
              }}</span></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Inbound notification emails</span
              ><input
                v-model="form.inboundEmailsText"
                aria-label="Inbound notification emails"
                class="field-control"
                :class="validationControlClass(fieldError('inbound_notification_emails'))"
                :aria-invalid="Boolean(fieldError('inbound_notification_emails'))"
                placeholder="ops@example.com, owner@example.com"
              /><span
                v-if="fieldError('inbound_notification_emails')"
                class="text-[10px] text-danger"
                >{{ fieldError('inbound_notification_emails') }}</span
              ></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Outbound notification emails</span
              ><input
                v-model="form.outboundEmailsText"
                aria-label="Outbound notification emails"
                class="field-control"
                :class="validationControlClass(fieldError('outbound_notification_emails'))"
                :aria-invalid="Boolean(fieldError('outbound_notification_emails'))"
              /><span
                v-if="fieldError('outbound_notification_emails')"
                class="text-[10px] text-danger"
                >{{ fieldError('outbound_notification_emails') }}</span
              ></label
            >
          </div>
        </article>
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
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
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
