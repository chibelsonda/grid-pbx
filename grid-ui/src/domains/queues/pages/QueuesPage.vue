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
import SearchInput from '@/shared/components/SearchInput.vue'
import AgentStatusPanel from '../components/AgentStatusPanel.vue'
import QueueFormPanel from '../components/QueueFormPanel.vue'
import { useQueueStore } from '../stores/queueStore'
import type { Agent, AgentStatusInput, QueueInput } from '../types/queue'

const accounts = useAccountStore()
const queues = useQueueStore()
const tab = ref<'queues' | 'agents'>('queues')
const queuePanel = ref(false)
const agentPanel = ref(false)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)

watch(
  () => accounts.selectedId,
  (id) => {
    queuePanel.value = false
    agentPanel.value = false
    queues.reset()
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
  if (!accounts.selectedId) return
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
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Queues</p>
        <h1 class="text-xl font-semibold text-slate-800">Queues & Agents</h1>
        <p class="mt-1 text-xs text-slate-500">
          Manage ACDc caller queues, projected rosters, and live agent state.
        </p>
      </div>
      <div class="ml-auto flex gap-2">
        <button
          v-if="canManage"
          :disabled="queues.synchronizing"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 disabled:opacity-40"
          @click="accounts.selectedId && queues.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon
            class="size-4"
            :class="queues.synchronizing && 'animate-spin'"
          />Sync</button
        ><button
          v-if="canManage"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="openQueue()"
        >
          <PlusIcon class="size-4" />New queue
        </button>
      </div>
    </div>
  </section>
  <div class="page-container py-4 sm:py-6 lg:py-8">
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
      <TabList class="mb-4 flex items-center gap-2 border-b border-slate-200">
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
      >
        {{ queues.error }}
      </div>
      <TabPanels>
        <TabPanel class="focus:outline-none">
          <form
            class="mb-4 flex gap-3"
            @submit.prevent="accounts.selectedId && queues.load(accounts.selectedId)"
          >
            <SearchInput v-model="queues.search" label="Search queues" class="min-w-0 flex-1" placeholder="Search queues…" input-class="h-10 bg-white text-xs shadow-sm" /><button
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
                  <th class="px-5 py-3.5">Queue</th>
                  <th class="px-5 py-3.5">Strategy</th>
                  <th class="px-5 py-3.5">Agents</th>
                  <th class="px-5 py-3.5">Capacity</th>
                  <th class="w-12"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs">
                <tr v-if="queues.loading">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">Loading queues…</td>
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
                  class="cursor-pointer hover:bg-slate-50"
                  @click="openQueue(record.id)"
                >
                  <td class="px-5 py-4 font-semibold text-slate-700">{{ record.name }}</td>
                  <td class="px-5 py-4 text-slate-500">
                    {{ record.strategy === 'most_idle' ? 'Most idle' : 'Round robin' }}
                  </td>
                  <td class="px-5 py-4 text-slate-500">{{ record.agent_count ?? 0 }}</td>
                  <td class="px-5 py-4 text-slate-500">
                    {{ record.max_queue_size || 'Unlimited' }}
                  </td>
                  <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </TabPanel>
        <TabPanel class="card-surface overflow-hidden focus:outline-none">
          <table class="w-full text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Agent</th>
                <th class="px-5 py-3.5">Extension</th>
                <th class="px-5 py-3.5">Queue assignments</th>
                <th class="w-12"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="queues.loading">
                <td colspan="4" class="px-5 py-14 text-center text-slate-400">Loading agents…</td>
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
                class="cursor-pointer hover:bg-slate-50"
                @click="openAgent(agent)"
              >
                <td class="px-5 py-4 font-semibold text-slate-700">{{ agent.name }}</td>
                <td class="px-5 py-4 text-slate-500">{{ agent.extension ?? '—' }}</td>
                <td class="px-5 py-4 text-slate-500">
                  {{ agent.queues.map(({ name }) => name).join(', ') }}
                </td>
                <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
              </tr>
            </tbody>
          </table>
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
    :can-manage="canManage"
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
    :can-manage="canManage"
    @close="agentPanel = false"
    @save="saveAgentStatus"
  />
</template>
