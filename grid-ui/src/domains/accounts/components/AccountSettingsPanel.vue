<script setup lang="ts">
import { computed } from 'vue'
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useAccountSettingsForm } from '../composables/useAccountSettingsForm'
import type { AccountDetail, AccountSettingsInput } from '../types/account'
import type { ListboxOptionValue, ListboxValue } from '@/shared/components/FormListbox.vue'

const props = defineProps<{
  account: AccountDetail
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; save: [input: AccountSettingsInput] }>()
const { form, validate, validationErrors } = useAccountSettingsForm(props.account)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const timezoneOptions = computed(() => {
  const supported =
    typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : []

  return [
    { value: '', label: 'Use Switch default' },
    ...[...new Set([form.timezone, 'UTC', ...supported].filter(Boolean))].map((value) => ({
      value,
      label: value,
    })),
  ]
})
const languageOptions = [
  { value: '', label: 'Use Switch default' },
  { value: 'en-US', label: 'English (United States)' },
  { value: 'en-GB', label: 'English (United Kingdom)' },
  { value: 'es-US', label: 'Spanish (United States)' },
  { value: 'fr-FR', label: 'French' },
]
const privacyOptions = [
  { value: 'none', label: 'Show name and number' },
  { value: 'name', label: 'Hide name' },
  { value: 'number', label: 'Hide number' },
  { value: 'full', label: 'Hide name and number' },
]
const callerIdOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Use Switch default' },
  ...props.account.options.caller_id_numbers.map((option) => ({
    value: option.id,
    label: option.number,
    description: option.display_name,
  })),
])
const emergencyCallerIdOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Use Switch default' },
  ...props.account.options.caller_id_numbers
    .filter((option) => option.e911_enabled)
    .map((option) => ({
      value: option.id,
      label: option.number,
      description: option.display_name,
    })),
])

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function submit(): void {
  const result = validate()
  if (result.success) emit('save', result.data)
}

function selectCallerId(scope: 'external' | 'emergency', value: ListboxValue): void {
  form.caller_id[scope].phone_number_id = typeof value === 'string' ? value : null
  form.caller_id[scope].preserve_number = false
}
</script>

