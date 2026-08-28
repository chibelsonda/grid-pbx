<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  BanknotesIcon,
  ChevronRightIcon,
  CircleStackIcon,
  MagnifyingGlassIcon,
  ServerStackIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import ServiceDetailPanel from '../components/ServiceDetailPanel.vue'
import { useServiceStore } from '../stores/serviceStore'
const accounts = useAccountStore()
const services = useServiceStore()
const search = ref('')
const scope = ref('')
const canView = computed(() => accounts.selected?.permissions.can_view_services ?? false)
const quantities = computed(() =>
  (services.overview?.quantities ?? []).filter(
    (row) =>
      (!scope.value || row.scope === scope.value) &&
      (!search.value ||
        `${row.category} ${row.item}`.toLowerCase().includes(search.value.toLowerCase())),
  ),
)
const totalQuantity = computed(() => quantities.value.reduce((sum, row) => sum + row.quantity, 0))
watch(
  () => accounts.selectedId,
  (id) => {
    services.reset()
    if (id && canView.value) void services.load(id)
  },
  { immediate: true },
)
const amount = (value: number): string =>
  new Intl.NumberFormat(undefined, { maximumFractionDigits: 4 }).format(value)
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-[1500px] items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] text-slate-400">GridPBX / Account</p>
        <h1 class="text-xl font-semibold text-slate-800">Services & limits</h1>
        <p class="mt-1 text-xs text-slate-500">
          Read-only service plans, quantities, limits, and billing-impact summary.
        </p>
      </div>
      <div v-if="canView" class="ml-auto flex gap-2">
        <button
          v-if="services.overview"
          class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600"
          @click="services.detailsOpen = true"
        >
          View details</button
        ><button
          :disabled="services.synchronizing"
          class="inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:opacity-50"
          @click="accounts.selectedId && services.synchronize(accounts.selectedId)"
        >
          <ArrowPathIcon class="size-4" :class="services.synchronizing && 'animate-spin'" />Sync
        </button>
      </div>
    </div>
  </section>
  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <div
      v-if="!canView"
      class="rounded-md border border-amber-100 bg-amber-50 p-5 text-sm text-amber-800"
    >
      Service and billing information is available only to account, reseller, or platform
      administrators.
    </div>
    <template v-else
      ><div
        v-if="services.error"
        class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
      >
        {{ services.error }}
      </div>
      <div v-if="services.loading" class="card-surface p-14 text-center text-xs text-slate-400">
        Loading service information…
      </div>
      <div v-else-if="!services.overview" class="card-surface p-14 text-center">
        <CircleStackIcon class="mx-auto size-9 text-slate-300" />
        <p class="mt-3 text-sm font-semibold text-slate-600">No service projection yet</p>
        <p class="mt-1 text-xs text-slate-400">
          Run a read-only synchronization to load the current Switch summary.
        </p>
      </div>
      <template v-else
        ><div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article class="card-surface flex items-center gap-4 p-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><CircleStackIcon class="size-5"
            /></span>
            <div>
              <p class="text-lg font-semibold text-slate-700">
                {{ services.overview.plans.length }}
              </p>
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Assigned plans
              </p>
            </div>
          </article>
          <article class="card-surface flex items-center gap-4 p-4">
            <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600"
              ><ServerStackIcon class="size-5"
            /></span>
            <div>
              <p class="text-lg font-semibold text-slate-700">{{ totalQuantity }}</p>
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Visible quantity
              </p>
            </div>
          </article>
          <article class="card-surface flex items-center gap-4 p-4">
            <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
              ><BanknotesIcon class="size-5"
            /></span>
            <div>
              <p class="text-lg font-semibold text-slate-700">
                {{ amount(services.overview.billing_impact.recurring_amount) }}
              </p>
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Recurring amount
              </p>
            </div>
          </article>
          <article class="card-surface flex items-center gap-4 p-4">
            <span
              class="grid size-10 place-items-center rounded-md"
              :class="
                services.overview.standing.acceptable
                  ? 'bg-emerald-50 text-emerald-600'
                  : 'bg-red-50 text-red-600'
              "
              ><BanknotesIcon class="size-5"
            /></span>
            <div>
              <p
                class="text-sm font-semibold"
                :class="services.overview.standing.acceptable ? 'text-emerald-700' : 'text-red-700'"
              >
                {{ services.overview.standing.acceptable ? 'Acceptable' : 'Attention needed' }}
              </p>
              <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                Account standing
              </p>
            </div>
          </article>
        </div>
        <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_180px]">
          <label class="relative"
            ><MagnifyingGlassIcon
              class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" /><input
              v-model="search"
              type="search"
              placeholder="Search category or item…"
              class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs" /></label
          ><FormSelect
            v-model="scope"
            class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
          >
            <option value="">All scopes</option>
            <option value="account">Account</option>
            <option value="cascade">Cascade</option>
            <option value="manual">Manual</option>
          </FormSelect>
        </div>
        <div class="card-surface overflow-hidden">
          <table class="w-full min-w-[640px] text-left">
            <thead
              class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
            >
              <tr>
                <th class="px-5 py-3.5">Category</th>
                <th class="px-5 py-3.5">Item</th>
                <th class="px-5 py-3.5">Scope</th>
                <th class="px-5 py-3.5 text-right">Quantity</th>
                <th class="w-12"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="!quantities.length">
                <td colspan="5" class="px-5 py-14 text-center text-slate-400">
                  No service quantities match these filters.
                </td>
              </tr>
              <tr
                v-for="row in quantities"
                v-else
                :key="row.id"
                class="cursor-pointer hover:bg-slate-50"
                @click="services.detailsOpen = true"
              >
                <td class="px-5 py-4 font-semibold text-slate-700">{{ row.category }}</td>
                <td class="px-5 py-4 text-slate-500">{{ row.item }}</td>
                <td class="px-5 py-4 capitalize text-slate-500">{{ row.scope }}</td>
                <td class="px-5 py-4 text-right font-semibold text-slate-700">
                  {{ amount(row.quantity) }}
                </td>
                <td><ChevronRightIcon class="size-4 text-slate-400" /></td>
              </tr>
            </tbody>
          </table></div></template
    ></template>
  </div>
  <ServiceDetailPanel
    v-if="services.detailsOpen && services.overview"
    :overview="services.overview"
    @close="services.detailsOpen = false"
  />
</template>
