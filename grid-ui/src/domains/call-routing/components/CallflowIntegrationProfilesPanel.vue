<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  ShieldCheckIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import { useCallflowIntegrationProfiles } from '../composables/useCallflowIntegrationProfiles'
import type {
  CarrierIntegrationProfile,
  CallflowIntegrationProfile,
  CallflowIntegrationProfileInput,
} from '../types/callflowIntegrationProfile'
import CarrierIntegrationProfileFormPanel from './CarrierIntegrationProfileFormPanel.vue'
import DisaIntegrationProfileFormPanel from './DisaIntegrationProfileFormPanel.vue'
import CallflowIntegrationTypePanel from './CallflowIntegrationTypePanel.vue'
import PivotIntegrationProfileFormPanel from './PivotIntegrationProfileFormPanel.vue'
import WebhookIntegrationProfileFormPanel from './WebhookIntegrationProfileFormPanel.vue'

const props = defineProps<{
  accountId: string | null
  canManage: boolean
}>()
const {
  clearErrors,
  create,
  error,
  fieldErrors,
  load,
  loading,
  profiles,
  remove,
  replace,
  saving,
  setActive,
} = useCallflowIntegrationProfiles()
const formOpen = ref(false)
const typePickerOpen = ref(false)
type IntegrationType = CallflowIntegrationProfile['integration_type']

const creatingType = ref<IntegrationType>('pivot')
const replacingProfile = ref<CallflowIntegrationProfile | null>(null)
const removingProfile = ref<CallflowIntegrationProfile | null>(null)

watch(
  () => [props.accountId, props.canManage] as const,
  ([accountId, canManage]) => {
    profiles.value = []
    clearErrors()
    formOpen.value = false
    typePickerOpen.value = false
    replacingProfile.value = null
    removingProfile.value = null
    if (accountId && canManage) void load(accountId)
  },
  { immediate: true },
)

function openCreate(type: IntegrationType): void {
  clearErrors()
  typePickerOpen.value = false
  creatingType.value = type
  replacingProfile.value = null
  formOpen.value = true
}

function openReplace(profile: CallflowIntegrationProfile): void {
  clearErrors()
  creatingType.value = profile.integration_type
  replacingProfile.value = profile
  formOpen.value = true
}

function closeForm(): void {
  if (saving.value) return
  formOpen.value = false
  replacingProfile.value = null
  clearErrors()
}

function openTypePicker(): void {
  clearErrors()
  replacingProfile.value = null
  formOpen.value = false
  typePickerOpen.value = true
}

async function save(input: CallflowIntegrationProfileInput): Promise<void> {
  if (!props.accountId) return

  const profile = replacingProfile.value
  const successful = profile
    ? await replace(props.accountId, profile.id, input)
    : await create(props.accountId, input)

  if (successful) closeForm()
}

async function confirmRemove(): Promise<void> {
  if (!props.accountId || !removingProfile.value) return
  if (await remove(props.accountId, removingProfile.value.id)) removingProfile.value = null
}
</script>

