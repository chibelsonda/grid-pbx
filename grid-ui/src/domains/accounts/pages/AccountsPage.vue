<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  BuildingOffice2Icon,
  CheckCircleIcon,
  CircleStackIcon,
  CloudIcon,
  Cog6ToothIcon,
  ExclamationTriangleIcon,
  GlobeAltIcon,
  ArrowPathIcon,
  PencilSquareIcon,
} from '@heroicons/vue/24/outline'
import AppAlert from '@/shared/components/AppAlert.vue'
import AccountSettingsPanel from '../components/AccountSettingsPanel.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import { useAccountStore } from '../stores/accountStore'
import type { AccountSettingsInput } from '../types/account'

const accounts = useAccountStore()
const settingsOpen = ref(false)
const statusConfirmationOpen = ref(false)
const countCards = computed(() => {
  const counts = accounts.detail?.resource_counts
  if (!counts) return []

  return [
    ['Extensions', counts.extensions],
    ['Devices', counts.devices],
    ['Phone numbers', counts.phone_numbers],
    ['Callflows', counts.callflows],
    ['Voicemail boxes', counts.voicemail_boxes],
    ['Queues', counts.queues],
    ['Media', counts.media],
    ['Recordings', counts.recordings],
  ] as const
})

watch(
  () => accounts.selectedId,
  (accountId) => {
    if (accountId) void accounts.loadDetail(accountId)
  },
  { immediate: true },
)

async function saveSettings(input: AccountSettingsInput): Promise<void> {
  if (!accounts.selectedId) return
  if (await accounts.updateSettings(accounts.selectedId, input)) settingsOpen.value = false
}

