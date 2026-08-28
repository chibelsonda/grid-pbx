<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  ClockIcon,
  CpuChipIcon,
  DevicePhoneMobileIcon,
  HashtagIcon,
  LinkIcon,
  PencilSquareIcon,
  SignalIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useDeviceStore } from '../stores/deviceStore'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const devices = useDeviceStore()
const deviceId = computed(() => String(route.params.deviceId))
const device = computed(() => devices.detail)
const modelLabel = computed(() => {
  if (!device.value) return 'Unknown hardware'

  return [device.value.make, device.value.model].filter(Boolean).join(' ') || 'Unknown hardware'
})

watch(
  [() => accounts.selectedId, deviceId],
  ([accountId, selectedDeviceId]) => {
    if (accountId && selectedDeviceId) void devices.loadDetail(accountId, selectedDeviceId)
  },
  { immediate: true },
)

function formatDate(value: string | null): string {
  if (!value) return 'Not synchronized'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

async function removeDevice(): Promise<void> {
  if (!accounts.selectedId || !device.value) return
  if (!window.confirm(`Delete ${device.value.name ?? 'this device'} from Switch?`)) return

  const removed = await devices.remove(accounts.selectedId, device.value.id)

  if (removed) await router.push({ name: 'devices' })
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] items-center gap-4">
      <RouterLink
        to="/devices"
        class="grid size-9 shrink-0 place-items-center rounded-md border border-slate-200 text-slate-500 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
        aria-label="Back to devices"
      >
        <ArrowLeftIcon class="size-4" />
      </RouterLink>
      <div class="min-w-0">
        <p class="mb-1 text-[11px] font-medium text-slate-400">
          <RouterLink to="/devices" class="hover:text-brand-600">GridPBX / Devices</RouterLink>
          / Detail
        </p>
        <h1 class="truncate text-xl font-semibold tracking-tight text-slate-800">
          {{ device?.name ?? 'Device details' }}
        </h1>
        <p class="mt-1 text-xs text-slate-500">
          Projected endpoint hardware, assignment, and synchronization state.
        </p>
      </div>
      <div
        v-if="device && accounts.selected?.permissions.can_manage_devices"
        class="ml-auto flex items-center gap-2"
      >
        <RouterLink
          :to="{ name: 'device-edit', params: { deviceId: device.id } }"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 px-3 text-[11px] font-semibold text-slate-600 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
        >
          <PencilSquareIcon class="size-4" /> Edit
        </RouterLink>
        <button
          type="button"
          :disabled="devices.mutationLoading"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-red-100 px-3 text-[11px] font-semibold text-danger hover:bg-red-50 disabled:opacity-50"
          @click="removeDevice"
        >
          <TrashIcon class="size-4" /> Delete
        </button>
        <span
          class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold lg:inline-flex"
          :class="
            device.is_enabled
              ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
              : 'border-slate-200 bg-slate-100 text-slate-500'
          "
        >
          <span class="size-2 rounded-full bg-current" />
          {{ device.is_enabled ? 'Enabled' : 'Disabled' }}
        </span>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div
      v-if="devices.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading device details…
    </div>

    <div
      v-else-if="devices.detailError"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <DevicePhoneMobileIcon class="mx-auto size-10 text-slate-300" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Device unavailable</h2>
        <p class="mt-2 text-xs text-slate-500">{{ devices.detailError }}</p>
        <RouterLink
          to="/devices"
          class="mt-5 inline-flex h-9 items-center rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600"
        >
          Return to devices
        </RouterLink>
      </div>
    </div>

    <template v-else-if="device">
      <div class="mb-6 grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <article class="card-surface overflow-hidden">
          <div class="h-1 bg-gradient-to-r from-brand-500 via-info to-success" />
          <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
            <span
              class="grid size-16 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600 ring-4 ring-brand-50/60"
            >
              <DevicePhoneMobileIcon class="size-8" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="truncate text-lg font-semibold text-slate-800">
                  {{ device.name ?? 'Unnamed device' }}
                </h2>
                <span
                  class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                  :class="
                    device.is_enabled
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-slate-100 text-slate-500'
                  "
                >
                  {{ device.is_enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </div>
              <p class="mt-1 text-xs text-slate-500">{{ modelLabel }}</p>
              <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-500">
                <span class="inline-flex items-center gap-2">
                  <SignalIcon class="size-4 text-brand-500" />
                  {{ humanize(device.device_type ?? 'unknown endpoint') }}
                </span>
                <span class="inline-flex items-center gap-2 font-mono">
                  <HashtagIcon class="size-4 text-brand-500" />
                  {{ device.mac_address ?? 'No MAC address' }}
                </span>
              </div>
            </div>
          </div>
        </article>

        <article class="card-surface p-5">
          <div class="flex items-start gap-3">
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-600"
            >
              <CheckCircleIcon class="size-5" />
            </span>
            <div>
              <p class="eyebrow">Projection state</p>
              <h2 class="mt-1 text-sm font-semibold text-slate-700">
                {{ humanize(device.sync_status) }}
              </h2>
              <p class="mt-2 inline-flex items-center gap-2 text-[11px] text-slate-500">
                <ClockIcon class="size-4" /> {{ formatDate(device.last_synced_at) }}
              </p>
            </div>
          </div>
          <div class="mt-5 border-t border-slate-100 pt-4">
            <p class="eyebrow">Registration</p>
            <div class="mt-2 flex items-center justify-between gap-3">
              <span
                class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                :class="
                  device.registration_status === 'registered'
                    ? 'bg-violet-50 text-violet-700'
                    : device.registration_status === 'unregistered'
                      ? 'bg-amber-50 text-amber-700'
                      : 'bg-slate-100 text-slate-500'
                "
              >
                {{ humanize(device.registration_status) }}
              </span>
              <span class="text-right text-[10px] text-slate-400">
                {{ formatDate(device.registration_checked_at) }}
              </span>
            </div>
          </div>
        </article>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-blue-50 text-info">
              <CpuChipIcon class="size-5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Endpoint hardware</h2>
              <p class="text-[10px] text-slate-400">Normalized Switch device information</p>
            </div>
          </header>
          <dl class="grid gap-4 p-5 text-xs sm:grid-cols-2">
            <div>
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Make</dt>
              <dd class="mt-1.5 font-medium text-slate-700">{{ device.make ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Model
              </dt>
              <dd class="mt-1.5 font-medium text-slate-700">{{ device.model ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Type</dt>
              <dd class="mt-1.5 font-medium text-slate-700">
                {{ humanize(device.device_type ?? 'unknown') }}
              </dd>
            </div>
            <div>
              <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                MAC address
              </dt>
              <dd class="mt-1.5 font-mono text-[11px] font-medium text-slate-700">
                {{ device.mac_address ?? '—' }}
              </dd>
            </div>
          </dl>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-violet-50 text-violet-600">
              <LinkIcon class="size-5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Extension assignment</h2>
              <p class="text-[10px] text-slate-400">Person or extension linked to this endpoint</p>
            </div>
          </header>
          <div v-if="device.assigned_extension" class="p-5">
            <p class="text-sm font-semibold text-slate-700">
              {{ device.assigned_extension.display_name }}
            </p>
            <p class="mt-1 font-mono text-xs text-slate-500">
              Extension {{ device.assigned_extension.extension ?? 'not assigned' }}
            </p>
            <RouterLink
              :to="{
                name: 'extension-detail',
                params: { extensionId: device.assigned_extension.id },
              }"
              class="mt-5 inline-flex h-9 items-center rounded-md border border-brand-200 bg-brand-50 px-4 text-xs font-semibold text-brand-700 hover:bg-brand-100"
            >
              View extension
            </RouterLink>
          </div>
          <div v-else class="p-5 text-xs leading-5 text-slate-500">
            This endpoint is not assigned to a projected extension.
          </div>
        </article>
      </div>
    </template>
  </div>
</template>
