<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  ChevronRightIcon,
  PlusIcon,
  UserGroupIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import GroupFormPanel from '../components/GroupFormPanel.vue'
import { useGroupStore } from '../stores/groupStore'
import type { GroupInput } from '../types/group'

const accounts = useAccountStore()
const groups = useGroupStore()
const panel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
watch(
  () => accounts.selectedId,
  (id) => {
    panel.value = false
    groups.reset()
    if (id) void groups.load(id)
  },
  { immediate: true },
)
async function open(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await groups.prepare(accounts.selectedId, id)
  panel.value = true
}
async function save(input: GroupInput): Promise<void> {
  if (accounts.selectedId && (await groups.save(accounts.selectedId, input))) panel.value = false
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await groups.remove(accounts.selectedId))) panel.value = false
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Groups</p>
        <h1 class="text-xl font-semibold text-slate-800">Groups & Ring Groups</h1>
        <p class="mt-1 text-xs text-slate-500">
          Build reusable user, device, and nested-group membership for routing.
        </p>
      </div>
      <div class="ml-auto flex gap-2">
        <button
          v-if="canManage"
          :disabled="groups.synchronizing"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 disabled:opacity-40"
          @click="accounts.selectedId && groups.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon
            class="size-4"
            :class="groups.synchronizing && 'animate-spin'"
          />Sync</button
        ><button
          v-if="canManage"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="open()"
        >
          <PlusIcon class="size-4" />New group
        </button>
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><UserGroupIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ groups.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected groups
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><UsersIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">
            {{ groups.records.reduce((sum, item) => sum + (item.member_count ?? 0), 0) }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Members on this page
          </p>
        </div>
      </article>
    </div>
    <form
      class="mb-4 flex gap-3"
      @submit.prevent="accounts.selectedId && groups.load(accounts.selectedId)"
    >
      <SearchInput v-model="groups.search" label="Search groups" class="min-w-0 flex-1" placeholder="Search groups…" input-class="h-10 bg-white text-xs shadow-sm" /><button
        class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
      >
        Search
      </button>
    </form>
    <div
      v-if="groups.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ groups.error }}
    </div>
    <div class="card-surface overflow-hidden">
      <table class="w-full text-left">
        <thead
          class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
        >
          <tr>
            <th class="px-5 py-3.5">Group</th>
            <th class="px-5 py-3.5">Members</th>
            <th class="px-5 py-3.5">Music on hold</th>
            <th class="w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <tr v-if="groups.loading">
            <td colspan="4" class="px-5 py-14 text-center text-slate-400">Loading groups…</td>
          </tr>
          <tr v-else-if="!groups.records.length">
            <td colspan="4" class="px-5 py-14 text-center text-slate-400">
              No groups are projected.
            </td>
          </tr>
          <tr
            v-for="record in groups.records"
            v-else
            :key="record.id"
            class="cursor-pointer hover:bg-slate-50"
            @click="open(record.id)"
          >
            <td class="px-5 py-4 font-semibold text-slate-700">{{ record.name }}</td>
            <td class="px-5 py-4 text-slate-500">{{ record.member_count ?? 0 }}</td>
            <td class="px-5 py-4 text-slate-500">
              {{ record.music_on_hold_media?.name ?? 'Account default' }}
            </td>
            <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <GroupFormPanel
    v-if="panel"
    :record="groups.detail"
    :options="groups.options"
    :saving="groups.saving"
    :error="groups.mutationError"
    :field-errors="groups.fieldErrors"
    :can-manage="canManage"
    @close="panel = false"
    @save="save"
    @remove="remove"
  />
</template>
