<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { LockClosedIcon, PlusIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useGlobalSearchListQuery } from '@/domains/global-search/composables/useGlobalSearchListQuery'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import { crudRowActions, type RowAction } from '@/shared/components/rowAction'
import { useVisibilityAwarePolling } from '@/shared/composables/useVisibilityAwarePolling'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import ConferenceFormPanel from '../components/ConferenceFormPanel.vue'
import ConferenceParticipantsPanel from '../components/ConferenceParticipantsPanel.vue'
import { useConferenceStore } from '../stores/conferenceStore'
import type {
  Conference,
  ConferenceBulkParticipantAction,
  ConferenceInput,
  ConferenceParticipant,
  ConferenceParticipantAction,
} from '../types/conference'

const accounts = useAccountStore()
const conferences = useConferenceStore()
const globalSearchQuery = useGlobalSearchListQuery()
const panelOpen = ref(false)
const confirmDelete = ref(false)
const liveConference = ref<Conference | null>(null)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const lastSynchronizedAt = computed(() => latestSynchronizedAt(conferences.records))
const activeParticipants = computed(() =>
  conferences.records.reduce(
    (sum, item) => sum + item.runtime.members + item.runtime.moderators,
    0,
  ),
)
const liveRoomPollingPaused = computed(
  () =>
    conferences.participantsLoading ||
    conferences.participantControlId !== null ||
    conferences.bulkControllingAction !== null ||
    conferences.playingMedia,
)

async function refreshLiveParticipants(): Promise<void> {
  if (!accounts.selectedId || !liveConference.value) return
  await conferences.loadParticipants(accounts.selectedId, liveConference.value.id)
}

useVisibilityAwarePolling({
  active: computed(() => accounts.selectedId !== null && liveConference.value !== null),
  paused: liveRoomPollingPaused,
  intervalMs: 5_000,
  task: refreshLiveParticipants,
})

watch(
  [() => accounts.selectedId, globalSearchQuery],
  ([id, searchQuery]) => {
    panelOpen.value = false
    liveConference.value = null
    conferences.reset()
    conferences.search = searchQuery
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
  if (accounts.selectedId && (await conferences.remove(accounts.selectedId))) {
    confirmDelete.value = false
    panelOpen.value = false
  }
}
async function control(record: Conference): Promise<void> {
  if (!accounts.selectedId) return
  await conferences.control(
    accounts.selectedId,
    record,
    record.runtime.is_locked ? 'unlock' : 'lock',
  )
}
async function manageParticipants(record: Conference): Promise<void> {
  if (!accounts.selectedId) return
  liveConference.value = record
  await Promise.all([
    conferences.loadOptions(accounts.selectedId),
    conferences.loadParticipants(accounts.selectedId, record.id),
  ])
}
async function controlParticipant(
  participant: ConferenceParticipant,
  action: ConferenceParticipantAction,
): Promise<void> {
  if (!accounts.selectedId || !liveConference.value) return
  await conferences.controlParticipant(
    accounts.selectedId,
    liveConference.value,
    participant,
    action,
  )
}
async function controlParticipants(
  action: ConferenceBulkParticipantAction,
  expectedParticipantCount: number,
  expectedTargetCount: number,
): Promise<void> {
  if (!accounts.selectedId || !liveConference.value) return
  await conferences.controlParticipants(
    accounts.selectedId,
    liveConference.value,
    action,
    expectedParticipantCount,
    expectedTargetCount,
  )
}
async function playMedia(mediaId: string, participantId: string | null): Promise<void> {
  if (!accounts.selectedId || !liveConference.value) return
  await conferences.playMedia(accounts.selectedId, liveConference.value, mediaId, participantId)
}

function conferenceActions(record: Conference): RowAction[] {
  const participantCount = record.runtime.members + record.runtime.moderators
  const canControl = canManage.value && (record.runtime.is_locked || participantCount > 0)

  return [
    ...crudRowActions(canManage.value),
    ...(canControl
      ? [
          {
            id: 'control',
            label: record.runtime.is_locked ? 'Unlock room' : 'Lock room',
            icon: record.runtime.is_locked ? ('unlock' as const) : ('lock' as const),
            disabled: conferences.controllingId !== null,
          },
        ]
      : []),
    ...(participantCount > 0
      ? [{ id: 'participants', label: 'Manage live room', icon: 'participants' as const }]
      : []),
  ]
}

async function handleRowAction(actionId: string, record: Conference): Promise<void> {
  if (actionId === 'delete' && accounts.selectedId) {
    await conferences.prepare(accounts.selectedId, record.id)
    confirmDelete.value = conferences.detail !== null
    return
  }

  if (actionId === 'control') {
    void control(record)
  } else if (actionId === 'participants') {
    void manageParticipants(record)
  } else {
    void open(record.id)
  }
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
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex gap-2">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="conferences.synchronizing"
            :disabled="conferences.synchronizing"
            @sync="accounts.selectedId && conferences.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
            @click="open()"
          >
            <PlusIcon class="size-4" />Create conference
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
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
      <SearchInput
        v-model="conferences.search"
        label="Search conferences"
        class="min-w-0 flex-1"
        placeholder="Search conferences or access numbers…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="accounts.selectedId && conferences.load(accounts.selectedId)"
      /><FormSelect
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
            <th scope="col" class="w-12" aria-label="Actions"></th>
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
            <td class="px-3 text-right">
              <RowActionMenu
                :label="`Actions for ${record.name}`"
                :actions="conferenceActions(record)"
                @select="handleRowAction($event, record)"
              />
            </td>
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
  <ConferenceParticipantsPanel
    v-if="liveConference"
    :conference="liveConference"
    :participants="conferences.participants"
    :loading="conferences.participantsLoading"
    :controlling-id="conferences.participantControlId"
    :playable-media="conferences.options.playable_media"
    :playing-media="conferences.playingMedia"
    :bulk-controlling-action="conferences.bulkControllingAction"
    :bulk-control-observation="conferences.bulkControlObservation"
    :error="conferences.participantError"
    :can-manage="canManage"
    @close="liveConference = null"
    @refresh="refreshLiveParticipants"
    @control="controlParticipant"
    @bulk-control="controlParticipants"
    @play-media="playMedia"
  />
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete conference"
    :description="`Delete ${conferences.detail?.name ?? 'this conference'}? Active callers should leave the room first.`"
    confirm-label="Delete conference"
    tone="danger"
    :busy="conferences.saving"
    @close="confirmDelete = false"
    @confirm="remove"
  />
</template>
