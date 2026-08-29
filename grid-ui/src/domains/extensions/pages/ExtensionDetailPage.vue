<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowPathRoundedSquareIcon,
  CheckCircleIcon,
  ClockIcon,
  DevicePhoneMobileIcon,
  EnvelopeIcon,
  IdentificationIcon,
  MicrophoneIcon,
  PhoneIcon,
  PencilSquareIcon,
  ShieldExclamationIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useVoicemailStore } from '@/domains/voicemail/stores/voicemailStore'
import ExtensionDeletionPreviewPanel from '../components/ExtensionDeletionPreviewPanel.vue'
import ExtensionEditPanel from '../components/ExtensionEditPanel.vue'
import { useExtensionStore } from '../stores/extensionStore'
import type { ExtensionUpdate } from '../types/extension'

const route = useRoute()
const router = useRouter()
const accounts = useAccountStore()
const extensions = useExtensionStore()
const voicemail = useVoicemailStore()
const extensionId = computed(() => String(route.params.extensionId))
const extension = computed(() => extensions.detail)
const canManage = computed(() => accounts.selected?.permissions.can_manage_extensions ?? false)
const editPanelOpen = ref(false)
const deletionPanelOpen = ref(false)
const initials = computed(() =>
  (extension.value?.display_name ?? 'Extension')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join(''),
)

