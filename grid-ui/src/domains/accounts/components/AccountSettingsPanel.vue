<script setup lang="ts">
import { computed } from 'vue'
import { BuildingOffice2Icon, ShieldExclamationIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { useAccountSettingsForm } from '../composables/useAccountSettingsForm'
import AccountCallRecordingSettings from './AccountCallRecordingSettings.vue'
import AccountRoutingSettings from './AccountRoutingSettings.vue'
import AccountPreflowMetaflowSettings from './AccountPreflowMetaflowSettings.vue'
import type {
  AccountCallflowOption,
  AccountDetail,
  AccountRestrictionOption,
  AccountSettingsInput,
} from '../types/account'
import type { MetaflowResources } from '@/shared/switch/metaflows/types'
import type { ListboxOptionValue, ListboxValue } from '@/shared/components/FormListbox.vue'

const props = defineProps<{
  account: AccountDetail
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  restrictionOptions: AccountRestrictionOption[]
  callflowOptions: AccountCallflowOption[]
  metaflowResources: MetaflowResources
  optionsError: string | null
}>()
const emit = defineEmits<{ close: []; save: [input: AccountSettingsInput] }>()
const { form, validate, validationErrors } = useAccountSettingsForm(
  props.account,
  props.restrictionOptions,
)
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
const restrictionRows = computed(() => {
  const rows = new Map(props.restrictionOptions.map((option) => [option.key, option] as const))

  for (const key of Object.keys(form.call_restriction)) {
    if (!rows.has(key)) {
      rows.set(key, {
        key,
        label: key
          .split('_')
          .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
          .join(' '),
        emergency: false,
      })
    }
  }

  return [...rows.values()]
})
const restrictionActions = [
  { value: 'inherit', label: 'Inherit endpoint policy' },
  { value: 'deny', label: 'Deny' },
]

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

function selectRestriction(key: string, value: ListboxValue): void {
  if (value === 'inherit' || value === 'deny') {
    form.call_restriction[key] = { action: value }
  }
}
</script>

<template>
  <CrudSlideOver
    title="Edit account settings"
    eyebrow="GridPBX / Accounts"
    description="Only schema-audited settings are written to Switch. Realm and operational controls remain protected."
    width="wide"
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
          <FormInput
            v-model="form.name"
            label="Account name"
            class="sm:col-span-2"
            :error="fieldError('name')"
          />
          <FormInput
            v-model="form.organization_name"
            label="Legal organization"
            class="sm:col-span-2"
            :error="fieldError('organization_name')"
          />
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
          <FormInput
            v-model="form.ringtone_internal"
            label="Internal ringtone"
            :error="fieldError('ringtone_internal')"
          />
          <FormInput
            v-model="form.ringtone_external"
            label="External ringtone"
            :error="fieldError('ringtone_external')"
          />
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
            <FormInput
              v-model="form.caller_id.internal.name"
              label="Name"
              aria-label="Internal caller ID name"
              maxlength="35"
              :error="fieldError('caller_id.internal.name')"
            />
            <FormInput
              v-model="form.caller_id.internal.number"
              label="Number"
              aria-label="Internal caller ID number"
              maxlength="35"
              :error="fieldError('caller_id.internal.number')"
            />
          </section>

          <section
            v-for="scope in ['external', 'emergency'] as const"
            :key="scope"
            class="grid gap-4 rounded-md border border-slate-200 p-4 sm:grid-cols-2"
          >
            <h3 class="text-xs font-semibold capitalize text-slate-700 sm:col-span-2">
              {{ scope }} calls
            </h3>
            <FormInput
              v-model="form.caller_id[scope].name"
              label="Name"
              :aria-label="`${scope} caller ID name`"
              maxlength="35"
              :error="fieldError(`caller_id.${scope}.name`)"
            />
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

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <ShieldExclamationIcon class="size-5 text-brand-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Account call restrictions</h2>
            <p class="mt-1 text-[10px] text-slate-500">
              Classifications are discovered from the connected Switch deployment.
            </p>
          </div>
        </header>
        <p
          v-if="optionsError"
          class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-[10px] leading-4 text-amber-800"
        >
          {{ optionsError }} Existing projected restrictions remain editable.
        </p>
        <div v-if="restrictionRows.length" class="divide-y divide-slate-200">
          <div
            v-for="restriction in restrictionRows"
            :key="restriction.key"
            class="grid items-center gap-3 px-5 py-3 sm:grid-cols-[1fr_260px]"
          >
            <div>
              <p class="text-xs font-semibold text-slate-700">{{ restriction.label }}</p>
              <p class="mt-0.5 text-[10px] text-slate-500">
                {{ restriction.emergency ? 'Emergency classification' : restriction.key }}
              </p>
            </div>
            <div>
              <FormListbox
                :model-value="form.call_restriction[restriction.key]?.action ?? 'inherit'"
                :aria-label="`${restriction.label} restriction`"
                :options="restrictionActions"
                :invalid="Boolean(fieldError(`call_restriction.${restriction.key}.action`))"
                @update:model-value="selectRestriction(restriction.key, $event)"
              />
              <span
                v-if="fieldError(`call_restriction.${restriction.key}.action`)"
                class="mt-1 block text-[10px] text-danger"
                >{{ fieldError(`call_restriction.${restriction.key}.action`) }}</span
              >
            </div>
          </div>
        </div>
        <p v-else class="p-5 text-xs text-slate-500">
          No number classifications are available from Switch.
        </p>
      </article>

      <AccountCallRecordingSettings v-model="form.call_recording" :field-errors="errors" />

      <AccountRoutingSettings
        v-model:dial-plan="form.dial_plan"
        v-model:formatters="form.formatters"
        :field-errors="errors"
      />

      <AccountPreflowMetaflowSettings
        v-model:preflow="form.preflow"
        v-model:metaflows="form.metaflows"
        :current-preflow="account.configuration.preflow"
        :current-metaflows="account.configuration.metaflows"
        :callflow-options="callflowOptions"
        :metaflow-resources="metaflowResources"
        :field-errors="errors"
      />

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
