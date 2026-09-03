<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BookOpenIcon, PlusIcon, UsersIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useGlobalSearchListQuery } from '@/domains/global-search/composables/useGlobalSearchListQuery'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import { crudRowActions } from '@/shared/components/rowAction'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import DirectoryFormPanel from '../components/DirectoryFormPanel.vue'
import { useDirectoryStore } from '../stores/directoryStore'
import type { DirectoryInput } from '../types/directory'

const accounts = useAccountStore()
const directories = useDirectoryStore()
const globalSearchQuery = useGlobalSearchListQuery()
const panel = ref(false)
const confirmDelete = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const lastSynchronizedAt = computed(() => latestSynchronizedAt(directories.records))
watch(
  [() => accounts.selectedId, globalSearchQuery],
  ([id, searchQuery]) => {
    panel.value = false
    directories.reset()
    directories.search = searchQuery
    if (id) void directories.load(id)
  },
  { immediate: true },
)
async function open(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await directories.prepare(accounts.selectedId, id)
  panel.value = true
}
async function save(input: DirectoryInput): Promise<void> {
  if (accounts.selectedId && (await directories.save(accounts.selectedId, input)))
    panel.value = false
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await directories.remove(accounts.selectedId))) {
    confirmDelete.value = false
    panel.value = false
  }
}
async function handleRowAction(actionId: string, id: string): Promise<void> {
  await open(id)
  if (actionId === 'delete' && directories.detail) {
    panel.value = false
    confirmDelete.value = true
  }
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Directories</p>
        <h1 class="text-xl font-semibold text-slate-800">Directories</h1>
        <p class="mt-1 text-xs text-slate-500">
          Route callers by first or last name without exposing Switch identifiers.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="directories.synchronizing"
            :disabled="directories.synchronizing"
            class="flex-1 sm:flex-none"
            @sync="accounts.selectedId && directories.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm sm:flex-none"
            @click="open()"
          >
            <PlusIcon class="size-4" />Create directory
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><BookOpenIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ directories.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected directories
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><UsersIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">
            {{ directories.records.reduce((sum, item) => sum + (item.member_count ?? 0), 0) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Members on this page
          </p>
        </div>
      </article>
    </div>
    <form
      class="mb-4 flex flex-col gap-3 sm:flex-row"
      @submit.prevent="accounts.selectedId && directories.load(accounts.selectedId)"
    >
      <SearchInput
        v-model="directories.search"
        label="Search directories"
        class="min-w-0 flex-1"
        placeholder="Search directories…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="accounts.selectedId && directories.load(accounts.selectedId)"
      /><button
        class="h-10 w-full rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 sm:w-auto"
      >
        Search
      </button>
    </form>
    <div
      v-if="directories.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
      role="alert"
    >
      {{ directories.error }}
    </div>
    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[680px] text-left" :aria-busy="directories.loading">
          <caption class="sr-only">
            Directories for the selected Switch account
          </caption>
          <thead
            class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
          >
            <tr>
              <th scope="col" class="px-5 py-3.5">Directory</th>
              <th scope="col" class="px-5 py-3.5">Sort</th>
              <th scope="col" class="px-5 py-3.5">DTMF range</th>
              <th scope="col" class="px-5 py-3.5">Members</th>
              <th scope="col" class="w-12" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="directories.loading">
              <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                <span role="status">Loading directories…</span>
              </td>
            </tr>
            <tr v-else-if="!accounts.selectedId">
              <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                Select an account to inspect its directories.
              </td>
            </tr>
            <tr v-else-if="!directories.records.length">
              <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                No directories are projected.
              </td>
            </tr>
            <tr
              v-for="record in directories.records"
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
              <td class="px-5 py-4 text-slate-500">
                {{ record.sort_by === 'first_name' ? 'First name' : 'Last name' }}
              </td>
              <td class="px-5 py-4 text-slate-500">
                {{ record.min_dtmf }}–{{ record.max_dtmf || 'unlimited' }}
              </td>
              <td class="px-5 py-4 text-slate-500">{{ record.member_count ?? 0 }}</td>
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
  <DirectoryFormPanel
    v-if="panel"
    :record="directories.detail"
    :options="directories.options"
    :saving="directories.saving"
    :error="directories.mutationError"
    :field-errors="directories.fieldErrors"
    :can-manage="canManage"
    @close="panel = false"
    @save="save"
    @remove="remove"
  />
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete directory"
    :description="`Delete ${directories.detail?.name ?? 'this directory'} after checking its routing dependencies?`"
    confirm-label="Delete directory"
    tone="danger"
    :busy="directories.saving"
    @close="confirmDelete = false"
    @confirm="remove"
  />
</template>
