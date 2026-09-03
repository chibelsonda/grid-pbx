<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Bars3BottomLeftIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useGlobalSearchListQuery } from '@/domains/global-search/composables/useGlobalSearchListQuery'
import AppAlert from '@/shared/components/AppAlert.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import { crudRowActions } from '@/shared/components/rowAction'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import MenuFormPanel from '../components/MenuFormPanel.vue'
import { useMenuStore } from '../stores/menuStore'
import type { MenuInput } from '../types/menu'

const accounts = useAccountStore()
const menus = useMenuStore()
const globalSearchQuery = useGlobalSearchListQuery()
const panelOpen = ref(false)
const confirmDelete = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const lastSynchronizedAt = computed(() => latestSynchronizedAt(menus.records))

watch(
  [() => accounts.selectedId, globalSearchQuery],
  ([id, searchQuery]) => {
    panelOpen.value = false
    menus.reset()
    menus.search = searchQuery
    if (id) void menus.load(id)
  },
  { immediate: true },
)

async function open(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await menus.prepare(accounts.selectedId, id)
  panelOpen.value = true
}
async function save(input: MenuInput): Promise<void> {
  if (accounts.selectedId && (await menus.save(accounts.selectedId, input))) panelOpen.value = false
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await menus.remove(accounts.selectedId))) {
    confirmDelete.value = false
    panelOpen.value = false
  }
}
async function handleRowAction(actionId: string, id: string): Promise<void> {
  await open(id)
  if (actionId === 'delete' && menus.detail) {
    panelOpen.value = false
    confirmDelete.value = true
  }
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Menus</p>
        <h1 class="text-xl font-semibold text-slate-800">Menus & IVR</h1>
        <p class="mt-1 text-xs text-slate-500">
          Manage voice menus, digit collection, prompts, and call-routing destinations.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex gap-2">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="menus.synchronizing"
            :disabled="menus.synchronizing"
            @sync="accounts.selectedId && menus.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
            @click="open()"
          >
            <PlusIcon class="size-4" />Create menu
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <article class="card-surface mb-5 flex items-center gap-4 p-4">
      <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
        ><Bars3BottomLeftIcon class="size-5"
      /></span>
      <div>
        <p class="text-lg font-semibold text-slate-700">{{ menus.total }}</p>
        <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
          Projected voice menus
        </p>
      </div>
    </article>
    <AppAlert
      v-if="menus.error"
      :message="menus.error"
      tone="error"
      class="mb-4"
      @dismiss="menus.error = null"
    />
    <form
      class="mb-4 flex gap-3"
      @submit.prevent="accounts.selectedId && menus.load(accounts.selectedId)"
    >
      <SearchInput
        v-model="menus.search"
        label="Search menus"
        class="min-w-0 flex-1"
        placeholder="Search menus…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="accounts.selectedId && menus.load(accounts.selectedId)"
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
            <th class="px-5 py-3.5">Menu</th>
            <th class="px-5 py-3.5">Greeting</th>
            <th class="px-5 py-3.5">Digit timeout</th>
            <th class="px-5 py-3.5">Retries</th>
            <th scope="col" class="w-12" aria-label="Actions"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <tr v-if="menus.loading">
            <td colspan="5" class="px-5 py-14 text-center text-slate-400">Loading menus…</td>
          </tr>
          <tr v-else-if="!menus.records.length">
            <td colspan="5" class="px-5 py-14 text-center text-slate-400">
              No menus are projected for this account.
            </td>
          </tr>
          <tr
            v-for="record in menus.records"
            v-else
            :key="record.id"
            class="cursor-pointer hover:bg-slate-50"
            @click="open(record.id)"
          >
            <td class="px-5 py-4 font-semibold text-slate-700">{{ record.name }}</td>
            <td class="px-5 py-4 text-slate-500">
              {{ record.greeting_media?.name ?? 'No custom greeting' }}
            </td>
            <td class="px-5 py-4 text-slate-500">{{ record.timeout }} ms</td>
            <td class="px-5 py-4 text-slate-500">{{ record.retries }}</td>
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
  <MenuFormPanel
    v-if="panelOpen"
    :record="menus.detail"
    :options="menus.options"
    :saving="menus.saving"
    :error="menus.mutationError"
    :field-errors="menus.fieldErrors"
    :can-manage="canManage"
    @close="panelOpen = false"
    @save="save"
    @remove="remove"
  />
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete menu"
    :description="`Delete ${menus.detail?.name ?? 'this menu'} after checking its call-routing dependencies?`"
    confirm-label="Delete menu"
    tone="danger"
    :busy="menus.saving"
    @close="confirmDelete = false"
    @confirm="remove"
  />
</template>
