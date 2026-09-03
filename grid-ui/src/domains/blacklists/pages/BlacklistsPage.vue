<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PlusIcon, ShieldCheckIcon, ShieldExclamationIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import AppAlert from '@/shared/components/AppAlert.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import { crudRowActions } from '@/shared/components/rowAction'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import BlacklistFormPanel from '../components/BlacklistFormPanel.vue'
import { useBlacklistStore } from '../stores/blacklistStore'
import type { BlacklistInput } from '../types/blacklist'

const accounts = useAccountStore()
const blacklists = useBlacklistStore()
const route = useRoute()
const router = useRouter()
const panel = ref(false)
const confirmDelete = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const lastSynchronizedAt = computed(() => latestSynchronizedAt(blacklists.records))
const activeCount = computed(() => blacklists.records.filter((record) => record.is_active).length)
watch(
  [() => accounts.selectedId, () => route.query.blacklist],
  ([accountId, blacklistId], previous) => {
    const accountChanged = accountId !== previous?.[0]

    if (accountChanged) {
      panel.value = false
      blacklists.reset()
      if (accountId) void blacklists.load(accountId)
    }

    if (accountId && typeof blacklistId === 'string') {
      if (blacklistId === blacklists.detail?.id) {
        panel.value = true
      } else {
        void loadDetail(accountId, blacklistId)
      }
    }
  },
  { immediate: true },
)

async function loadDetail(accountId: string, id: string): Promise<void> {
  await blacklists.prepare(accountId, id)
  panel.value = true
}

async function open(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  if (id) {
    await router.replace({ query: { ...route.query, blacklist: id } })

    return
  }

  await blacklists.prepare(accounts.selectedId)
  panel.value = true
}
async function save(input: BlacklistInput): Promise<void> {
  if (accounts.selectedId && (await blacklists.save(accounts.selectedId, input))) {
    panel.value = false
    clearBlacklistQuery()
  }
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await blacklists.remove(accounts.selectedId))) {
    confirmDelete.value = false
    panel.value = false
    clearBlacklistQuery()
  }
}
async function handleRowAction(actionId: string, id: string): Promise<void> {
  if (actionId === 'delete' && accounts.selectedId) {
    await blacklists.prepare(accounts.selectedId, id)
    confirmDelete.value = blacklists.detail !== null
    return
  }

  await open(id)
}
function closePanel(): void {
  panel.value = false
  clearBlacklistQuery()
}
function clearBlacklistQuery(): void {
  if (!('blacklist' in route.query)) return

  const query = { ...route.query }
  delete query.blacklist
  void router.replace({ query })
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Security</p>
        <h1 class="text-xl font-semibold text-slate-800">Blacklists</h1>
        <p class="mt-1 text-xs text-slate-500">
          Protect inbound calls with reusable caller-number lists.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="blacklists.synchronizing"
            :disabled="blacklists.synchronizing"
            class="flex-1 sm:flex-none"
            @sync="accounts.selectedId && blacklists.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white sm:flex-none"
            @click="open()"
          >
            <PlusIcon class="size-4" />Create blacklist
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-slate-100 text-slate-600"
          ><ShieldExclamationIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ blacklists.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Caller lists
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><ShieldCheckIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ activeCount }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Active in this view
          </p>
        </div>
      </article>
    </div>
    <AppAlert
      v-if="blacklists.error"
      :message="blacklists.error"
      tone="error"
      class="mb-4"
      @dismiss="blacklists.error = null"
    />
    <form
      class="mb-4 flex flex-col gap-3 sm:flex-row"
      @submit.prevent="accounts.selectedId && blacklists.load(accounts.selectedId)"
    >
      <SearchInput
        v-model="blacklists.search"
        label="Search blacklists"
        class="min-w-0 flex-1"
        placeholder="Search blacklists…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="accounts.selectedId && blacklists.load(accounts.selectedId)"
      /><button
        class="h-10 w-full rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 sm:w-auto"
      >
        Search
      </button>
    </form>
    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[680px] text-left" :aria-busy="blacklists.loading">
          <caption class="sr-only">
            Blacklists for the selected Switch account
          </caption>
          <thead
            class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
          >
            <tr>
              <th scope="col" class="px-5 py-3.5">Blacklist</th>
              <th scope="col" class="px-5 py-3.5">Numbers</th>
              <th scope="col" class="px-5 py-3.5">Anonymous</th>
              <th scope="col" class="px-5 py-3.5">Account status</th>
              <th scope="col" class="w-12" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="blacklists.loading">
              <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                <span role="status">Loading blacklists…</span>
              </td>
            </tr>
            <tr v-else-if="!accounts.selectedId">
              <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                Select an account to inspect its blacklists.
              </td>
            </tr>
            <tr v-else-if="!blacklists.records.length">
              <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                No blacklists are projected.
              </td>
            </tr>
            <tr
              v-for="record in blacklists.records"
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
              </td>
              <td class="px-5 py-4 text-slate-500">{{ record.number_count ?? 0 }}</td>
              <td class="px-5 py-4 text-slate-500">
                {{ record.should_block_anonymous ? 'Blocked' : 'Allowed' }}
              </td>
              <td class="px-5 py-4">
                <span
                  class="rounded-full px-2 py-1 text-[10px] font-semibold"
                  :class="
                    record.is_active
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-slate-100 text-slate-500'
                  "
                  >{{ record.is_active ? 'Active' : 'Inactive' }}</span
                >
              </td>
              <td class="px-3 text-right">
                <RowActionMenu
                  :label="`Actions for ${record.name}`"
                  :actions="crudRowActions(canManage)"
                  @select="handleRowAction($event, record.id)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <BlacklistFormPanel
    v-if="panel"
    :record="blacklists.detail"
    :saving="blacklists.saving"
    :error="blacklists.mutationError"
    :field-errors="blacklists.fieldErrors"
    :can-manage="canManage"
    @close="closePanel"
    @save="save"
    @remove="remove"
  />
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete blacklist"
    :description="`Delete ${blacklists.detail?.name ?? 'this blacklist'}? This cannot be undone.`"
    confirm-label="Delete blacklist"
    tone="danger"
    :busy="blacklists.saving"
    @close="confirmDelete = false"
    @confirm="remove"
  />
</template>
