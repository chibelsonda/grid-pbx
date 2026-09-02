<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ChevronRightIcon,
  PlusIcon,
  UserGroupIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useDeviceStore } from '@/domains/devices/stores/deviceStore'
import { useVoicemailStore } from '@/domains/voicemail/stores/voicemailStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import { useExtensionStore } from '../stores/extensionStore'
import ExtensionCreatePanel from '../components/ExtensionCreatePanel.vue'
import ExtensionRecoveryPanel from '../components/ExtensionRecoveryPanel.vue'
import type { ExtensionCreate, ExtensionRecoveryOperation } from '../types/extension'

const router = useRouter()
const accounts = useAccountStore()
const devices = useDeviceStore()
const voicemail = useVoicemailStore()
const extensions = useExtensionStore()
const creating = ref(false)
const recoveryOpen = ref(false)
watch(
  () => accounts.selectedId,
  (accountId) => {
    extensions.reset()
    if (accountId) {
      void extensions.load(accountId, 1)
      void extensions.loadOptions(accountId)
      void devices.loadOptions(accountId)
      void voicemail.loadFormOptions(accountId)
    }
  },
  { immediate: true },
)

function search(): void {
  if (accounts.selectedId) void extensions.load(accounts.selectedId, 1)
}

function synchronize(): void {
  if (accounts.selectedId) void extensions.startSync(accounts.selectedId)
}

async function createExtension(input: ExtensionCreate): Promise<void> {
  if (!accounts.selectedId) return
  const extension = await extensions.create(accounts.selectedId, input)

  if (extension) {
    creating.value = false
    await router.push({ name: 'extension-detail', params: { extensionId: extension.id } })
  }
}

function openRecovery(): void {
  if (!accounts.selectedId) return
  extensions.recoveryActionError = null
  recoveryOpen.value = true
  void extensions.loadRecoveryQueue(accounts.selectedId)
}

function openExtension(id: string): void {
  void router.push({ name: 'extension-detail', params: { extensionId: id } })
}

