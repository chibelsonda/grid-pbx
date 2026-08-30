<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  ChevronRightIcon,
  LockClosedIcon,
  PlusIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import ConferenceFormPanel from '../components/ConferenceFormPanel.vue'
import { useConferenceStore } from '../stores/conferenceStore'
import type { ConferenceInput } from '../types/conference'

const accounts = useAccountStore()
const conferences = useConferenceStore()
const panelOpen = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const activeParticipants = computed(() =>
  conferences.records.reduce(
    (sum, item) => sum + item.runtime.members + item.runtime.moderators,
    0,
  ),
)
watch(
  () => accounts.selectedId,
  (id) => {
    panelOpen.value = false
    conferences.reset()
    if (id) void conferences.load(id)
  },
  { immediate: true },
)
async function open(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await conferences.prepare(accounts.selectedId, id)
  panelOpen.value = true
}
async function save(input: ConferenceInput): Promise<void> {
  if (accounts.selectedId && (await conferences.save(accounts.selectedId, input)))
    panelOpen.value = false
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await conferences.remove(accounts.selectedId)))
    panelOpen.value = false
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Conferences</p>
        <h1 class="text-xl font-semibold text-slate-800">Conferences</h1>
        <p class="mt-1 text-xs text-slate-500">
          Manage conference rooms, role-based access, and last-observed runtime status.
        </p>
      </div>
      <div class="ml-auto flex gap-2">
        <button
          v-if="canManage"
          :disabled="conferences.synchronizing"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 disabled:opacity-40"
          @click="accounts.selectedId && conferences.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon
            class="size-4"
            :class="conferences.synchronizing && 'animate-spin'"
          />Sync</button
        ><button
          v-if="canManage"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="open()"
        >
          <PlusIcon class="size-4" />New conference
        </button>
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-3">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><UserGroupIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ conferences.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected rooms
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><UserGroupIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ activeParticipants }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Participants on this page
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-amber-50 text-amber-600"
          ><LockClosedIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">
            {{ conferences.records.filter((item) => item.runtime.is_locked).length }}
          </p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Locked rooms
          </p>
        </div>
      </article>
    </div>
    <div
      v-if="conferences.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ conferences.error }}
    </div>
    <form
      class="mb-4 flex gap-3"
      @submit.prevent="accounts.selectedId && conferences.load(accounts.selectedId)"
    >
      <SearchInput v-model="conferences.search" label="Search conferences" class="min-w-0 flex-1" placeholder="Search conferences or access numbers…" input-class="h-10 bg-white text-xs shadow-sm" /><FormSelect
        v-model="conferences.status"
        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
        ><option value="">All rooms</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="locked">Locked</option></FormSelect
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
            <th class="px-5 py-3.5">Conference</th>
            <th class="px-5 py-3.5">Access</th>
            <th class="px-5 py-3.5">Owner</th>
            <th class="px-5 py-3.5">Live status</th>
            <th class="w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <tr v-if="conferences.loading">
            <td colspan="5" class="px-5 py-14 text-center text-slate-400">Loading conferences…</td>
          </tr>
          <tr v-else-if="!conferences.records.length">
            <td colspan="5" class="px-5 py-14 text-center text-slate-400">
              No conferences are projected for this account.
            </td>
          </tr>
          <tr
            v-for="record in conferences.records"
            v-else
            :key="record.id"
            class="cursor-pointer hover:bg-slate-50"
            @click="open(record.id)"
          >
            <td class="px-5 py-4">
              <p class="font-semibold text-slate-700">{{ record.name }}</p>
              <p class="mt-1 text-[10px] text-slate-400">
                {{ record.require_moderator ? 'Moderator required' : 'Open room' }}
              </p>
            </td>
            <td class="px-5 py-4 text-slate-500">
              {{
                record.conference_numbers.join(', ') ||
                record.member_numbers.join(', ') ||
                'No access number'
              }}
            </td>
            <td class="px-5 py-4 text-slate-500">{{ record.owner?.label ?? 'Unassigned' }}</td>
            <td class="px-5 py-4">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold"
                :class="
                  record.runtime.is_locked
                    ? 'bg-amber-50 text-amber-700'
                    : record.runtime.members + record.runtime.moderators > 0
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-slate-100 text-slate-500'
                "
                >{{
                  record.runtime.is_locked
                    ? 'Locked'
                    : `${record.runtime.members + record.runtime.moderators} active`
                }}</span
              >
            </td>
            <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <ConferenceFormPanel
    v-if="panelOpen"
    :record="conferences.detail"
    :options="conferences.options"
    :saving="conferences.saving"
    :error="conferences.mutationError"
    :field-errors="conferences.fieldErrors"
    :can-manage="canManage"
    @close="panelOpen = false"
    @save="save"
    @remove="remove"
  />
</template>
