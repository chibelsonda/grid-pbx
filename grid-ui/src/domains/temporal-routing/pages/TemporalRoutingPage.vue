<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import {
  ArrowPathIcon,
  CalendarDaysIcon,
  ChevronRightIcon,
  ClockIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
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
const canManage = computed(() => accounts.selected?.permissions.can_manage_call_routing ?? false)

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
  if (accounts.selectedId && (await temporal.removeRule(accounts.selectedId)))
    rulePanel.value = false
}

async function removeSet(): Promise<void> {
  if (accounts.selectedId && (await temporal.removeSet(accounts.selectedId))) setPanel.value = false
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
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Routing</p>
        <h1 class="text-xl font-semibold text-slate-800">Business Hours & Schedules</h1>
        <p class="mt-1 text-xs text-slate-500">
          Build recurring rules and reusable rule sets for time-based routing.
        </p>
      </div>
      <div class="ml-auto flex gap-2">
        <button
          v-if="canManage"
          :disabled="temporal.synchronizing"
          class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 disabled:opacity-40"
          @click="accounts.selectedId && temporal.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon class="size-4" :class="temporal.synchronizing && 'animate-spin'" />Sync
        </button>
        <button
          v-if="canManage"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="tab === 'rules' ? openRule() : openSet()"
        >
          <PlusIcon class="size-4" />New {{ tab === 'rules' ? 'rule' : 'rule set' }}
        </button>
      </div>
    </div>
  </section>

  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
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
      >
        {{ temporal.error }}
      </div>
      <form
        class="mb-4 flex gap-3"
        @submit.prevent="accounts.selectedId && temporal.load(accounts.selectedId)"
      >
        <label class="relative min-w-0 flex-1"
          ><MagnifyingGlassIcon
            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" /><input
            v-model="temporal.search"
            type="search"
            placeholder="Search schedules…"
            class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs shadow-sm"
        /></label>
        <button
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
        >
          Search
        </button>
      </form>

      <TabPanels>
        <TabPanel class="card-surface overflow-hidden focus:outline-none">
          <table class="w-full text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Rule</th>
                <th class="px-5 py-3.5">Cycle</th>
                <th class="px-5 py-3.5">Window</th>
                <th class="px-5 py-3.5">Effective status</th>
                <th class="w-12"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="temporal.loading">
                <td colspan="5" class="px-5 py-14 text-center text-slate-400">Loading rules…</td>
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
                class="cursor-pointer hover:bg-slate-50"
                @click="openRule(rule.id)"
              >
                <td class="px-5 py-4 font-semibold text-slate-700">{{ rule.name }}</td>
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
                <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
              </tr>
            </tbody>
          </table>
        </TabPanel>
        <TabPanel class="card-surface overflow-hidden focus:outline-none">
          <table class="w-full text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Rule set</th>
                <th class="px-5 py-3.5">Rules</th>
                <th class="px-5 py-3.5">Effective status</th>
                <th class="w-12"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="temporal.loading">
                <td colspan="4" class="px-5 py-14 text-center text-slate-400">
                  Loading rule sets…
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
                class="cursor-pointer hover:bg-slate-50"
                @click="openSet(set.id)"
              >
                <td class="px-5 py-4 font-semibold text-slate-700">{{ set.name }}</td>
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
                <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
              </tr>
            </tbody>
          </table>
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
</template>
