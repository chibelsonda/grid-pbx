<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ChevronRightIcon,
  MagnifyingGlassIcon,
  SquaresPlusIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
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
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Provisioning</p>
        <h1 class="text-xl font-semibold text-slate-800">Line keys</h1>
        <p class="mt-1 text-xs text-slate-500">
          Preview and manage device combo and feature keys without exposing SIP credentials.
        </p>
      </div>
    </div>
  </section>
  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
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
      <label class="relative min-w-0 flex-1"
        ><MagnifyingGlassIcon
          class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" /><input
          v-model="lineKeys.search"
          type="search"
          placeholder="Search device, model, or key label…"
          class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs shadow-sm" /></label
      ><button
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