function recoverOperation(
  operation: ExtensionRecoveryOperation,
  confirmation: string | null,
): void {
  if (accounts.selectedId) void extensions.recover(accounts.selectedId, operation, confirmation)
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 xl:flex-row xl:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / People & Extensions</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">People & Extensions</h1>
        <p class="mt-1 text-xs text-slate-500">Fast MySQL projection of users managed by Switch.</p>
      </div>
      <div class="flex flex-col items-start gap-1 xl:ml-auto xl:items-end">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <button
            v-if="accounts.selected?.permissions.can_manage_extensions"
            type="button"
            :disabled="!accounts.selectedId"
            class="inline-flex h-9 flex-auto items-center justify-center gap-2 whitespace-nowrap rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:bg-brand-50 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
            @click="openRecovery"
          >
            <WrenchScrewdriverIcon class="size-4" /> Recovery queue
          </button>
          <ProjectionSyncButton
            :synchronizing="extensions.syncing"
            :disabled="!accounts.selectedId"
            class="flex-auto sm:flex-none"
            @sync="synchronize"
          />
          <button
            v-if="accounts.selected?.permissions.can_manage_extensions"
            type="button"
            :disabled="!accounts.selectedId"
            class="inline-flex h-9 flex-auto items-center justify-center gap-2 whitespace-nowrap rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
            @click="creating = true"
          >
            <PlusIcon class="size-4" /> Create extension
          </button>
        </div>
        <ProjectionFreshness
          :last-synchronized-at="extensions.sync.last_successful_at"
          :status="extensions.sync.status"
        />
      </div>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div
      v-if="!accounts.loading && accounts.accounts.length === 0"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <UserGroupIcon class="mx-auto size-10 text-slate-400" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">No Switch account is mapped</h2>
        <p class="mt-2 max-w-md text-xs leading-5 text-slate-500">
          Set SWITCH_ACCOUNT_ID and seed the API database, then return here to run the first
          projection sync.
        </p>
      </div>
    </div>

    <template v-else>
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form class="relative w-full max-w-sm" @submit.prevent="search">
          <SearchInput
            v-model="extensions.search"
            label="Search extensions"
            placeholder="Search name, extension, username…"
            input-class="h-10 bg-white text-xs shadow-sm"
            live
            @search="search"
          />
        </form>
      </div>

      <div
        v-if="extensions.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
        role="alert"
      >
        {{ extensions.error }}
      </div>

      <div class="card-surface min-w-0 max-w-full overflow-hidden" :aria-busy="extensions.loading">
        <div class="max-w-full overflow-x-auto overscroll-x-contain">
          <table class="w-full min-w-[760px] text-left">
            <caption class="sr-only">
              Projected people and extensions for the selected Switch account
            </caption>
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th scope="col" class="px-5 py-3.5">Person</th>
                <th scope="col" class="px-5 py-3.5">Extension</th>
                <th scope="col" class="px-5 py-3.5">Username</th>
                <th scope="col" class="px-5 py-3.5">Timezone</th>
                <th scope="col" class="px-5 py-3.5">Status</th>
                <th scope="col" aria-label="View extension" class="w-12 px-5 py-3.5" />
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="extensions.loading">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  <span role="status" aria-live="polite">Loading projected extensions…</span>
                </td>
              </tr>
              <tr v-else-if="extensions.records.length === 0">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  No projected extensions found. Run a Switch sync to populate this account.
                </td>
              </tr>
              <tr
                v-for="record in extensions.records"
                v-else
                :key="record.id"
                class="cursor-pointer transition hover:bg-slate-50/60"
                @click="openExtension(record.id)"
              >
                <td class="px-5 py-3.5">
                  <RouterLink
                    :to="{ name: 'extension-detail', params: { extensionId: record.id } }"
                    class="font-semibold text-slate-700 hover:text-brand-600"
                    @click.stop
                    >{{ record.display_name }}</RouterLink
                  >
                  <div class="mt-1 text-[10px] text-slate-400">
                    {{ record.email ?? 'No email' }}
                  </div>
                </td>
                <td class="px-5 py-3.5 font-mono font-semibold text-brand-600">
                  <RouterLink
                    :to="{ name: 'extension-detail', params: { extensionId: record.id } }"
                    @click.stop
                    >{{ record.extension ?? '—' }}</RouterLink
                  >
                </td>
                <td class="px-5 py-3.5 text-slate-500">{{ record.username ?? '—' }}</td>
                <td class="px-5 py-3.5 text-slate-500">{{ record.timezone ?? '—' }}</td>
                <td class="px-5 py-3.5">
                  <span
                    class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                    :class="
                      record.is_enabled
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-slate-100 text-slate-500'
                    "
                    >{{ record.is_enabled ? 'Enabled' : 'Disabled' }}</span
                  >
                </td>
                <td class="px-5 py-3.5">
                  <RouterLink
                    :to="{ name: 'extension-detail', params: { extensionId: record.id } }"
                    :aria-label="`View ${record.display_name}`"
                    class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600"
                    @click.stop
                    ><ChevronRightIcon class="size-4"
                  /></RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer
          class="flex flex-col gap-3 border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500 sm:flex-row sm:items-center"
        >
          <span>{{ extensions.total }} extensions</span>
          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <button
              type="button"
              :disabled="extensions.page <= 1"
              class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
              @click="
                accounts.selectedId && extensions.load(accounts.selectedId, extensions.page - 1)
              "
            >
              Previous</button
            ><span>Page {{ extensions.page }} of {{ extensions.lastPage }}</span
            ><button
              type="button"
              :disabled="extensions.page >= extensions.lastPage"
              class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
              @click="
                accounts.selectedId && extensions.load(accounts.selectedId, extensions.page + 1)
              "
            >
              Next
            </button>
          </div>
        </footer>
      </div>
    </template>
  </div>

  <ExtensionCreatePanel
    v-if="creating"
    :saving="extensions.mutationLoading"
    :error="extensions.mutationError"
    :field-errors="extensions.fieldErrors"
    :options="extensions.formOptions"
    :device-options="{
      extensions: devices.extensionOptions,
      media: devices.mediaOptions,
      metaflow_resources: devices.metaflowResources,
      caller_id_numbers: devices.callerIdNumberOptions,
      provisioning_catalog: devices.provisioningCatalog,
      device_schema: devices.schemaCompatibility,
      restrictions: devices.restrictionOptions,
    }"
    :voicemail-options="voicemail.formOptions"
    @close="creating = false"
    @save="createExtension"
  />
  <ExtensionRecoveryPanel
    v-if="recoveryOpen"
    :records="extensions.recoveryRecords"
    :loading="extensions.recoveryLoading"
    :action-loading="extensions.recoveryActionLoading"
    :error="extensions.recoveryError"
    :action-error="extensions.recoveryActionError"
    @close="recoveryOpen = false"
    @recover="recoverOperation"
  />
</template>
