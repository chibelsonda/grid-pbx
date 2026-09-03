<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import { CalendarDaysIcon, ClockIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import RowActionMenu from '@/shared/components/RowActionMenu.vue'
import type { RowAction } from '@/shared/components/rowAction'
import { latestSynchronizedAt } from '@/shared/utils/projectionSync'
import TemporalRulePanel from '../components/TemporalRulePanel.vue'
import TemporalRuleSetPanel from '../components/TemporalRuleSetPanel.vue'
import { useTemporalRoutingStore } from '../stores/temporalRoutingStore'
import type {
  TemporalControlAction,
  TemporalEffectiveStatus,
  TemporalRuleInput,
  TemporalRuleSetInput,
} from '../types/temporalRouting'

const accounts = useAccountStore()
const temporal = useTemporalRoutingStore()
const tab = ref<'rules' | 'sets'>('rules')
const rulePanel = ref(false)
const setPanel = ref(false)
const pendingDelete = ref<'rule' | 'set' | null>(null)
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)
const lastSynchronizedAt = computed(() =>
  latestSynchronizedAt([...temporal.rules, ...temporal.sets]),
)

watch(
  () => accounts.selectedId,
  (id) => {
    rulePanel.value = false
    setPanel.value = false
    temporal.reset()
    if (id) void temporal.load(id)
  },
  { immediate: true },
)

async function openRule(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await temporal.prepareRule(accounts.selectedId, id)
  rulePanel.value = true
}

async function openSet(id?: string): Promise<void> {
  if (!accounts.selectedId) return
  await temporal.prepareSet(accounts.selectedId, id)
  setPanel.value = true
}

async function saveRule(input: TemporalRuleInput): Promise<void> {
  if (accounts.selectedId && (await temporal.saveRule(accounts.selectedId, input)))
    rulePanel.value = false
}

async function saveSet(input: TemporalRuleSetInput): Promise<void> {
  if (accounts.selectedId && (await temporal.saveSet(accounts.selectedId, input)))
    setPanel.value = false
}

async function removeRule(): Promise<void> {
  if (accounts.selectedId && (await temporal.removeRule(accounts.selectedId))) {
    pendingDelete.value = null
    rulePanel.value = false
  }
}

async function removeSet(): Promise<void> {
  if (accounts.selectedId && (await temporal.removeSet(accounts.selectedId))) {
    pendingDelete.value = null
    setPanel.value = false
  }
}

async function controlRule(action: TemporalControlAction): Promise<void> {
  if (accounts.selectedId) await temporal.controlRule(accounts.selectedId, action)
}

async function controlSet(action: TemporalControlAction): Promise<void> {
  if (accounts.selectedId) await temporal.controlSet(accounts.selectedId, action)
}

function formatWindow(start: number | null, stop: number | null): string {
  if (start === null || stop === null) return 'All day'
  const time = (seconds: number) =>
    `${Math.floor(seconds / 3600)
      .toString()
      .padStart(2, '0')}:${Math.floor((seconds % 3600) / 60)
      .toString()
      .padStart(2, '0')}`
  return `${time(start)}–${time(stop)}`
}

function statusLabel(status: TemporalEffectiveStatus): string {
  const mode = {
    scheduled: 'schedule',
    forced_active: 'forced',
    forced_inactive: 'forced',
    mixed: 'mixed',
    empty: 'empty',
  }[status.override]
  return `${status.is_active ? 'Active' : 'Inactive'} · ${mode}`
}

function rowActions(): RowAction[] {
  if (!canManage.value) return [{ id: 'view', label: 'View details', icon: 'view' }]

  return [
    { id: 'view', label: 'View details', icon: 'view' },
    { id: 'edit', label: 'Edit', icon: 'edit' },
    { id: 'enable', label: 'Force active', icon: 'enable' },
    { id: 'disable', label: 'Force inactive', icon: 'disable' },
    { id: 'reset', label: 'Clear override', icon: 'reset' },
    { id: 'delete', label: 'Delete', icon: 'delete', destructive: true },
  ]
}

async function handleRuleAction(actionId: string, id: string): Promise<void> {
  await openRule(id)
  if (!temporal.ruleDetail) return

  if (actionId === 'delete') {
    rulePanel.value = false
    pendingDelete.value = 'rule'
  } else if (['enable', 'disable', 'reset'].includes(actionId)) {
    rulePanel.value = false
    await controlRule(actionId as TemporalControlAction)
  }
}