async function changeStatus(): Promise<void> {
  if (!accounts.selectedId || !accounts.detail) return
  const enabled = !accounts.detail.enabled
  if (await accounts.updateStatus(accounts.selectedId, enabled, accounts.detail.name)) {
    statusConfirmationOpen.value = false
  }
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container">
      <p class="mb-1 text-[11px] font-medium text-slate-500">GridPBX / Accounts</p>
      <h1 class="text-xl font-semibold tracking-tight text-slate-800">Accounts</h1>
      <p class="mt-1 text-xs text-slate-600">
        Safe account projections, tenancy context, and configuration boundaries.
      </p>
    </div>
  </section>

  <div class="page-container grid gap-5 py-4 sm:py-6 lg:grid-cols-[300px_1fr] lg:py-8">
    <aside class="card-surface h-fit overflow-hidden">
      <div class="border-b border-slate-200 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-700">Accessible accounts</h2>
      </div>
      <div class="grid divide-y divide-slate-100">
        <button
          v-for="account in accounts.accounts"
          :key="account.id"
          type="button"
          class="flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50"
          :class="account.id === accounts.selectedId && 'bg-brand-50/70'"
          @click="accounts.select(account.id)"
        >
          <span
            class="grid size-9 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-600"
          >
            <BuildingOffice2Icon class="size-4" />
          </span>
          <span class="min-w-0">
            <span class="block truncate text-xs font-semibold text-slate-700">{{
              account.name
            }}</span>
            <span class="mt-0.5 block truncate text-[10px] text-slate-500">{{
              account.organization.name
            }}</span>
            <span v-if="!account.enabled" class="mt-1 block text-[10px] font-semibold text-red-600">
              Disabled
            </span>
          </span>
        </button>
      </div>
    </aside>

    <main class="min-w-0">
      <div
        v-if="accounts.detailLoading"
        class="card-surface p-12 text-center text-xs text-slate-500"
      >
        Loading account projection…
      </div>
      <AppAlert
        v-else-if="accounts.detailError"
        :message="accounts.detailError"
        tone="error"
        @dismiss="accounts.detailError = null"
      />
      <div v-else-if="accounts.detail" class="grid gap-5">
        <article class="card-surface p-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <span class="grid size-12 place-items-center rounded-md bg-brand-50 text-brand-600">
              <BuildingOffice2Icon class="size-6" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-800">{{ accounts.detail.name }}</h2>
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold"
                  :class="
                    accounts.detail.enabled
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-red-50 text-red-700'
                  "
                >
                  <CheckCircleIcon v-if="accounts.detail.enabled" class="size-3.5" />
                  <ExclamationTriangleIcon v-else class="size-3.5" />
                  {{ accounts.detail.enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </div>
              <p class="mt-1 text-xs text-slate-600">{{ accounts.detail.organization.name }}</p>
            </div>
            <div v-if="accounts.detail.permissions.can_manage_settings" class="flex shrink-0 gap-2">
              <button
                type="button"
                :disabled="accounts.refreshing"
                class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm disabled:opacity-50"
                @click="accounts.selectedId && accounts.refreshProjection(accounts.selectedId)"
              >
                <ArrowPathIcon
                  class="size-4"
                  :class="accounts.refreshing && 'animate-spin'"
                />Refresh
              </button>
              <button
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-3 text-xs font-semibold text-white"
                @click="settingsOpen = true"
              >
                <PencilSquareIcon class="size-4" />Edit settings
              </button>
              <button
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs font-semibold"
                :class="
                  accounts.detail.enabled
                    ? 'border-red-200 text-red-700'
                    : 'border-emerald-200 text-emerald-700'
                "
                @click="statusConfirmationOpen = true"
              >
                {{ accounts.detail.enabled ? 'Disable account' : 'Enable account' }}
              </button>
            </div>
          </div>
          <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2">
            <div>
              <dt
                class="flex items-center gap-2 text-[10px] font-bold tracking-wide text-slate-500 uppercase"
              >
                <GlobeAltIcon class="size-4" />Realm
              </dt>
              <dd class="mt-1 break-all text-sm font-semibold text-slate-700">
                {{ accounts.detail.realm ?? 'Not projected' }}
              </dd>
            </div>
            <div>
              <dt
                class="flex items-center gap-2 text-[10px] font-bold tracking-wide text-slate-500 uppercase"
              >
                <CloudIcon class="size-4" />Timezone
              </dt>
              <dd class="mt-1 text-sm font-semibold text-slate-700">
                {{ accounts.detail.timezone ?? 'Inherited from Switch' }}
              </dd>
            </div>
          </dl>
        </article>

        <section>
          <h2 class="mb-3 text-sm font-semibold text-slate-700">Projected resources</h2>
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="[label, count] in countCards" :key="label" class="card-surface p-4">
              <CircleStackIcon class="size-4 text-brand-500" />
              <p class="mt-3 text-xl font-semibold text-slate-800">{{ count }}</p>
              <p class="mt-1 text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                {{ label }}
              </p>
            </article>
          </div>
        </section>

        <article class="card-surface overflow-hidden">
          <div class="flex items-center gap-2 border-b border-slate-200 px-5 py-4">
            <Cog6ToothIcon class="size-5 text-brand-500" />
            <h2 class="text-sm font-semibold text-slate-700">Account schema boundaries</h2>
          </div>
          <div class="grid gap-3 p-5 sm:grid-cols-2">
            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
              <p class="text-xs font-semibold text-slate-700">Identity and calling defaults</p>
              <p class="mt-1 text-[11px] leading-5 text-slate-600">
                Schema-audited identity, caller ID, recording, routing, preflow, and in-call
                defaults are available. Unknown Switch-owned settings remain protected from partial
                writes.
              </p>
            </div>
            <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
              <p class="text-xs font-semibold text-amber-800">Operational and billing controls</p>
              <p class="mt-1 text-[11px] leading-5 text-amber-700">
                Enable/disable requires confirmation and audit. Top-up, billing, zones, and provider
                notifications remain capability-gated.
              </p>
            </div>
          </div>
        </article>
        <AppAlert
          v-if="accounts.mutationError"
          :message="accounts.mutationError"
          tone="error"
          @dismiss="accounts.mutationError = null"
        />
      </div>
    </main>
  </div>
  <AccountSettingsPanel
    v-if="settingsOpen && accounts.detail"
    :key="`${accounts.detail.id}:${accounts.detail.projection.version}`"
    :account="accounts.detail"
    :saving="accounts.saving"
    :error="accounts.mutationError"
    :field-errors="accounts.fieldErrors"
    :restriction-options="accounts.settingsOptions.restrictions"
    :callflow-options="accounts.settingsOptions.callflows"
    :metaflow-resources="accounts.settingsOptions.metaflow_resources"
    :options-error="accounts.settingsOptionsError"
    @close="settingsOpen = false"
    @save="saveSettings"
  />
  <ConfirmDialog
    v-if="accounts.detail"
    :open="statusConfirmationOpen"
    :title="accounts.detail.enabled ? 'Disable account' : 'Enable account'"
    :description="
      accounts.detail.enabled
        ? 'Disabling blocks account-scoped PBX operations until an administrator enables it again.'
        : 'Enable this account and restore account-scoped PBX operations.'
    "
    :confirm-label="accounts.detail.enabled ? 'Disable account' : 'Enable account'"
    :confirmation-text="accounts.detail.name"
    :busy="accounts.changingStatus"
    :tone="accounts.detail.enabled ? 'danger' : 'primary'"
    @close="statusConfirmationOpen = false"
    @confirm="changeStatus"
  />
</template>