<template>
  <header class="flex flex-wrap items-start gap-3 border-b border-slate-200 px-5 py-4">
    <div>
      <h2 class="text-sm font-semibold text-slate-700">Callflow integrations</h2>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Account-scoped, administrator-approved endpoints for high-risk Callflow actions.
      </p>
    </div>
    <div v-if="accountId && canManage" class="ml-auto flex flex-wrap gap-2">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
        @click="openTypePicker"
      >
        <PlusIcon class="size-4" /> Create integration
      </button>
    </div>
  </header>

  <div class="grid gap-4 p-5">
    <div
      class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900"
    >
      <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
      <p class="text-[10px] leading-4">
        Endpoint URLs and private headers are encrypted and write-only. A callflow stores only the
        profile's public UUID; GridPBX resolves private settings immediately before the Switch
        write. Multiple profiles of the same type are supported, and each Callflow node selects its
        profile independently. Profiles can be reused by any number of nodes in this account. An
        active, valid, account-owned profile enables its matching action except DISA, which
        additionally requires a live carrier/SBC guard to attest to every operational safety
        control. Disable a profile immediately if its endpoint or deployment controls are no longer
        trusted.
      </p>
    </div>

    <p
      v-if="error"
      role="alert"
      class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-xs text-danger"
    >
      {{ error }}
    </p>

    <p v-if="!accountId" class="text-xs text-slate-500">
      Select a mapped account to manage its Callflow integrations.
    </p>
    <p v-else-if="!canManage" class="text-xs text-slate-500">
      Your current account role cannot view or change private integration profiles.
    </p>
    <p v-else-if="loading" class="text-xs text-slate-500">Loading integration profiles…</p>
    <div
      v-else-if="profiles.length === 0"
      class="rounded-md border border-dashed border-slate-300 px-5 py-10 text-center"
    >
      <p class="text-xs font-semibold text-slate-700">No integration profiles configured</p>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Guided integration actions remain unavailable until an active, valid, account-owned profile
        is configured for that action.
      </p>
    </div>

    <article
      v-for="profile in profiles"
      :key="profile.id"
      class="rounded-md border border-slate-200 bg-white p-4"
    >
      <div class="flex flex-wrap items-start gap-3">
        <component
          :is="profile.is_active ? CheckCircleIcon : ExclamationTriangleIcon"
          class="size-5 shrink-0"
          :class="profile.is_active ? 'text-emerald-500' : 'text-amber-500'"
        />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-xs font-semibold text-slate-800">{{ profile.name }}</h3>
            <span
              class="rounded-full bg-brand-50 px-2 py-0.5 text-[9px] font-bold uppercase text-brand-700"
            >
              {{ profile.integration_type }}
            </span>
            <span
              class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
              :class="
                profile.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
              "
            >
              {{ profile.is_active ? 'Profile active' : 'Profile disabled' }}
            </span>
          </div>
          <div class="mt-2 flex flex-wrap gap-2 text-[10px] text-slate-500">
            <template v-if="profile.integration_type === 'pivot'">
              <span>Methods: {{ profile.configuration.methods.join(', ').toUpperCase() }}</span>
              <span aria-hidden="true">·</span>
              <span>Formats: {{ profile.configuration.formats.join(', ') }}</span>
              <span v-if="profile.configuration.has_cdr_callback" aria-hidden="true">·</span>
              <span v-if="profile.configuration.has_cdr_callback">CDR callback</span>
              <span v-if="profile.configuration.has_custom_headers" aria-hidden="true">·</span>
              <span v-if="profile.configuration.has_custom_headers">Private headers</span>
            </template>
            <template v-else-if="profile.integration_type === 'webhook'">
              <span>Methods: {{ profile.configuration.methods.join(', ').toUpperCase() }}</span>
              <span aria-hidden="true">·</span>
              <span>Maximum attempts: {{ profile.configuration.max_retries }}</span>
            </template>
            <template v-else-if="profile.integration_type === 'disa'">
              <span>Write-only PIN configured</span>
              <span aria-hidden="true">·</span>
              <span>{{ profile.configuration.retries }} attempts per call</span>
              <span aria-hidden="true">·</span>
              <span>Call restrictions enforced</span>
              <span aria-hidden="true">·</span>
              <span>Deployment guard required</span>
            </template>
            <template v-else>
              <span>Scope: {{ profile.configuration.route_scope.replace('_', ' ') }}</span>
            </template>
          </div>
          <p class="mt-2 text-[10px] leading-4 text-slate-400">
            Private values are intentionally not displayed. Replace the authorization to rotate or
            change its configuration.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            :disabled="saving"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 px-3 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
            @click="openReplace(profile)"
          >
            <ArrowPathIcon class="size-3.5" /> Replace configuration
          </button>
          <button
            type="button"
            :disabled="saving"
            class="h-8 rounded-md border border-slate-300 px-3 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
            @click="accountId && setActive(accountId, profile, !profile.is_active)"
          >
            {{ profile.is_active ? 'Disable' : 'Enable' }}
          </button>
          <button
            type="button"
            :disabled="saving"
            class="grid size-8 place-items-center rounded-md border border-red-200 text-danger hover:bg-red-50 disabled:opacity-50"
            :aria-label="`Remove ${profile.name}`"
            @click="removingProfile = profile"
          >
            <TrashIcon class="size-4" />
          </button>
        </div>
      </div>
    </article>
  </div>

  <CallflowIntegrationTypePanel
    v-if="typePickerOpen"
    @close="typePickerOpen = false"
    @select="openCreate"
  />

  <PivotIntegrationProfileFormPanel
    v-if="formOpen && creatingType === 'pivot'"
    :profile="replacingProfile?.integration_type === 'pivot' ? replacingProfile : null"
    :saving="saving"
    :error="error"
    :field-errors="fieldErrors"
    @close="closeForm"
    @save="save"
  />

  <WebhookIntegrationProfileFormPanel
    v-if="formOpen && creatingType === 'webhook'"
    :profile="replacingProfile?.integration_type === 'webhook' ? replacingProfile : null"
    :saving="saving"
    :error="error"
    :field-errors="fieldErrors"
    @close="closeForm"
    @save="save"
  />

  <DisaIntegrationProfileFormPanel
    v-if="formOpen && creatingType === 'disa'"
    :profile="replacingProfile?.integration_type === 'disa' ? replacingProfile : null"
    :saving="saving"
    :error="error"
    :field-errors="fieldErrors"
    @close="closeForm"
    @save="save"
  />

  <CarrierIntegrationProfileFormPanel
    v-if="formOpen && ['global_carrier', 'account_carrier'].includes(creatingType)"
    :type="creatingType as 'global_carrier' | 'account_carrier'"
    :profile="
      replacingProfile &&
      ['global_carrier', 'account_carrier'].includes(replacingProfile.integration_type)
        ? (replacingProfile as CarrierIntegrationProfile)
        : null
    "
    :saving="saving"
    :error="error"
    :field-errors="fieldErrors"
    @close="closeForm"
    @save="save"
  />

  <ConfirmDialog
    :open="removingProfile !== null"
    title="Remove integration profile?"
    :description="`Callflows using ${removingProfile?.name ?? 'this profile'} will no longer be editable as a supported ${removingProfile?.integration_type ?? 'integration'} configuration. Existing Switch data is not silently rewritten.`"
    confirm-label="Remove profile"
    :busy="saving"
    @close="removingProfile = null"
    @confirm="confirmRemove"
  />
</template>