async function handleSetAction(actionId: string, id: string): Promise<void> {
  await openSet(id)
  if (!temporal.setDetail) return

  if (actionId === 'delete') {
    setPanel.value = false
    pendingDelete.value = 'set'
  } else if (['enable', 'disable', 'reset'].includes(actionId)) {
    setPanel.value = false
    await controlSet(actionId as TemporalControlAction)
  }
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0 flex-1">
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Routing</p>
        <h1 class="text-xl font-semibold text-slate-800">Business Hours & Schedules</h1>
        <p class="mt-1 text-xs text-slate-500">
          Build recurring rules and reusable rule sets for time-based routing.
        </p>
      </div>
      <div class="flex flex-col items-start gap-1 sm:ml-auto sm:items-end">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <ProjectionSyncButton
            v-if="canManage"
            :synchronizing="temporal.synchronizing"
            :disabled="temporal.synchronizing"
            class="flex-1 sm:flex-none"
            @sync="accounts.selectedId && temporal.synchronize(accounts.selectedId)"
          />
          <button
            v-if="canManage"
            class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white sm:flex-none"
            @click="tab === 'rules' ? openRule() : openSet()"
          >
            <PlusIcon class="size-4" />Create {{ tab === 'rules' ? 'rule' : 'rule set' }}
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
          ><ClockIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ temporal.ruleTotal }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Temporal rules
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><CalendarDaysIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ temporal.setTotal }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Rule sets</p>
        </div>
      </article>
    </div>

    <TabGroup
      :selected-index="tab === 'rules' ? 0 : 1"
      @change="tab = $event === 0 ? 'rules' : 'sets'"
    >
      <TabList
        aria-label="Business hours sections"
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
            Rules
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
            Rule Sets
          </button></Tab
        >
      </TabList>

      <div
        v-if="temporal.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
        role="alert"
      >
        {{ temporal.error }}
      </div>
      <form
        class="mb-4 flex flex-col gap-3 sm:flex-row"
        @submit.prevent="accounts.selectedId && temporal.load(accounts.selectedId)"
      >
        <SearchInput
          v-model="temporal.search"
          label="Search schedules"
          class="min-w-0 flex-1"
          placeholder="Search schedules…"
          input-class="h-10 bg-white text-xs shadow-sm"
          live
          @search="accounts.selectedId && temporal.load(accounts.selectedId)"
        />
        <button
          class="h-10 w-full rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 sm:w-auto"
        >
          Search
        </button>
      </form>

      <TabPanels>
        <TabPanel class="card-surface overflow-hidden focus:outline-none">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left" :aria-busy="temporal.loading">
              <caption class="sr-only">
                Temporal rules for the selected Switch account
              </caption>
              <thead
                class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
              >
                <tr>
                  <th scope="col" class="px-5 py-3.5">Rule</th>
                  <th scope="col" class="px-5 py-3.5">Cycle</th>
                  <th scope="col" class="px-5 py-3.5">Window</th>
                  <th scope="col" class="px-5 py-3.5">Effective status</th>
                  <th scope="col" class="w-12" aria-label="Actions"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs">
                <tr v-if="temporal.loading">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                    <span role="status">Loading rules…</span>
                  </td>
                </tr>
                <tr v-else-if="!accounts.selectedId">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                    Select an account to inspect its temporal rules.
                  </td>
                </tr>
                <tr v-else-if="!temporal.rules.length">
                  <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                    No temporal rules are projected.
                  </td>
                </tr>
                <tr
                  v-for="rule in temporal.rules"
                  v-else
                  :key="rule.id"
                  class="cursor-pointer transition hover:bg-slate-50"
                  @click="openRule(rule.id)"
                >
                  <td class="px-5 py-4">
                    <button
                      type="button"
                      class="rounded-sm font-semibold text-slate-700 outline-none hover:text-brand-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                      @click.stop="openRule(rule.id)"
                    >
                      {{ rule.name }}
                    </button>
                  </td>
                  <td class="px-5 py-4 text-slate-500 capitalize">{{ rule.cycle }}</td>
                  <td class="px-5 py-4 text-slate-500">
                    {{ formatWindow(rule.time_window_start, rule.time_window_stop) }}
                  </td>
                  <td class="px-5 py-4">
                    <span
                      class="rounded-full px-2 py-1 text-[10px] font-semibold"
                      :class="
                        rule.effective_status.is_active
                          ? 'bg-emerald-50 text-emerald-700'
                          : 'bg-slate-100 text-slate-600'
                      "
                      >{{ statusLabel(rule.effective_status) }}</span
                    >
                  </td>
                  <td class="px-3 text-right">
                    <RowActionMenu
                      :label="`Actions for ${rule.name}`"
                      :actions="rowActions()"
                      @select="handleRuleAction($event, rule.id)"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </TabPanel>
        <TabPanel class="card-surface overflow-hidden focus:outline-none">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-left" :aria-busy="temporal.loading">
              <caption class="sr-only">
                Temporal rule sets for the selected Switch account
              </caption>
              <thead
                class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
              >
                <tr>
                  <th scope="col" class="px-5 py-3.5">Rule set</th>
                  <th scope="col" class="px-5 py-3.5">Rules</th>
                  <th scope="col" class="px-5 py-3.5">Effective status</th>
                  <th scope="col" class="w-12" aria-label="Actions"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs">
                <tr v-if="temporal.loading">
                  <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                    <span role="status">Loading rule sets…</span>
                  </td>
                </tr>
                <tr v-else-if="!accounts.selectedId">
                  <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                    Select an account to inspect its temporal rule sets.
                  </td>
                </tr>
                <tr v-else-if="!temporal.sets.length">
                  <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                    No rule sets are projected.
                  </td>
                </tr>
                <tr
                  v-for="set in temporal.sets"
                  v-else
                  :key="set.id"
                  class="cursor-pointer transition hover:bg-slate-50"
                  @click="openSet(set.id)"
                >
                  <td class="px-5 py-4">
                    <button
                      type="button"
                      class="rounded-sm font-semibold text-slate-700 outline-none hover:text-brand-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                      @click.stop="openSet(set.id)"
                    >
                      {{ set.name }}
                    </button>
                  </td>
                  <td class="px-5 py-4 text-slate-500">{{ set.rule_count ?? 0 }}</td>
                  <td class="px-5 py-4">
                    <span
                      class="rounded-full px-2 py-1 text-[10px] font-semibold"
                      :class="
                        set.effective_status.is_active
                          ? 'bg-emerald-50 text-emerald-700'
                          : 'bg-slate-100 text-slate-600'
                      "
                      >{{ statusLabel(set.effective_status) }}</span
                    >
                  </td>
                  <td class="px-3 text-right">
                    <RowActionMenu
                      :label="`Actions for ${set.name}`"
                      :actions="rowActions()"
                      @select="handleSetAction($event, set.id)"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </div>

  <TemporalRulePanel
    v-if="rulePanel"
    :record="temporal.ruleDetail"
    :saving="temporal.saving || temporal.controlling"
    :error="temporal.mutationError"
    :field-errors="temporal.fieldErrors"
    :can-manage="canManage"
    @close="rulePanel = false"
    @save="saveRule"
    @remove="removeRule"
    @control="controlRule"
  />
  <TemporalRuleSetPanel
    v-if="setPanel"
    :record="temporal.setDetail"
    :options="temporal.options"
    :saving="temporal.saving || temporal.controlling"
    :error="temporal.mutationError"
    :field-errors="temporal.fieldErrors"
    :can-manage="canManage"
    @close="setPanel = false"
    @save="saveSet"
    @remove="removeSet"
    @control="controlSet"
  />
  <ConfirmDialog
    :open="pendingDelete !== null"
    :title="pendingDelete === 'rule' ? 'Delete temporal rule' : 'Delete rule set'"
    :description="`Delete ${pendingDelete === 'rule' ? (temporal.ruleDetail?.name ?? 'this rule') : (temporal.setDetail?.name ?? 'this rule set')} after checking its callflow dependencies?`"
    :confirm-label="pendingDelete === 'rule' ? 'Delete rule' : 'Delete rule set'"
    tone="danger"
    :busy="temporal.saving"
    @close="pendingDelete = null"
    @confirm="pendingDelete === 'rule' ? removeRule() : removeSet()"
  />
</template>
