<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { HashtagIcon, IdentificationIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import AppAlert from '@/shared/components/AppAlert.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import { crudRowActions } from '@/shared/components/rowAction'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import CallerIdListFormPanel from '../components/CallerIdListFormPanel.vue'
import { useCallerIdListStore } from '../stores/callerIdListStore'
import type { CallerIdListInput } from '../types/callerIdList'

const accounts = useAccountStore()
const lists = useCallerIdListStore()
const route = useRoute()
const router = useRouter()
const panel = ref(false)
const confirmDelete = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const rowActions = computed(() => crudRowActions(canManage.value))
const lastSynchronizedAt = computed(() => latestSynchronizedAt(lists.records))
const visibleEntryCount = computed(() =>
  lists.records.reduce((total, record) => total + (record.entry_count ?? 0), 0),
)

watch(
  [() => accounts.selectedId, () => route.query.caller_id_list],
  ([accountId, callerIdListId], previous) => {
    const accountChanged = accountId !== previous?.[0]

    if (accountChanged) {
      panel.value = false
      confirmDelete.value = false
      lists.reset()
      if (accountId) void lists.load(accountId)
    }

    if (accountId && typeof callerIdListId === 'string') {
      if (callerIdListId === lists.detail?.id) {
        panel.value = true
      } else {
        void loadDetail(accountId, callerIdListId)
      }
    }
  },
  { immediate: true },
)

async function loadDetail(accountId: string, id: string): Promise<void> {
  await lists.prepare(accountId, id)
  panel.value = true
}

async function open(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  if (id) {
    await router.replace({ query: { ...route.query, caller_id_list: id } })

    return
  }

  await lists.prepare(accounts.selectedId)
  panel.value = true
}

async function save(input: CallerIdListInput): Promise<void> {
  if (accounts.selectedId && (await lists.save(accounts.selectedId, input))) {
    panel.value = false
    clearCallerIdListQuery()
  }
}

async function remove(): Promise<void> {
  if (accounts.selectedId && (await lists.remove(accounts.selectedId))) {
    confirmDelete.value = false
    clearCallerIdListQuery()
  }
}

async function requestRemove(): Promise<void> {
  panel.value = false
  await nextTick()
  confirmDelete.value = true
}

async function handleRowAction(actionId: string, id: string): Promise<void> {
  if (actionId === 'delete' && accounts.selectedId) {
    await lists.prepare(accounts.selectedId, id)
    await requestRemove()
    return
  }

  await open(id)
}

function closePanel(): void {
  panel.value = false
  clearCallerIdListQuery()
}

function clearCallerIdListQuery(): void {
  if (!('caller_id_list' in route.query)) return

  const query = { ...route.query }
  delete query.caller_id_list
  void router.replace({ query })
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="mb-1 text-[11px] text-slate-500">GridPBX / Callflows</p>
        <h1 class="text-xl font-semibold text-slate-800">Caller-ID Lists</h1>
        <p class="mt-1 text-xs text-slate-500">
          Reusable caller-number and pattern matches for visual Callflow branches.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="lists.synchronizing"
            :disabled="lists.synchronizing"
            class="flex-1 sm:flex-none"
            @sync="accounts.selectedId && lists.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white sm:flex-none"
            @click="open()"
          >
            <PlusIcon class="size-4" />Create Caller-ID list
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
      </div>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
          <IdentificationIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ lists.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Caller-ID Lists
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600">
          <HashtagIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ visibleEntryCount }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
            Entries in this view
          </p>
        </div>
      </article>
    </div>

    <AppAlert
      v-if="lists.error"
      :message="lists.error"
      tone="error"
      class="mb-4"
      @dismiss="lists.error = null"
    />

    <form
      class="mb-4 flex flex-col gap-3 sm:flex-row"
      @submit.prevent="accounts.selectedId && lists.load(accounts.selectedId)"
    >
      <SearchInput
        v-model="lists.search"
        label="Search Caller-ID Lists"
        class="min-w-0 flex-1"
        placeholder="Search Caller-ID Lists…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="accounts.selectedId && lists.load(accounts.selectedId)"
      />
      <button
        class="h-10 w-full rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-600 sm:w-auto"
      >
        Search
      </button>
    </form>

    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left" :aria-busy="lists.loading">
          <caption class="sr-only">
            Caller-ID Lists for the selected Switch account
          </caption>
          <thead
            class="border-b border-slate-200 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
          >
            <tr>
              <th scope="col" class="px-5 py-3.5">List</th>
              <th scope="col" class="px-5 py-3.5">Organization</th>
              <th scope="col" class="px-5 py-3.5">Entries</th>
              <th scope="col" class="px-5 py-3.5">Sync status</th>
              <th scope="col" class="w-12" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 text-xs">
            <tr v-if="lists.loading">
              <td colspan="5" class="px-5 py-14 text-center text-slate-500">
                <span role="status">Loading Caller-ID Lists…</span>
              </td>
            </tr>
            <tr v-else-if="!accounts.selectedId">
              <td colspan="5" class="px-5 py-14 text-center text-slate-500">
                Select an account to inspect its Caller-ID Lists.
              </td>
            </tr>
            <tr v-else-if="!lists.records.length">
              <td colspan="5" class="px-5 py-14 text-center text-slate-500">
                No Caller-ID Lists are projected.
              </td>
            </tr>
            <tr
              v-for="record in lists.records"
              v-else
              :key="record.id"
              class="cursor-pointer transition hover:bg-slate-50"
              @click="open(record.id)"
            >
              <td class="px-5 py-4">
                <button
                  type="button"
                  class="rounded-sm font-semibold text-slate-700 outline-none hover:text-brand-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                  @click.stop="open(record.id)"
                >
                  {{ record.name }}
                </button>
                <p v-if="record.description" class="mt-1 text-[10px] text-slate-500">
                  {{ record.description }}
                </p>
              </td>
              <td class="px-5 py-4 text-slate-600">{{ record.organization || '—' }}</td>
              <td class="px-5 py-4 text-slate-600">{{ record.entry_count ?? 0 }}</td>
              <td class="px-5 py-4">
                <span
                  class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600"
                >
                  {{ record.sync_status ?? 'unknown' }}
                </span>
              </td>
              <td class="px-3 text-right">
                <RowActionMenu
                  :label="`Actions for ${record.name}`"
                  :actions="rowActions"
                  @select="handleRowAction($event, record.id)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <CallerIdListFormPanel
    v-if="panel"
    :record="lists.detail"
    :saving="lists.saving"
    :error="lists.mutationError"
    :field-errors="lists.fieldErrors"
    :can-manage="canManage"
    @close="closePanel"
    @save="save"
    @request-remove="requestRemove"
  />
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete Caller-ID List"
    description="Delete this list and its entries? Lists used by a Callflow must be detached first."
    confirm-label="Delete list"
    :busy="lists.saving"
    @close="confirmDelete = false"
    @confirm="remove"
  />
</template>