<template>
  <CrudSlideOver
    title="Edit account settings"
    eyebrow="GridPBX / Accounts"
    description="Only schema-audited settings are written to Switch. Realm and operational controls remain protected."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <BuildingOffice2Icon class="size-5 text-brand-500" />
          <h2 class="text-sm font-semibold text-slate-700">Identity and locale</h2>
        </header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Account name</span>
            <input
              v-model="form.name"
              aria-label="Account name"
              class="field-control"
              :class="validationControlClass(fieldError('name'))"
              :aria-invalid="Boolean(fieldError('name'))"
            />
            <span v-if="fieldError('name')" class="text-[10px] text-danger">{{
              fieldError('name')
            }}</span>
          </label>
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Legal organization</span>
            <input
              v-model="form.organization_name"
              aria-label="Legal organization"
              class="field-control"
              :class="validationControlClass(fieldError('organization_name'))"
              :aria-invalid="Boolean(fieldError('organization_name'))"
            />
            <span v-if="fieldError('organization_name')" class="text-[10px] text-danger">{{
              fieldError('organization_name')
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Timezone</span>
            <FormListbox
              v-model="form.timezone"
              aria-label="Timezone"
              :options="timezoneOptions"
              :invalid="Boolean(fieldError('timezone'))"
              placeholder="Use Switch default"
            />
            <span v-if="fieldError('timezone')" class="text-[10px] text-danger">{{
              fieldError('timezone')
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Language</span>
            <FormListbox
              v-model="form.language"
              aria-label="Language"
              :options="languageOptions"
              :invalid="Boolean(fieldError('language'))"
            />
            <span v-if="fieldError('language')" class="text-[10px] text-danger">{{
              fieldError('language')
            }}</span>
          </label>
        </div>
      </article>

      <article class="card-surface grid gap-4 p-5">
        <h2 class="text-sm font-semibold text-slate-700">Calling defaults</h2>
        <ToggleSwitch
          v-model="form.call_waiting_enabled"
          label="Call waiting"
          description="Allow a second inbound call while a call is active."
        />
        <ToggleSwitch
          v-model="form.show_rate"
          label="Show outbound rate"
          description="Allow supported clients to display the outbound rate."
        />
        <ToggleSwitch
          v-model="form.do_not_disturb_enabled"
          label="Do not disturb"
          description="Send inbound calls through the account's unavailable path."
        />
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Outbound caller privacy</span>
          <FormListbox
            v-model="form.outbound_privacy"
            aria-label="Outbound caller privacy"
            :options="privacyOptions"
            :invalid="Boolean(fieldError('outbound_privacy'))"
          />
          <span v-if="fieldError('outbound_privacy')" class="text-[10px] text-danger">{{
            fieldError('outbound_privacy')
          }}</span>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">Internal ringtone</span
            ><input
              v-model="form.ringtone_internal"
              aria-label="Internal ringtone"
              class="field-control"
              :class="validationControlClass(fieldError('ringtone_internal'))"
              :aria-invalid="Boolean(fieldError('ringtone_internal'))"
            /><span v-if="fieldError('ringtone_internal')" class="text-[10px] text-danger">{{
              fieldError('ringtone_internal')
            }}</span></label
          >
          <label class="grid gap-2"
            ><span class="text-xs font-semibold text-slate-600">External ringtone</span
            ><input
              v-model="form.ringtone_external"
              aria-label="External ringtone"
              class="field-control"
              :class="validationControlClass(fieldError('ringtone_external'))"
              :aria-invalid="Boolean(fieldError('ringtone_external'))"
            /><span v-if="fieldError('ringtone_external')" class="text-[10px] text-danger">{{
              fieldError('ringtone_external')
            }}</span></label
          >
        </div>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Default caller identity</h2>
          <p class="mt-1 text-[10px] text-slate-500">
            External choices are account-owned numbers. Emergency choices require E911.
          </p>
        </header>
        <div class="grid gap-5 p-5">
          <section class="grid gap-4 rounded-md border border-slate-200 p-4 sm:grid-cols-2">
            <h3 class="text-xs font-semibold text-slate-700 sm:col-span-2">Internal calls</h3>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Name</span>
              <input
                v-model="form.caller_id.internal.name"
                aria-label="Internal caller ID name"
                maxlength="35"
                class="field-control"
                :class="validationControlClass(fieldError('caller_id.internal.name'))"
                :aria-invalid="Boolean(fieldError('caller_id.internal.name'))"
              />
              <span v-if="fieldError('caller_id.internal.name')" class="text-[10px] text-danger">{{
                fieldError('caller_id.internal.name')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Number</span>
              <input
                v-model="form.caller_id.internal.number"
                aria-label="Internal caller ID number"
                maxlength="35"
                class="field-control"
                :class="validationControlClass(fieldError('caller_id.internal.number'))"
                :aria-invalid="Boolean(fieldError('caller_id.internal.number'))"
              />
              <span
                v-if="fieldError('caller_id.internal.number')"
                class="text-[10px] text-danger"
                >{{ fieldError('caller_id.internal.number') }}</span
              >
            </label>
          </section>

          <section
            v-for="scope in ['external', 'emergency'] as const"
            :key="scope"
            class="grid gap-4 rounded-md border border-slate-200 p-4 sm:grid-cols-2"
          >
            <h3 class="text-xs font-semibold capitalize text-slate-700 sm:col-span-2">
              {{ scope }} calls
            </h3>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Name</span>
              <input
                v-model="form.caller_id[scope].name"
                :aria-label="`${scope} caller ID name`"
                maxlength="35"
                class="field-control"
                :class="validationControlClass(fieldError(`caller_id.${scope}.name`))"
                :aria-invalid="Boolean(fieldError(`caller_id.${scope}.name`))"
              />
              <span v-if="fieldError(`caller_id.${scope}.name`)" class="text-[10px] text-danger">{{
                fieldError(`caller_id.${scope}.name`)
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Phone number</span>
              <FormListbox
                :model-value="form.caller_id[scope].phone_number_id"
                :aria-label="`${scope} caller ID number`"
                :options="scope === 'emergency' ? emergencyCallerIdOptions : callerIdOptions"
                :invalid="Boolean(fieldError(`caller_id.${scope}.phone_number_id`))"
                @update:model-value="selectCallerId(scope, $event)"
              />
              <span
                v-if="fieldError(`caller_id.${scope}.phone_number_id`)"
                class="text-[10px] text-danger"
                >{{ fieldError(`caller_id.${scope}.phone_number_id`) }}</span
              >
            </label>
            <div
              v-if="account.configuration.caller_id[scope].unresolved"
              class="rounded-md border border-amber-200 bg-amber-50 p-3 sm:col-span-2"
            >
              <p class="text-[10px] leading-4 text-amber-800">
                The current number {{ account.configuration.caller_id[scope].number }} is not in the
                projected inventory.
              </p>
              <ToggleSwitch
                v-model="form.caller_id[scope].preserve_number"
                class="mt-3"
                label="Keep current unresolved number"
                description="Turn this off to clear it, or select a projected number above."
              />
            </div>
          </section>
        </div>
      </article>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save settings' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
