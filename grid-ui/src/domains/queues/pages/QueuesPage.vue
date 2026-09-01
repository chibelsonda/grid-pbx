<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import {
  ArrowPathIcon,
  ChevronRightIcon,
  PlusIcon,
  QueueListIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useGlobalSearchListQuery } from '@/domains/global-search/composables/useGlobalSearchListQuery'
import SearchInput from '@/shared/components/SearchInput.vue'
import AgentStatusPanel from '../components/AgentStatusPanel.vue'
import QueueFormPanel from '../components/QueueFormPanel.vue'
import { useQueueStore } from '../stores/queueStore'
import type { Agent, AgentStatusInput, QueueInput } from '../types/queue'

const accounts = useAccountStore()
const queues = useQueueStore()
const globalSearchQuery = useGlobalSearchListQuery()
const tab = ref<'queues' | 'agents'>('queues')
const queuePanel = ref(false)
const agentPanel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const configurationAvailable = computed(() => queues.options.capabilities.configuration_available)
const liveAgentControlsAvailable = computed(
  () => queues.options.capabilities.live_agent_controls_available,
)

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
  if (!accounts.selectedId || !liveAgentControlsAvailable.value) return
  agentPanel.value = true
  await queues.prepareAgent(accounts.selectedId, agent)
}
async function save(input: QueueInput): Promise<void> {
  if (accounts.selectedId && (await queues.save(accounts.selectedId, input)))
    queuePanel.value = false
}
async function remove(): Promise<void> {
  if (accounts.selectedId && (await queues.remove(accounts.selectedId))) queuePanel.value = false
}
async function saveAgentStatus(input: AgentStatusInput): Promise<void> {
  if (accounts.selectedId && (await queues.updateAgentStatus(accounts.selectedId, input)))
    agentPanel.value = false
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
      <div class="flex w-full flex-wrap gap-2 sm:ml-auto sm:w-auto">
        <button
          v-if="canManage"
          :disabled="queues.synchronizing || !configurationAvailable"
          :title="configurationAvailable ? undefined : 'Switch Queue configuration is unavailable.'"
          class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 disabled:opacity-40 sm:flex-none"
          @click="accounts.selectedId && queues.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon
            class="size-4"
            :class="queues.synchronizing && 'animate-spin'"
          />Sync</button
        ><button
          v-if="canManage"
          :disabled="!configurationAvailable"
          :title="configurationAvailable ? undefined : 'Switch Queue configuration is unavailable.'"
          class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:opacity-40 sm:flex-none"
          @click="openQueue()"
        >
          <PlusIcon class="size-4" />New queue
        </button>
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
        <TabPanel class="card-surface overflow-hidden focus:outline-none">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] text-left" :aria-busy="queues.loading">
              <caption class="sr-only">
                Queue agents for the selected Switch account
              </caption>
              <thead
                class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
              >
                <tr>
                  <th scope="col" class="px-5 py-3.5">Agent</th>
                  <th scope="col" class="px-5 py-3.5">Extension</th>
                  <th scope="col" class="px-5 py-3.5">Queue assignments</th>
                  <th scope="col" class="w-12" aria-label="Open agent status"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs">
                <tr v-if="queues.loading">
                  <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                    <span role="status">Loading agents…</span>
                  </td>
                </tr>
                <tr v-else-if="!accounts.selectedId">
                  <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                    Select an account to inspect its queue agents.
                  </td>
                </tr>
                <tr v-else-if="!queues.agents.length">
                  <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                    No agents are assigned to projected queues.
                  </td>
                </tr>
                <tr
                  v-for="agent in queues.agents"
                  v-else
                  :key="agent.id"
                  :class="liveAgentControlsAvailable ? 'hover:bg-slate-50' : 'opacity-60'"
                >
                  <td class="px-5 py-4">
                    <button
                      type="button"
                      :disabled="!liveAgentControlsAvailable"
                      class="rounded-sm font-semibold text-slate-700 outline-none hover:text-brand-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
                      @click="openAgent(agent)"
                    >
                      {{ agent.name }}
                    </button>
                  </td>
                  <td class="px-5 py-4 text-slate-500">{{ agent.extension ?? '—' }}</td>
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
    :error="queues.mutationError"
    :field-errors="queues.fieldErrors"
    :can-manage="canManage && liveAgentControlsAvailable"
    @close="agentPanel = false"
    @save="saveAgentStatus"
  />
</template>
