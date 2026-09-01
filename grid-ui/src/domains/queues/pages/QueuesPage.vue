<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import { ChevronRightIcon, PlusIcon, QueueListIcon, UsersIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useGlobalSearchListQuery } from '@/domains/global-search/composables/useGlobalSearchListQuery'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import { useVisibilityAwarePolling } from '@/shared/composables/useVisibilityAwarePolling'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import AgentAvailabilityBadge from '../components/AgentAvailabilityBadge.vue'
import AgentStatisticsPanel from '../components/AgentStatisticsPanel.vue'
import AgentStatusPanel from '../components/AgentStatusPanel.vue'
import QueueFormPanel from '../components/QueueFormPanel.vue'
import QueueStatisticsPanel from '../components/QueueStatisticsPanel.vue'
import { useQueueStore } from '../stores/queueStore'
import type { Agent, AgentQueueMembershipInput, AgentStatusInput, QueueInput } from '../types/queue'

const accounts = useAccountStore()
const queues = useQueueStore()
const globalSearchQuery = useGlobalSearchListQuery()
const tab = ref<'queues' | 'agents'>('queues')
const queuePanel = ref(false)
const agentPanel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const configurationAvailable = computed(() => queues.options.capabilities.configuration_available)
const lastSynchronizedAt = computed(() => latestSynchronizedAt(queues.records))
const liveAgentControlsAvailable = computed(
  () => queues.options.capabilities.live_agent_controls_available,
)
const statisticsAvailable = computed(() => queues.options.capabilities.statistics_available)
const agentStatisticsAvailable = computed(
  () => queues.options.capabilities.agent_statistics_available,
)
const agentStatusPollingPaused = computed(() => queues.statusLoading || queues.statusRefreshing)
const statisticsPollingPaused = computed(
  () => queues.loading || queues.statisticsLoading || queues.statisticsRefreshing,
)
const agentStatisticsPollingPaused = computed(
  () => queues.loading || queues.agentStatisticsLoading || queues.agentStatisticsRefreshing,
)
const agentAvailabilityPollingPaused = computed(
  () => queues.loading || queues.agentAvailabilityLoading || queues.agentAvailabilityRefreshing,
)
const agentAvailabilityById = computed(
  () => new Map(queues.agentAvailability?.agents.map((agent) => [agent.id, agent.status]) ?? []),
)

async function refreshAgentStatus(): Promise<void> {
  if (!accounts.selectedId || !agentPanel.value || !liveAgentControlsAvailable.value) return
  await queues.refreshAgentStatus(accounts.selectedId)
}

useVisibilityAwarePolling({
  active: computed(
    () =>
      accounts.selectedId !== null &&
      agentPanel.value &&
      queues.selectedAgent !== null &&
      liveAgentControlsAvailable.value,
  ),
  paused: agentStatusPollingPaused,
  intervalMs: 5_000,
  task: refreshAgentStatus,
})

async function refreshAgentAvailability(): Promise<void> {
  if (!accounts.selectedId || !liveAgentControlsAvailable.value) return
  await queues.refreshAgentAvailability(accounts.selectedId)
}

useVisibilityAwarePolling({
  active: computed(
    () =>
      accounts.selectedId !== null && tab.value === 'agents' && liveAgentControlsAvailable.value,
  ),
  paused: agentAvailabilityPollingPaused,
  intervalMs: 10_000,
  task: refreshAgentAvailability,
})

async function refreshAgentStatistics(): Promise<void> {
  if (!accounts.selectedId || !agentStatisticsAvailable.value) return
  await queues.refreshAgentStatistics(accounts.selectedId)
}

useVisibilityAwarePolling({
  active: computed(
    () => accounts.selectedId !== null && tab.value === 'agents' && agentStatisticsAvailable.value,
  ),
  paused: agentStatisticsPollingPaused,
  intervalMs: 15_000,
  task: refreshAgentStatistics,
})

async function refreshQueueStatistics(): Promise<void> {
  if (!accounts.selectedId || !statisticsAvailable.value) return
  await queues.refreshStatistics(accounts.selectedId)
}

