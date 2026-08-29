<script setup lang="ts">
import { reactive, ref } from 'vue'
import { PrinterIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
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
const lists = reactive({
  permissions: props.record?.smtp_permission_list.join('\n') ?? '',
  inbound: props.record?.inbound_notification_emails.join(', ') ?? '',
  outbound: props.record?.outbound_notification_emails.join(', ') ?? '',
})
const form = reactive<FaxBoxInput>({
  name: props.record?.name ?? '',
  owner_id: props.record?.owner?.id ?? null,
  caller_id: props.record?.caller_id ?? null,
  caller_name: props.record?.caller_name ?? null,
  fax_header: props.record?.fax_header ?? null,
  fax_identity: props.record?.fax_identity ?? null,
  fax_timezone: props.record?.fax_timezone ?? 'UTC',
  retries: props.record?.retries ?? 1,
  t38_enabled: props.record?.t38_enabled ?? false,
  custom_smtp_email_address: props.record?.custom_smtp_email_address ?? null,
  smtp_permission_list: [],
  inbound_notification_emails: [],
  outbound_notification_emails: [],
})
const split = (value: string): string[] => [
  ...new Set(
    value
      .split(/[\n,]+/)
      .map((item) => item.trim())
      .filter(Boolean),
  ),
]
function save(): void {
  emit('save', {
    ...form,
    smtp_permission_list: split(lists.permissions),
    inbound_notification_emails: split(lists.inbound),
    outbound_notification_emails: split(lists.outbound),
  })
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
    <form class="grid gap-5" @submit.prevent="canManage && save()">
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
                required
                maxlength="128"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                :aria-invalid="Boolean(fieldErrors.name)"
              /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{
                fieldErrors.name[0]
              }}</span></label
            ><label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Owner</span
              ><FormSelect
                v-model="form.owner_id"
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
                ><option :value="null">No owner</option>
                <option v-for="owner in options.owners" :key="owner.id" :value="owner.id">
                  {{ owner.label }}{{ owner.detail ? ` · ${owner.detail}` : '' }}
                </option></FormSelect
              ></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Caller ID number</span
              ><input
                v-model="form.caller_id"
                maxlength="64"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Caller ID name</span
              ><input
                v-model="form.caller_name"
                maxlength="128"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Fax header</span
              ><input
                v-model="form.fax_header"
                maxlength="128"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Fax identity number</span
              ><input
                v-model="form.fax_identity"
                maxlength="64"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Timezone</span
              ><input
                v-model="form.fax_timezone"
                maxlength="64"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Retries</span
              ><input
                v-model.number="form.retries"
                type="number"
                min="0"
                max="4"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><ToggleSwitch
              v-model="form.t38_enabled"
              label="Enable T.38 when supported"
              class="sm:col-span-2"
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
                type="email"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Allowed sender patterns</span
              ><textarea
                v-model="lists.permissions"
                rows="3"
                class="rounded-md border border-slate-200 p-3 text-xs"
                placeholder="One regular expression per line"
              /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Inbound notification emails</span
              ><input
                v-model="lists.inbound"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                placeholder="ops@example.com, owner@example.com" /></label
            ><label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Outbound notification emails</span
              ><input
                v-model="lists.outbound"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
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
          :disabled="saving || !form.name.trim()"
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
