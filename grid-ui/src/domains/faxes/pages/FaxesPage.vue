<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ChevronRightIcon,
  InboxArrowDownIcon,
  PaperAirplaneIcon,
  PlusIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import type { RowAction } from '@/shared/components/rowAction'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import FaxBoxFormPanel from '../components/FaxBoxFormPanel.vue'
import FaxDetailPanel from '../components/FaxDetailPanel.vue'
import { useFaxStore } from '../stores/faxStore'
import type { Fax, FaxBoxInput, FaxOperationCapabilities } from '../types/fax'
const accounts = useAccountStore()
const faxes = useFaxStore()
const route = useRoute()
const router = useRouter()
const boxPanel = ref(false)
const messagePanel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const lastSynchronizedAt = computed(() => latestSynchronizedAt(faxes.boxes))
type FaxOperation = keyof FaxOperationCapabilities
const operationLabels: Record<FaxOperation, string> = {
  send: 'Send fax',
  forward: 'Forward received fax',
  resubmit: 'Resubmit sent fax',
  delete_message: 'Delete fax message',
  delete_document: 'Delete fax document',
}
const operationEntries = computed(() => {
  const capabilities = faxes.capabilities
  if (!capabilities) return []

  return (Object.keys(operationLabels) as FaxOperation[]).map((operation) => ({
    operation,
    label: operationLabels[operation],
    capability: capabilities[operation],
  }))
})
watch(
  [() => accounts.selectedId, () => route.query.fax_box],
  ([accountId, faxBoxId], previous) => {
    const accountChanged = accountId !== previous?.[0]

    if (accountChanged) {
      boxPanel.value = false
      messagePanel.value = false
      faxes.reset()
      if (accountId) void faxes.load(accountId)
    }

    if (accountId && typeof faxBoxId === 'string' && faxBoxId !== faxes.boxDetail?.id) {
      void loadBox(accountId, faxBoxId)
    }
  },
  { immediate: true },
)

async function loadBox(accountId: string, id: string): Promise<void> {
  await faxes.prepareBox(accountId, id)
  boxPanel.value = true
}

async function openBox(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  if (id) {
    await router.replace({ query: { ...route.query, fax_box: id } })

    return
  }

  await faxes.prepareBox(accounts.selectedId)
  boxPanel.value = true
}
async function openMessage(id: string): Promise<void> {
  if (!accounts.selectedId) return
  await faxes.prepareMessage(accounts.selectedId, id)
  messagePanel.value = true
}

function faxActions(fax: Fax): RowAction[] {
  return [
    { id: 'view', label: 'View fax', icon: 'view' },
    ...(fax.has_document
      ? [{ id: 'download', label: 'Download document', icon: 'download' as const }]
      : []),
  ]
}

