<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  ExclamationCircleIcon,
  SquaresPlusIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import LineKeyPanel from '../components/LineKeyPanel.vue'
import { useLineKeyStore } from '../stores/lineKeyStore'
import type { LineKeyInput } from '../types/lineKey'

const accounts = useAccountStore()
const lineKeys = useLineKeyStore()
const panel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_devices ?? false)
const assignedCount = computed(() =>
  lineKeys.records.reduce((total, device) => total + device.line_keys.length, 0),
)
const syncLabel = computed(() => {
  if (lineKeys.synchronizing) {
    const processed = lineKeys.syncRun?.processed_count ?? 0
    return processed > 0 ? `Synchronizing · ${processed} processed` : 'Synchronizing from Switch…'
  }

  if (lineKeys.sync.status === 'error') return 'Last synchronization failed'
  if (!lineKeys.sync.last_successful_at) return 'Not synchronized yet'

  return `Last synchronized ${new Date(lineKeys.sync.last_successful_at).toLocaleString()}`
})
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
        <button
          type="button"
          :disabled="!accounts.selectedId || lineKeys.synchronizing"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:bg-brand-50 hover:text-brand-600 disabled:opacity-50"
          @click="accounts.selectedId && lineKeys.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon class="size-4" :class="lineKeys.synchronizing && 'animate-spin'" />
          {{ lineKeys.synchronizing ? 'Synchronizing…' : 'Sync from Switch' }}
        </button>
        <p
          class="flex items-center gap-1 text-[10px]"
          :class="lineKeys.sync.status === 'error' ? 'text-danger' : 'text-slate-500'"
          aria-live="polite"
        >
          <ExclamationCircleIcon v-if="lineKeys.sync.status === 'error'" class="size-3.5" />
          <CheckCircleIcon
            v-else-if="lineKeys.sync.status === 'healthy'"
            class="size-3.5 text-emerald-600"
          />
          <span>{{ syncLabel }}</span>
          <span v-if="syncSummary" class="text-slate-400">· {{ syncSummary }}</span>
        </p>
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
            Projected devices
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
    <div
      v-if="lineKeys.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ lineKeys.error }}
    </div>
    <div
      v-if="lineKeys.mutationError && !panel"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ lineKeys.mutationError }}
    </div>
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
            <th class="w-12"></th>
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
              No projected devices are available. Synchronize extensions and devices first.
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
            <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
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
