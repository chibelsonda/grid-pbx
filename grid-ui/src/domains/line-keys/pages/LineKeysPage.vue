<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { SquaresPlusIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import AppAlert from '@/shared/components/AppAlert.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import LineKeyPanel from '../components/LineKeyPanel.vue'
import { useLineKeyStore } from '../stores/lineKeyStore'
import type { LineKeyInput } from '../types/lineKey'

const accounts = useAccountStore()
const lineKeys = useLineKeyStore()
const router = useRouter()
const panel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_devices ?? false)
const assignedCount = computed(() =>
  lineKeys.records.reduce((total, device) => total + device.line_keys.length, 0),
)
const syncSummary = computed(() => {
  const run = lineKeys.syncRun

  if (!run || run.status !== 'succeeded') return null

  return `${run.processed_count} processed · ${run.upserted_count} updated · ${run.deleted_count} removed`
})
watch(
  () => accounts.selectedId,
  (id) => {
    panel.value = false
    lineKeys.reset()
    if (id) void lineKeys.load(id)
  },
  { immediate: true },
)
async function open(deviceId: string): Promise<void> {
  if (!accounts.selectedId) return
  await lineKeys.prepare(accounts.selectedId, deviceId)
  if (lineKeys.preview) panel.value = true
}
function handleRowAction(actionId: string, deviceId: string): void {
  if (actionId === 'device') {
    void router.push({ name: 'device-detail', params: { deviceId } })
  } else {
    void open(deviceId)
  }
}
async function save(keys: LineKeyInput[]): Promise<void> {
  if (accounts.selectedId && (await lineKeys.save(accounts.selectedId, keys))) panel.value = false
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Provisioning</p>
        <h1 class="text-xl font-semibold text-slate-800">Line keys</h1>
        <p class="mt-1 text-xs text-slate-500">
          Preview and manage device combo and feature keys without exposing SIP credentials.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <ProjectionSyncButton
          :synchronizing="lineKeys.synchronizing"
          :disabled="!accounts.selectedId || lineKeys.synchronizing"
          @sync="accounts.selectedId && lineKeys.synchronize(accounts.selectedId)"
        />
        <ProjectionFreshness
          :last-synchronized-at="lineKeys.sync.last_successful_at"
          :status="lineKeys.sync.status"
          :detail="syncSummary"
        />
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><WrenchScrewdriverIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ lineKeys.records.length }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Provisionable devices
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600"
          ><SquaresPlusIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ assignedCount }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected assignments
          </p>
        </div>
      </article>
    </div>
    <AppAlert
      v-if="lineKeys.error"
      :message="lineKeys.error"
      tone="error"
      class="mb-4"
      @dismiss="lineKeys.error = null"
    />
    <AppAlert
      v-if="lineKeys.mutationError && !panel"
      :message="lineKeys.mutationError"
      tone="error"
      class="mb-4"
      @dismiss="lineKeys.mutationError = null"
    />
    <form
      class="mb-4 flex gap-3"
      @submit.prevent="accounts.selectedId && lineKeys.load(accounts.selectedId)"
    >
      <SearchInput
        v-model="lineKeys.search"
        label="Search line keys"
        class="min-w-0 flex-1"
        placeholder="Search device, model, or key label…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="accounts.selectedId && lineKeys.load(accounts.selectedId)"
      /><button
        class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
      >
        Search
      </button>
    </form>
    <div class="card-surface overflow-hidden">
      <table class="w-full text-left">
        <thead
          class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
        >
          <tr>
            <th class="px-5 py-3.5">Device</th>
            <th class="px-5 py-3.5">Provisioning identity</th>
            <th class="px-5 py-3.5">Combo keys</th>
            <th class="px-5 py-3.5">Feature keys</th>
            <th scope="col" class="w-12" aria-label="Actions"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <tr v-if="lineKeys.loading">
            <td colspan="5" class="px-5 py-14 text-center text-slate-400">
              Loading projected line keys…
            </td>
          </tr>
          <tr v-else-if="!lineKeys.records.length">
            <td colspan="5" class="px-5 py-14 text-center text-slate-400">
              No provisionable devices are available. Add or synchronize a physical device first.
            </td>
          </tr>
          <tr
            v-for="device in lineKeys.records"
            v-else
            :key="device.id"
            class="cursor-pointer hover:bg-slate-50"
            @click="open(device.id)"
          >
            <td class="px-5 py-4">
              <p class="font-semibold text-slate-700">{{ device.name ?? 'Unnamed device' }}</p>
              <p class="mt-1 font-mono text-[10px] text-slate-400">
                {{ device.mac_address ?? 'No MAC address' }}
              </p>
            </td>
            <td class="px-5 py-4 text-slate-500">
              {{
                [device.make, device.endpoint_family, device.model].filter(Boolean).join(' / ') ||
                'Not configured'
              }}
            </td>
            <td class="px-5 py-4 text-slate-500">
              {{ device.line_keys.filter((key) => key.category === 'combo').length }}
            </td>
            <td class="px-5 py-4 text-slate-500">
              {{ device.line_keys.filter((key) => key.category === 'feature').length }}
            </td>
            <td class="px-3 text-right">
              <RowActionMenu
                :label="`Actions for ${device.name}`"
                :actions="[
                  { id: 'device', label: 'View device', icon: 'view' },
                  {
                    id: 'manage',
                    label: canManage ? 'Manage line keys' : 'View line keys',
                    icon: canManage ? 'manage' : 'view',
                  },
                ]"
                @select="handleRowAction($event, device.id)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <LineKeyPanel
    v-if="panel && lineKeys.preview"
    :preview="lineKeys.preview"
    :saving="lineKeys.saving"
    :error="lineKeys.mutationError"
    :field-errors="lineKeys.fieldErrors"
    :can-manage="canManage"
    @close="panel = false"
    @save="save"
  />
</template>
