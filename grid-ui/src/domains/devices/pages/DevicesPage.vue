<script setup lang="ts">
import { computed, watch } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  DevicePhoneMobileIcon,
  LinkIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  SignalIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useDeviceStore } from '../stores/deviceStore'

const accounts = useAccountStore()
const devices = useDeviceStore()
const enabledOnPage = computed(() => devices.records.filter((device) => device.is_enabled).length)
const assignedOnPage = computed(
  () => devices.records.filter((device) => device.assigned_extension !== null).length,
)
const registeredOnPage = computed(
  () => devices.records.filter((device) => device.registration_status === 'registered').length,
)
const freshnessLabel = computed(() => {
  if (!devices.sync.last_successful_at) return 'Not synchronized yet'

  return `Last synchronized ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(devices.sync.last_successful_at))}`
})

watch(
  () => accounts.selectedId,
  (accountId) => {
    devices.reset()
    if (accountId) void devices.load(accountId, 1)
  },
  { immediate: true },
)

function search(): void {
  if (accounts.selectedId) void devices.load(accounts.selectedId, 1)
}

function refresh(): void {
  if (accounts.selectedId) void devices.load(accounts.selectedId, devices.page)
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Devices</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Devices</h1>
        <p class="mt-1 text-xs text-slate-500">
          Desk phones, softphones, and SIP endpoints projected from Switch.
        </p>
      </div>
      <div class="flex gap-2 sm:ml-auto">
        <button
          type="button"
          :disabled="!accounts.selectedId || devices.loading"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600 disabled:opacity-50"
          @click="refresh"
        >
          <ArrowPathIcon class="size-4" :class="devices.loading && 'animate-spin'" />
          Reload projection
        </button>
        <RouterLink
          v-if="accounts.selectedId && accounts.selected?.permissions.can_manage_devices"
          :to="{ name: 'device-create' }"
          class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600"
        >
          <PlusIcon class="size-4" /> Add device
        </RouterLink>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div
      v-if="!accounts.loading && accounts.accounts.length === 0"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <DevicePhoneMobileIcon class="mx-auto size-10 text-slate-300" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">No Switch account is mapped</h2>
        <p class="mt-2 max-w-md text-xs leading-5 text-slate-500">
          Map an account and synchronize the extension projection before viewing devices.
        </p>
      </div>
    </div>

    <template v-else>
      <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
            <DevicePhoneMobileIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ devices.total }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Projected devices
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600">
            <SignalIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ registeredOnPage }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Registered on page
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600">
            <CheckCircleIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ enabledOnPage }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Enabled on page
            </p>
          </div>
        </article>
        <article class="card-surface flex items-center gap-4 p-4">
          <span class="grid size-10 place-items-center rounded-md bg-blue-50 text-info">
            <LinkIcon class="size-5" />
          </span>
          <div>
            <p class="text-lg font-semibold text-slate-700">{{ assignedOnPage }}</p>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Assigned on page
            </p>
          </div>
        </article>
      </div>

      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form class="relative w-full max-w-sm" @submit.prevent="search">
          <MagnifyingGlassIcon
            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="devices.search"
            type="search"
            placeholder="Search name, model, MAC, extension…"
            class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs shadow-sm outline-none focus:border-brand-500"
          />
        </form>
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold sm:ml-auto"
          :class="
            devices.sync.status === 'healthy'
              ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
              : devices.sync.status === 'error'
                ? 'border-red-100 bg-red-50 text-danger'
                : 'border-amber-100 bg-amber-50 text-amber-700'
          "
        >
          <span class="size-2 rounded-full bg-current" /> {{ freshnessLabel }}
        </span>
      </div>

      <div
        v-if="devices.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
      >
        {{ devices.error }}
      </div>

      <div class="card-surface overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Device</th>
                <th class="px-5 py-3.5">Type & model</th>
                <th class="px-5 py-3.5">Assigned extension</th>
                <th class="px-5 py-3.5">MAC address</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="w-12 px-5 py-3.5"><span class="sr-only">View</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="devices.loading">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  Loading projected devices…
                </td>
              </tr>
              <tr v-else-if="devices.records.length === 0">
                <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                  No projected devices found for this account.
                </td>
              </tr>
              <tr
                v-for="device in devices.records"
                v-else
                :key="device.id"
                class="hover:bg-slate-50/60"
              >
                <td class="px-5 py-3.5">
                  <RouterLink
                    :to="{ name: 'device-detail', params: { deviceId: device.id } }"
                    class="font-semibold text-slate-700 hover:text-brand-600"
                  >
                    {{ device.name ?? 'Unnamed device' }}
                  </RouterLink>
                  <div class="mt-1 text-[10px] text-slate-400">
                    {{ humanize(device.device_type ?? 'unknown endpoint') }}
                  </div>
                </td>
                <td class="px-5 py-3.5 text-slate-500">
                  {{ [device.make, device.model].filter(Boolean).join(' ') || '—' }}
                </td>
                <td class="px-5 py-3.5">
                  <RouterLink
                    v-if="device.assigned_extension"
                    :to="{
                      name: 'extension-detail',
                      params: { extensionId: device.assigned_extension.id },
                    }"
                    class="font-semibold text-brand-600 hover:text-brand-700"
                  >
                    {{ device.assigned_extension.display_name }}
                    <span class="ml-1 font-mono text-[10px] text-slate-400">
                      {{ device.assigned_extension.extension ?? '—' }}
                    </span>
                  </RouterLink>
                  <span v-else class="text-slate-400">Unassigned</span>
                </td>
                <td class="px-5 py-3.5 font-mono text-[11px] text-slate-500">
                  {{ device.mac_address ?? '—' }}
                </td>
                <td class="px-5 py-3.5">
                  <div class="flex flex-wrap gap-1.5">
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
                  </div>
                </td>
                <td class="px-5 py-3.5">
                  <RouterLink
                    :to="{ name: 'device-detail', params: { deviceId: device.id } }"
                    :aria-label="`View ${device.name ?? 'device'}`"
                    class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600"
                  >
                    <ChevronRightIcon class="size-4" />
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer
          class="flex items-center border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500"
        >
          <span>{{ devices.total }} devices</span>
          <div class="ml-auto flex items-center gap-2">
            <button
              type="button"
              :disabled="devices.page <= 1"
              class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
              @click="accounts.selectedId && devices.load(accounts.selectedId, devices.page - 1)"
            >
              Previous
            </button>
            <span>Page {{ devices.page }} of {{ devices.lastPage }}</span>
            <button
              type="button"
              :disabled="devices.page >= devices.lastPage"
              class="rounded border border-slate-200 px-3 py-1.5 disabled:opacity-40"
              @click="accounts.selectedId && devices.load(accounts.selectedId, devices.page + 1)"
            >
              Next
            </button>
          </div>
        </footer>
      </div>
    </template>
  </div>
</template>