useVisibilityAwarePolling({
  active: computed(() => accounts.selectedId !== null && statisticsAvailable.value),
  paused: statisticsPollingPaused,
  intervalMs: 15_000,
  task: refreshQueueStatistics,
})

watch(
  [() => accounts.selectedId, globalSearchQuery],
  ([id, searchQuery]) => {
    queuePanel.value = false
    agentPanel.value = false
    queues.reset()
    queues.search = searchQuery
    if (id) void queues.load(id)
  },
  { immediate: true },
)

async function openQueue(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await queues.prepare(accounts.selectedId, id)
  queuePanel.value = true
}
async function openAgent(agent: Agent): Promise<void> {
  if (!accounts.selectedId || !configurationAvailable.value) return
  agentPanel.value = true
  await queues.prepareAgent(accounts.selectedId, agent, liveAgentControlsAvailable.value)
}
async function save(input: QueueInput): Promise<void> {
  if (accounts.selectedId && (await queues.save(accounts.selectedId, input)))
    queuePanel.value = false
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await queues.remove(accounts.selectedId))) queuePanel.value = false
}
async function saveAgentStatus(input: AgentStatusInput): Promise<void> {
  if (!accounts.selectedId) return
  await queues.updateAgentStatus(accounts.selectedId, input)
}
async function refreshAgentQueueMemberships(): Promise<void> {
  if (!accounts.selectedId) return
  await queues.refreshAgentQueueMemberships(accounts.selectedId)
}
async function changeAgentQueueMembership(input: AgentQueueMembershipInput): Promise<void> {
  if (!accounts.selectedId) return
  await queues.updateAgentQueueMembership(accounts.selectedId, input)
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Queues</p>
        <h1 class="text-xl font-semibold text-slate-800">Queues & Agents</h1>
        <p class="mt-1 text-xs text-slate-500">
          Manage ACDc caller queues, projected rosters, and live agent state.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="queues.synchronizing"
            :disabled="queues.synchronizing || !configurationAvailable"
            :title="
              configurationAvailable ? undefined : 'Switch Queue configuration is unavailable.'
            "
            class="flex-1 sm:flex-none"
            @sync="accounts.selectedId && queues.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            :disabled="!configurationAvailable"
            :title="
              configurationAvailable ? undefined : 'Switch Queue configuration is unavailable.'
            "
            class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:opacity-40 sm:flex-none"
            @click="openQueue()"
          >
            <PlusIcon class="size-4" />New queue
          </button>
        </div>
        <ProjectionFreshness :last-synchronized-at="lastSynchronizedAt" />
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div
      v-if="!queues.loading && !configurationAvailable"
      class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800"
      role="status"
    >
      Switch Queue configuration is unavailable. Projected data remains read-only until the
      configuration API recovers.
    </div>
    <div
      v-else-if="!queues.loading && !liveAgentControlsAvailable"
      class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800"
      role="status"
    >
      Queue configuration is available, but the connected Switch did not report live agent controls
      as available.
      <span v-if="!queues.options.capabilities.statistics_available">
        Queue statistics are also unavailable in this deployment.
      </span>
    </div>
    <div
      v-else-if="!queues.loading && !statisticsAvailable"
      class="mb-4 rounded-md border border-slate-200 bg-white p-4 text-xs text-slate-600"
      role="status"
    >
      The connected Switch did not report live queue statistics as available. Queue configuration
      and agent controls remain available.
    </div>
    <QueueStatisticsPanel
      v-if="statisticsAvailable"
      :statistics="queues.statistics"
      :loading="queues.statisticsLoading"
      :refreshing="queues.statisticsRefreshing"
      :error="queues.statisticsError"
      @refresh="refreshQueueStatistics"
    />
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><QueueListIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ queues.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected queues
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><UsersIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ queues.agents.length }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Assigned agents
          </p>
        </div>
      </article>
    </div>
    <TabGroup
      :selected-index="tab === 'queues' ? 0 : 1"
      @change="tab = $event === 0 ? 'queues' : 'agents'"
    >
      <TabList
        aria-label="Queue workspace sections"
        class="mb-4 flex items-center gap-2 border-b border-slate-200"
      >
        <Tab v-slot="{ selected }" as="template"
          ><button
            class="border-b-2 px-4 py-3 text-xs font-semibold outline-none"
            :class="
              selected
                ? 'border-brand-500 text-brand-600'
                : 'border-transparent text-slate-400 hover:text-slate-600'
            "
          >
            Queues
          </button></Tab
        >
        <Tab v-slot="{ selected }" as="template"
          ><button
            class="border-b-2 px-4 py-3 text-xs font-semibold outline-none"
            :class="
              selected
                ? 'border-brand-500 text-brand-600'
                : 'border-transparent text-slate-400 hover:text-slate-600'
            "
          >
            Agents
          </button></Tab
        >
      </TabList>
      <div
        v-if="queues.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
        role="alert"
      >
        {{ queues.error }}
      </div>
      <TabPanels>
        <TabPanel class="focus:outline-none">
          <form
            class="mb-4 flex flex-col gap-3 sm:flex-row"
            @submit.prevent="accounts.selectedId && queues.load(accounts.selectedId)"
          >
            <SearchInput
              v-model="queues.search"
              label="Search queues"
              class="min-w-0 flex-1"
              placeholder="Search queues…"
              input-class="h-10 bg-white text-xs shadow-sm"
              live
              @search="accounts.selectedId && queues.load(accounts.selectedId)"
            /><button
              class="h-10 w-full rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 sm:w-auto"
            >
              Search
            </button>
          </form>
          <div class="card-surface overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full min-w-[680px] text-left" :aria-busy="queues.loading">
                <caption class="sr-only">
                  Queues for the selected Switch account
                </caption>
                <thead
                  class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                >
                  <tr>
                    <th scope="col" class="px-5 py-3.5">Queue</th>
                    <th scope="col" class="px-5 py-3.5">Strategy</th>
                    <th scope="col" class="px-5 py-3.5">Agents</th>
                    <th scope="col" class="px-5 py-3.5">Capacity</th>
                    <th scope="col" class="w-12" aria-label="Open queue"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                  <tr v-if="queues.loading">
                    <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                      <span role="status">Loading queues…</span>
                    </td>
                  </tr>
                  <tr v-else-if="!accounts.selectedId">
                    <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                      Select an account to inspect its queues.
                    </td>
                  </tr>
                  <tr v-else-if="!queues.records.length">
                    <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                      No queues are projected. Switch ACDc may not be enabled for this account.
                    </td>
                  </tr>
                  <tr
                    v-for="record in queues.records"
                    v-else
                    :key="record.id"
                    class="hover:bg-slate-50"
                  >
                    <td class="px-5 py-4">
                      <button
                        type="button"
                        class="rounded-sm font-semibold text-slate-700 outline-none hover:text-brand-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                        @click="openQueue(record.id)"
                      >
                        {{ record.name }}
                      </button>
                    </td>
                    <td class="px-5 py-4 text-slate-500">
                      {{ record.strategy === 'most_idle' ? 'Most idle' : 'Round robin' }}
                    </td>
                    <td class="px-5 py-4 text-slate-500">{{ record.agent_count ?? 0 }}</td>
                    <td class="px-5 py-4 text-slate-500">
                      {{ record.max_queue_size || 'Unlimited' }}
                    </td>
                    <td><ChevronRightIcon class="size-4 text-slate-400" aria-hidden="true" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </TabPanel>
        <TabPanel class="focus:outline-none">
          <AgentStatisticsPanel
            v-if="agentStatisticsAvailable"
            :statistics="queues.agentStatistics"
            :loading="queues.agentStatisticsLoading"
            :refreshing="queues.agentStatisticsRefreshing"
            :error="queues.agentStatisticsError"
            @refresh="refreshAgentStatistics"
          />
          <div
            v-else-if="!queues.loading"
            class="mb-4 rounded-md border border-slate-200 bg-white p-4 text-xs text-slate-600"
            role="status"
          >
            The connected Switch did not report aggregate agent statistics as available. Agent
            inventory and any separately available live status controls remain accessible.
          </div>
          <div
            v-if="queues.agentAvailabilityError"
            class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800"
            role="alert"
          >
            {{ queues.agentAvailabilityError }} The last observed availability remains displayed.
          </div>
          <div
            v-if="queues.agentAvailability?.unresolved_agents"
            class="mb-4 rounded-md border border-slate-200 bg-white p-4 text-xs text-slate-600"
            role="status"
          >
            {{ queues.agentAvailability.unresolved_agents }} live Agent state<span
              v-if="queues.agentAvailability.unresolved_agents !== 1"
              >s</span
            >
            could not be matched to projected Queue Agents.
          </div>
          <div class="card-surface overflow-x-auto">
            <table class="w-full min-w-[720px] text-left" :aria-busy="queues.loading">
              <caption class="sr-only">
                Queue agents for the selected Switch account
              </caption>
              <thead
                class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
              >
                <tr>
                  <th scope="col" class="px-5 py-3.5">Agent</th>
                  <th scope="col" class="px-5 py-3.5">Extension</th>
                  <th scope="col" class="px-5 py-3.5">Availability</th>
                  <th scope="col" class="px-5 py-3.5">Queue assignments</th>
                  <th scope="col" class="w-12" aria-label="Open agent status"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs">
                <tr v-if="queues.loading">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                    <span role="status">Loading agents…</span>
                  </td>
                </tr>
                <tr v-else-if="!accounts.selectedId">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                    Select an account to inspect its queue agents.
                  </td>
                </tr>
                <tr v-else-if="!queues.agents.length">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                    No agents are assigned to projected queues.
                  </td>
                </tr>
                <tr
                  v-for="agent in queues.agents"
                  v-else
                  :key="agent.id"
                  :class="configurationAvailable ? 'hover:bg-slate-50' : 'opacity-60'"
                >
                  <td class="px-5 py-4">
                    <button
                      type="button"
                      :disabled="!configurationAvailable"
                      class="rounded-sm font-semibold text-slate-700 outline-none hover:text-brand-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
                      @click="openAgent(agent)"
                    >
                      {{ agent.name }}
                    </button>
                  </td>
                  <td class="px-5 py-4 text-slate-500">{{ agent.extension ?? '—' }}</td>
                  <td class="px-5 py-4">
                    <AgentAvailabilityBadge :status="agentAvailabilityById.get(agent.id) ?? null" />
                  </td>
                  <td class="px-5 py-4 text-slate-500">
                    {{ agent.queues.map(({ name }) => name).join(', ') }}
                  </td>
                  <td><ChevronRightIcon class="size-4 text-slate-400" aria-hidden="true" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </div>
  <QueueFormPanel
    v-if="queuePanel"
    :record="queues.detail"
    :options="queues.options"
    :saving="queues.saving"
    :error="queues.mutationError"
    :field-errors="queues.fieldErrors"
    :can-manage="canManage && configurationAvailable"
    @close="queuePanel = false"
    @save="save"
    @remove="remove"
  />
  <AgentStatusPanel
    v-if="agentPanel && queues.selectedAgent"
    :agent="queues.selectedAgent"
    :current="queues.agentStatus"
    :loading="queues.statusLoading"
    :refreshing="queues.statusRefreshing"
    :last-observed-at="queues.statusLastObservedAt"
    :refresh-error="queues.statusRefreshError"
    :command-accepted="queues.statusCommandAccepted"
    :membership="queues.agentQueueMembership"
    :membership-loading="queues.membershipLoading"
    :membership-saving="queues.membershipSaving"
    :membership-error="queues.membershipError"
    :membership-command-accepted="queues.membershipCommandAccepted"
    :status-available="liveAgentControlsAvailable"
    :error="queues.mutationError"
    :field-errors="queues.fieldErrors"
    :can-manage="canManage && liveAgentControlsAvailable"
    @close="agentPanel = false"
    @refresh="refreshAgentStatus"
    @refresh-memberships="refreshAgentQueueMemberships"
    @change-membership="changeAgentQueueMembership"
    @save="saveAgentStatus"
  />
</template>