async function handleFaxAction(actionId: string, id: string): Promise<void> {
  await openMessage(id)
  if (actionId === 'download' && accounts.selectedId && faxes.messageDetail) {
    messagePanel.value = false
    await faxes.download(accounts.selectedId)
  }
}
async function saveBox(input: FaxBoxInput): Promise<void> {
  if (accounts.selectedId && (await faxes.saveBox(accounts.selectedId, input))) {
    boxPanel.value = false
    clearFaxBoxQuery()
  }
}
async function removeBox(): Promise<void> {
  if (accounts.selectedId && (await faxes.removeBox(accounts.selectedId))) {
    boxPanel.value = false
    clearFaxBoxQuery()
  }
}
function closeBox(): void {
  boxPanel.value = false
  clearFaxBoxQuery()
}
function clearFaxBoxQuery(): void {
  if (!('fax_box' in route.query)) return

  const query = { ...route.query }
  delete query.fax_box
  void router.replace({ query })
}
</script>
<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Fax</p>
        <h1 class="text-xl font-semibold text-slate-800">Fax boxes & history</h1>
        <p class="mt-1 text-xs text-slate-500">
          Configure inbound fax boxes and securely access projected message documents.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex gap-2">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="faxes.synchronizing"
            :disabled="faxes.synchronizing"
            @sync="accounts.selectedId && faxes.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
            @click="openBox()"
          >
            <PlusIcon class="size-4" />Create fax box
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
      </div>
    </div>
  </section>
  <div class="page-container grid gap-6 py-4 sm:py-6 lg:py-8">
    <div
      v-if="faxes.error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ faxes.error }}
    </div>
    <section v-if="operationEntries.length" class="card-surface overflow-hidden">
      <header class="border-b border-slate-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-700">Fax message operations</h2>
        <p class="mt-1 text-[10px] text-slate-400">
          Installed Switch operations remain unavailable until their safety policies are approved.
        </p>
      </header>
      <dl class="grid gap-px bg-slate-100 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="entry in operationEntries" :key="entry.operation" class="bg-white px-5 py-4">
          <div class="flex items-center justify-between gap-3">
            <dt class="text-xs font-semibold text-slate-700">{{ entry.label }}</dt>
            <dd class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">
              Policy gated
            </dd>
          </div>
          <p class="mt-2 text-[10px] leading-4 text-slate-500">{{ entry.capability.reason }}</p>
        </div>
      </dl>
    </section>
    <section>
      <div class="mb-3 flex items-center gap-2">
        <PrinterIcon class="size-5 text-brand-500" />
        <h2 class="text-sm font-semibold text-slate-700">Fax boxes</h2>
      </div>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="box in faxes.boxes"
          :key="box.id"
          type="button"
          class="card-surface p-5 text-left transition hover:-translate-y-0.5 hover:shadow-md"
          @click="openBox(box.id)"
        >
          <div class="flex items-start gap-3">
            <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><PrinterIcon class="size-4"
            /></span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-700">{{ box.name }}</p>
              <p class="mt-1 truncate text-[10px] text-slate-400">
                {{ box.smtp_email_address || 'No SMTP address' }}
              </p>
            </div>
            <ChevronRightIcon class="size-4 text-slate-400" />
          </div>
          <div
            class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-[10px] text-slate-500"
          >
            <span>{{ box.owner?.label || 'No owner' }}</span
            ><span>{{ box.fax_count ?? 0 }} faxes</span>
          </div>
        </button>
        <p
          v-if="!faxes.loading && !faxes.boxes.length"
          class="card-surface col-span-full p-8 text-center text-xs text-slate-400"
        >
          No fax boxes are projected.
        </p>
      </div>
    </section>
    <section>
      <div class="mb-3 flex items-center gap-2">
        <InboxArrowDownIcon class="size-5 text-brand-500" />
        <h2 class="text-sm font-semibold text-slate-700">Fax history</h2>
        <span class="text-[10px] text-slate-400">{{ faxes.total }} projected</span>
      </div>
      <form
        class="mb-4 flex gap-3"
        @submit.prevent="accounts.selectedId && faxes.load(accounts.selectedId)"
      >
        <SearchInput
          v-model="faxes.search"
          label="Search faxes"
          class="min-w-0 flex-1"
          placeholder="Search sender, recipient, or subject…"
          input-class="h-10 bg-white text-xs"
          live
          @search="accounts.selectedId && faxes.load(accounts.selectedId)"
        /><FormSelect
          v-model="faxes.folder"
          class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
          ><option value="">Inbox & outbox</option>
          <option value="inbox">Inbox</option>
          <option value="outbox">Outbox</option></FormSelect
        ><FormSelect
          v-model="faxes.faxBoxId"
          class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
          ><option value="">All fax boxes</option>
          <option v-for="box in faxes.boxes" :key="box.id" :value="box.id">
            {{ box.name }}
          </option></FormSelect
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
              <th class="px-5 py-3.5">Direction</th>
              <th class="px-5 py-3.5">From / To</th>
              <th class="px-5 py-3.5">Fax box</th>
              <th class="px-5 py-3.5">Pages</th>
              <th class="px-5 py-3.5">Status</th>
              <th scope="col" class="w-12" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="faxes.loading">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">Loading faxes…</td>
            </tr>
            <tr v-else-if="!faxes.messages.length">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                No faxes match the current filters.
              </td>
            </tr>
            <tr
              v-for="fax in faxes.messages"
              v-else
              :key="fax.id"
              class="cursor-pointer hover:bg-slate-50"
              @click="openMessage(fax.id)"
            >
              <td class="px-5 py-4">
                <span
                  class="inline-flex items-center gap-1.5 font-semibold"
                  :class="fax.folder === 'inbox' ? 'text-emerald-600' : 'text-brand-600'"
                  ><InboxArrowDownIcon
                    v-if="fax.folder === 'inbox'"
                    class="size-4"
                  /><PaperAirplaneIcon v-else class="size-4" />{{ fax.folder }}</span
                >
              </td>
              <td class="px-5 py-4">
                <p class="font-semibold text-slate-700">
                  {{ fax.folder === 'inbox' ? fax.from.number : fax.to.number }}
                </p>
                <p class="text-[10px] text-slate-400">
                  {{ fax.created_at ? new Date(fax.created_at).toLocaleString() : 'Unknown time' }}
                </p>
              </td>
              <td class="px-5 py-4 text-slate-500">{{ fax.fax_box?.name || 'Unassigned' }}</td>
              <td class="px-5 py-4 text-slate-500">{{ fax.pages }}</td>
              <td class="px-5 py-4">
                <span
                  class="rounded-full px-2 py-1 text-[10px] font-semibold"
                  :class="
                    fax.successful === false
                      ? 'bg-red-50 text-red-700'
                      : fax.successful === true
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-slate-100 text-slate-500'
                  "
                  >{{ fax.status || 'Unknown' }}</span
                >
              </td>
              <td class="px-3 text-right">
                <RowActionMenu
                  :label="`Actions for fax ${fax.id}`"
                  :actions="faxActions(fax)"
                  @select="handleFaxAction($event, fax.id)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <FaxBoxFormPanel
    v-if="boxPanel"
    :record="faxes.boxDetail"
    :options="faxes.options"
    :saving="faxes.saving"
    :error="faxes.mutationError"
    :field-errors="faxes.fieldErrors"
    :can-manage="canManage"
    @close="closeBox"
    @save="saveBox"
    @remove="removeBox"
  /><FaxDetailPanel
    v-if="messagePanel && faxes.messageDetail"
    :record="faxes.messageDetail"
    :downloading="faxes.downloading"
    :error="faxes.mutationError"
    @close="messagePanel = false"
    @download="accounts.selectedId && faxes.download(accounts.selectedId)"
  />
</template>
