<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  DevicePhoneMobileIcon,
  KeyIcon,
  LinkIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useDeviceStore } from '../stores/deviceStore'
import type { DeviceInput } from '../types/device'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const devices = useDeviceStore()
const isEditing = computed(() => route.name === 'device-edit')
const deviceId = computed(() => (isEditing.value ? String(route.params.deviceId) : null))
const title = computed(() => (isEditing.value ? 'Edit device' : 'Add device'))
const form = reactive({
  name: '',
  device_type: 'sip_device',
  make: '',
  model: '',
  mac_address: '',
  is_enabled: true,
  assigned_extension_id: '',
  sip_username: '',
  sip_password: '',
})

watch(
  [() => accounts.selectedId, deviceId],
  async ([accountId, selectedDeviceId]) => {
    devices.mutationError = null
    devices.fieldErrors = {}

    if (!accountId) return

    await devices.loadExtensionOptions(accountId)

    if (selectedDeviceId) {
      await devices.loadDetail(accountId, selectedDeviceId)
      const device = devices.detail

      if (device) {
        form.name = device.name ?? ''
        form.device_type = device.device_type ?? 'sip_device'
        form.make = device.make ?? ''
        form.model = device.model ?? ''
        form.mac_address = device.mac_address ?? ''
        form.is_enabled = device.is_enabled
        form.assigned_extension_id = device.assigned_extension?.id ?? ''
        form.sip_username = ''
        form.sip_password = ''
      }
    }
  },
  { immediate: true },
)

function fieldError(field: string): string | null {
  return devices.fieldErrors[field]?.[0] ?? null
}

function nullable(value: string): string | null {
  const trimmed = value.trim()

  return trimmed === '' ? null : trimmed
}

async function save(): Promise<void> {
  if (!accounts.selectedId) return

  const input: DeviceInput = {
    name: form.name.trim(),
    device_type: form.device_type,
    make: nullable(form.make),
    model: nullable(form.model),
    mac_address: nullable(form.mac_address),
    is_enabled: form.is_enabled,
    assigned_extension_id: nullable(form.assigned_extension_id),
    sip_username: nullable(form.sip_username),
    sip_password: nullable(form.sip_password),
  }
  const device = deviceId.value
    ? await devices.update(accounts.selectedId, deviceId.value, input)
    : await devices.create(accounts.selectedId, input)

  if (device) await router.push({ name: 'device-detail', params: { deviceId: device.id } })
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1100px] items-center gap-4">
      <RouterLink
        :to="deviceId ? { name: 'device-detail', params: { deviceId } } : { name: 'devices' }"
        class="grid size-9 shrink-0 place-items-center rounded-md border border-slate-200 text-slate-500 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
        aria-label="Back"
      >
        <ArrowLeftIcon class="size-4" />
      </RouterLink>
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Devices / {{ title }}</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">{{ title }}</h1>
        <p class="mt-1 text-xs text-slate-500">
          Configuration is written to Switch and immediately projected into MySQL.
        </p>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1100px] p-4 sm:p-6 lg:p-8">
    <div
      v-if="isEditing && devices.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading device configuration…
    </div>

    <div
      v-else-if="isEditing && devices.detailError"
      class="card-surface p-8 text-center text-xs text-danger"
    >
      {{ devices.detailError }}
    </div>

    <form v-else class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]" @submit.prevent="save">
      <div class="grid gap-5">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
              <DevicePhoneMobileIcon class="size-5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Device identity</h2>
              <p class="text-[10px] text-slate-400">Name and endpoint type shown throughout GridPBX</p>
            </div>
          </header>
          <div class="grid gap-5 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Device name</span>
              <input
                v-model="form.name"
                required
                maxlength="255"
                placeholder="Reception Desk Phone"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              />
              <span v-if="fieldError('name')" class="text-[11px] text-danger">{{ fieldError('name') }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Device type</span>
              <select
                v-model="form.device_type"
                required
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              >
                <option value="sip_device">SIP device</option>
                <option value="softphone">Softphone</option>
                <option value="cellphone">Cellphone</option>
                <option value="landline">Landline</option>
                <option value="fax">Fax</option>
                <option value="ata">ATA</option>
              </select>
              <span v-if="fieldError('device_type')" class="text-[11px] text-danger">{{ fieldError('device_type') }}</span>
            </label>
            <label class="flex items-center gap-3 self-end rounded-md border border-slate-200 px-3 py-2.5">
              <input v-model="form.is_enabled" type="checkbox" class="size-4 accent-brand-500" />
              <span>
                <span class="block text-xs font-semibold text-slate-600">Enabled</span>
                <span class="block text-[10px] text-slate-400">Allow this endpoint to operate</span>
              </span>
            </label>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-blue-50 text-info">
              <WrenchScrewdriverIcon class="size-5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Hardware and provisioning</h2>
              <p class="text-[10px] text-slate-400">Optional inventory metadata used by the endpoint</p>
            </div>
          </header>
          <div class="grid gap-5 p-5 sm:grid-cols-2">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Make</span>
              <input v-model="form.make" maxlength="255" placeholder="Yealink" class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" />
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Model</span>
              <input v-model="form.model" maxlength="255" placeholder="T54W" class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" />
            </label>
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">MAC address</span>
              <input v-model="form.mac_address" maxlength="64" placeholder="00:11:22:33:44:55" class="h-10 rounded-md border border-slate-200 px-3 font-mono text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" />
              <span v-if="fieldError('mac_address')" class="text-[11px] text-danger">{{ fieldError('mac_address') }}</span>
            </label>
          </div>
        </article>
      </div>

      <div class="grid content-start gap-5">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <LinkIcon class="size-5 text-violet-500" />
            <h2 class="text-sm font-semibold text-slate-700">Assignment</h2>
          </header>
          <div class="p-5">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Extension</span>
              <select v-model="form.assigned_extension_id" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                <option value="">Unassigned</option>
                <option v-for="extension in devices.extensionOptions" :key="extension.id" :value="extension.id">
                  {{ extension.display_name }}{{ extension.extension ? ` · ${extension.extension}` : '' }}
                </option>
              </select>
              <span v-if="fieldError('assigned_extension_id')" class="text-[11px] text-danger">{{ fieldError('assigned_extension_id') }}</span>
            </label>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <KeyIcon class="size-5 text-amber-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">SIP credentials</h2>
              <p class="text-[10px] text-slate-400">Optional and write-only</p>
            </div>
          </header>
          <div class="grid gap-4 p-5">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">SIP username</span>
              <input v-model="form.sip_username" maxlength="128" autocomplete="off" class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" />
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">{{ isEditing ? 'New SIP password' : 'SIP password' }}</span>
              <input v-model="form.sip_password" type="password" minlength="12" maxlength="255" autocomplete="new-password" class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" />
              <span class="text-[10px] leading-4 text-slate-400">
                {{ isEditing ? 'Leave blank to keep the existing password.' : 'Use at least 12 characters.' }} The value is never stored unredacted or returned by GridPBX.
              </span>
              <span v-if="fieldError('sip_password')" class="text-[11px] text-danger">{{ fieldError('sip_password') }}</span>
            </label>
          </div>
        </article>

        <div v-if="devices.mutationError" class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger">
          {{ devices.mutationError }}
        </div>

        <button
          type="submit"
          :disabled="devices.mutationLoading || !accounts.selectedId"
          class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          <CheckCircleIcon class="size-4" />
          {{ devices.mutationLoading ? 'Saving…' : isEditing ? 'Save changes' : 'Create device' }}
        </button>
      </div>
    </form>
  </div>
</template>