watch(
  [() => accounts.selectedId, extensionId],
  ([accountId, selectedExtensionId]) => {
    if (accountId && selectedExtensionId) {
      void extensions.loadDetail(accountId, selectedExtensionId)
      void extensions.loadOptions(accountId)
      void voicemail.loadFormOptions(accountId)
    }
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

async function openEditPanel(): Promise<void> {
  if (!accounts.selectedId || !extension.value) return
  extensions.mutationError = null
  extensions.fieldErrors = {}
  voicemail.detail = null
  voicemail.detailError = null
  const managedVoicemail = extension.value.voicemail_boxes.find((box) => box.is_managed)

  if (managedVoicemail) {
    await voicemail.loadDetail(accounts.selectedId, managedVoicemail.id)
    if (!voicemail.detail) {
      extensions.mutationError = voicemail.detailError ?? 'Unable to load the managed mailbox.'

      return
    }
  }

  editPanelOpen.value = true
}

function openDeletionPreview(): void {
  if (!accounts.selectedId || !extension.value) return
  deletionPanelOpen.value = true
  extensions.deletionError = null
  extensions.fieldErrors = {}
  void extensions.loadDeletionPreview(accounts.selectedId, extension.value.id)
}

async function deleteExtension(confirmation: string): Promise<void> {
  if (!accounts.selectedId || !extension.value) return
  const deleted = await extensions.remove(accounts.selectedId, extension.value.id, confirmation)
  if (deleted) await router.push({ name: 'extensions' })
}

async function updateExtension(input: ExtensionUpdate): Promise<void> {
  if (!accounts.selectedId || !extension.value) return
  const updated = await extensions.update(accounts.selectedId, extension.value.id, input)
  if (updated) editPanelOpen.value = false
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] items-center gap-4">
      <RouterLink
        to="/extensions"
        class="grid size-9 shrink-0 place-items-center rounded-md border border-slate-200 text-slate-500 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
        aria-label="Back to extensions"
      >
        <ArrowLeftIcon class="size-4" />
      </RouterLink>
      <div class="min-w-0">
        <p class="mb-1 text-[11px] font-medium text-slate-400">
          <RouterLink to="/extensions" class="hover:text-brand-600"
            >GridPBX / People & Extensions</RouterLink
          >
          / Detail
        </p>
        <h1 class="truncate text-xl font-semibold tracking-tight text-slate-800">
          {{ extension?.display_name ?? 'Extension details' }}
        </h1>
        <p class="mt-1 text-xs text-slate-500">
          Projected identity, endpoints, voicemail, and routing from Switch.
        </p>
      </div>
      <div v-if="extension" class="ml-auto flex items-center gap-2">
        <button
          v-if="canManage && extension.is_managed"
          type="button"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm hover:bg-brand-50 hover:text-brand-600"
          @click="openEditPanel"
        >
          <PencilSquareIcon class="size-4" /><span class="hidden sm:inline">Edit</span>
        </button>
        <button
          v-if="canManage"
          type="button"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-amber-100 bg-amber-50 px-3 text-xs font-semibold text-amber-700 hover:bg-amber-100"
          @click="openDeletionPreview"
        >
          <ShieldExclamationIcon class="size-4" /><span class="hidden sm:inline"
            >Review deletion</span
          >
        </button>
        <span
          class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold lg:inline-flex"
          :class="
            extension.is_enabled
              ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
              : 'border-slate-200 bg-slate-100 text-slate-500'
          "
        >
          <span class="size-2 rounded-full bg-current" />
          {{ extension.is_enabled ? 'Enabled' : 'Disabled' }}
        </span>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div
      v-if="extensions.detailLoading"
      class="card-surface grid min-h-72 place-items-center text-xs text-slate-400"
    >
      Loading extension details…
    </div>

    <div
      v-else-if="extensions.detailError"
      class="card-surface grid min-h-72 place-items-center p-8 text-center"
    >
      <div>
        <IdentificationIcon class="mx-auto size-10 text-slate-400" />
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Extension unavailable</h2>
        <p class="mt-2 text-xs text-slate-500">{{ extensions.detailError }}</p>
        <RouterLink
          to="/extensions"
          class="mt-5 inline-flex h-9 items-center rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600"
          >Return to extensions</RouterLink
        >
      </div>
    </div>

    <template v-else-if="extension">
      <div class="mb-6 grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <article class="card-surface overflow-hidden">
          <div class="h-1 bg-gradient-to-r from-brand-500 via-info to-success" />
          <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
            <div
              class="grid size-16 shrink-0 place-items-center rounded-full bg-brand-50 text-xl font-bold text-brand-600 ring-4 ring-brand-50/60"
            >
              {{ initials }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="truncate text-lg font-semibold text-slate-800">
                  {{ extension.display_name }}
                </h2>
                <span
                  class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                  :class="
                    extension.is_enabled
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-slate-100 text-slate-500'
                  "
                  >{{ extension.is_enabled ? 'Enabled' : 'Disabled' }}</span
                >
              </div>
              <p class="mt-1 text-xs text-slate-500">
                {{ extension.username ?? 'No Switch username' }}
              </p>
              <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-500">
                <span class="inline-flex items-center gap-2"
                  ><PhoneIcon class="size-4 text-brand-500" />
                  {{ extension.extension ?? 'No extension number' }}</span
                >
                <span class="inline-flex items-center gap-2"
                  ><EnvelopeIcon class="size-4 text-brand-500" />
                  {{ extension.email ?? 'No email address' }}</span
                >
                <span class="inline-flex items-center gap-2"
                  ><ClockIcon class="size-4 text-brand-500" />
                  {{ extension.timezone ?? 'Default timezone' }}</span
                >
              </div>
            </div>
          </div>
        </article>

        <article class="card-surface p-5">
          <div class="flex items-start gap-3">
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-600"
              ><CheckCircleIcon class="size-5"
            /></span>
            <div>
              <p class="eyebrow">Projection state</p>
              <h2 class="mt-1 text-sm font-semibold text-slate-700">
                {{ humanize(extension.sync_status) }}
              </h2>
              <p class="mt-2 text-[11px] leading-5 text-slate-500">
                {{ formatDate(extension.last_synced_at) }}
              </p>
            </div>
          </div>
          <div
            class="mt-5 grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100 pt-4 text-center"
          >
            <div>
              <div class="text-lg font-semibold text-slate-700">{{ extension.devices.length }}</div>
              <div class="text-[10px] text-slate-400">Devices</div>
            </div>
            <div>
              <div class="text-lg font-semibold text-slate-700">
                {{ extension.voicemail_boxes.length }}
              </div>
              <div class="text-[10px] text-slate-400">Voicemail</div>
            </div>
            <div>
              <div class="text-lg font-semibold text-slate-700">
                {{ extension.callflows.length }}
              </div>
              <div class="text-[10px] text-slate-400">Callflows</div>
            </div>
          </div>
        </article>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-blue-50 text-info"
              ><DevicePhoneMobileIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Assigned devices</h2>
              <p class="text-[10px] text-slate-400">Desk phones, softphones, and SIP endpoints</p>
            </div>
          </header>
          <div
            v-if="extension.devices.length === 0"
            class="px-5 py-10 text-center text-xs text-slate-400"
          >
            No device is assigned to this extension.
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="device in extension.devices"
              :key="device.id"
              class="flex items-center gap-4 px-5 py-4"
            >
              <span
                class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500"
                ><PhoneIcon class="size-4"
              /></span>
              <div class="min-w-0 flex-1">
                <div class="truncate text-xs font-semibold text-slate-700">
                  {{ device.name ?? 'Unnamed device' }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400">
                  {{
                    [device.make, device.model].filter(Boolean).join(' ') ||
                    humanize(device.device_type ?? 'SIP device')
                  }}<span v-if="device.mac_address"> · {{ device.mac_address }}</span>
                </div>
              </div>
              <span
                class="rounded-full px-2 py-1 text-[10px] font-bold"
                :class="
                  device.is_enabled
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-slate-100 text-slate-500'
                "
                >{{ device.is_enabled ? 'Enabled' : 'Disabled' }}</span
              >
            </li>
          </ul>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-purple-50 text-purple-600"
              ><MicrophoneIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Voicemail boxes</h2>
              <p class="text-[10px] text-slate-400">Mailboxes owned by this Switch user</p>
            </div>
          </header>
          <div
            v-if="extension.voicemail_boxes.length === 0"
            class="px-5 py-10 text-center text-xs text-slate-400"
          >
            No voicemail box is assigned to this extension.
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="voicemail in extension.voicemail_boxes"
              :key="voicemail.id"
              class="flex items-center gap-4 px-5 py-4"
            >
              <span
                class="grid size-9 shrink-0 place-items-center rounded-full bg-purple-50 font-mono text-xs font-bold text-purple-600"
                >{{ voicemail.mailbox?.slice(-2) ?? 'VM' }}</span
              >
              <div class="min-w-0 flex-1">
                <div class="truncate text-xs font-semibold text-slate-700">
                  {{ voicemail.name ?? 'Voicemail box' }}
                </div>
                <div class="mt-1 text-[10px] text-slate-400">
                  Mailbox {{ voicemail.mailbox ?? 'not assigned' }}
                </div>
              </div>
              <span
                class="rounded-full px-2 py-1 text-[10px] font-bold"
                :class="
                  voicemail.is_setup === true
                    ? 'bg-emerald-50 text-emerald-700'
                    : voicemail.is_setup === false
                      ? 'bg-amber-50 text-amber-700'
                      : 'bg-slate-100 text-slate-500'
                "
                >{{
                  voicemail.is_setup === true
                    ? 'Set up'
                    : voicemail.is_setup === false
                      ? 'Not set up'
                      : 'Projected'
                }}</span
              >
            </li>
          </ul>
        </article>

        <article class="card-surface overflow-hidden xl:col-span-2">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-9 place-items-center rounded-md bg-amber-50 text-warning"
              ><ArrowPathRoundedSquareIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Call routing</h2>
              <p class="text-[10px] text-slate-400">Callflows matched to this extension number</p>
            </div>
          </header>
          <div
            v-if="extension.callflows.length === 0"
            class="px-5 py-10 text-center text-xs text-slate-400"
          >
            No user callflow is matched to this extension number.
          </div>
          <div v-else class="grid gap-4 p-5 md:grid-cols-2">
            <div
              v-for="callflow in extension.callflows"
              :key="callflow.id"
              class="rounded-md border border-slate-100 bg-slate-50/60 p-4"
            >
              <div class="flex items-start gap-3">
                <span
                  class="grid size-9 shrink-0 place-items-center rounded-md bg-white text-warning shadow-sm"
                  ><ArrowPathRoundedSquareIcon class="size-4"
                /></span>
                <div class="min-w-0">
                  <h3 class="truncate text-xs font-semibold text-slate-700">
                    {{ callflow.name ?? 'User callflow' }}
                  </h3>
                  <p class="mt-1 font-mono text-[10px] text-brand-600">
                    {{ callflow.numbers.join(', ') || 'No number' }}
                  </p>
                </div>
              </div>
              <div class="mt-4 flex flex-wrap gap-1.5">
                <span
                  v-for="module in callflow.modules"
                  :key="module"
                  class="rounded bg-white px-2 py-1 text-[10px] font-medium text-slate-500 ring-1 ring-slate-200"
                  >{{ humanize(module) }}</span
                ><span v-if="callflow.modules.length === 0" class="text-[10px] text-slate-400"
                  >No module summary available</span
                >
              </div>
            </div>
          </div>
        </article>
      </div>

      <div class="mt-5 flex items-center gap-2 text-[10px] text-slate-400">
        <UserIcon class="size-3.5" /> Data is served from the MySQL projection; Switch remains the
        system of record.
      </div>
    </template>
  </div>

  <ExtensionEditPanel
    v-if="editPanelOpen && extension"
    :extension="extension"
    :saving="extensions.mutationLoading"
    :error="extensions.mutationError"
    :field-errors="extensions.fieldErrors"
    :options="extensions.formOptions"
    :voicemail-box="voicemail.detail"
    :voicemail-options="voicemail.formOptions"
    @close="editPanelOpen = false"
    @save="updateExtension"
  />
  <ExtensionDeletionPreviewPanel
    v-if="deletionPanelOpen"
    :preview="extensions.deletionPreview"
    :loading="extensions.previewLoading"
    :error="extensions.previewError"
    :deleting="extensions.deletionLoading"
    :deletion-error="extensions.deletionError"
    :field-errors="extensions.fieldErrors"
    @close="deletionPanelOpen = false"
    @delete="deleteExtension"
  />
</template>
