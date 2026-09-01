<script setup lang="ts">
import { computed, watch } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ChevronRightIcon,
  HashtagIcon,
  LinkIcon,
  MapPinIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useGlobalSearchListQuery } from '@/domains/global-search/composables/useGlobalSearchListQuery'
import SearchInput from '@/shared/components/SearchInput.vue'
import PhoneNumberDetailPanel from '../components/PhoneNumberDetailPanel.vue'
import { usePhoneNumberStore } from '../stores/phoneNumberStore'

const accounts = useAccountStore()
const phoneNumbers = usePhoneNumberStore()
const globalSearchQuery = useGlobalSearchListQuery()
const inServiceOnPage = computed(
  () => phoneNumbers.records.filter((record) => record.state === 'in_service').length,
)
const assignedOnPage = computed(
  () => phoneNumbers.records.filter((record) => record.assigned_callflow !== null).length,
)
const e911OnPage = computed(
  () => phoneNumbers.records.filter((record) => record.e911.status === 'PROVISIONED').length,
)
const panelOpen = computed(
  () =>
    phoneNumbers.detailLoading || phoneNumbers.detail !== null || phoneNumbers.detailError !== null,
)
const freshnessLabel = computed(() => {
  if (!phoneNumbers.sync.last_successful_at) return 'Not synchronized yet'
  return `Last synchronized ${new Date(phoneNumbers.sync.last_successful_at).toLocaleString()}`
})

watch(
  [() => accounts.selectedId, globalSearchQuery],
  ([accountId, searchQuery]) => {
    phoneNumbers.reset()
    phoneNumbers.filters.search = searchQuery
    if (accountId) void phoneNumbers.load(accountId, 1)
  },
  { immediate: true },
)

function search(): void {
  if (accounts.selectedId) void phoneNumbers.load(accounts.selectedId, 1)
}

function synchronize(): void {
  if (accounts.selectedId) void phoneNumbers.synchronize(accounts.selectedId)
}

function openDetail(id: string): void {
  if (accounts.selectedId) void phoneNumbers.loadDetail(accounts.selectedId, id)
}

function humanize(value: string | null): string {
  return value
    ? value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
    : 'Unknown'
}
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Phone Numbers</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Phone Numbers</h1>
        <p class="mt-1 text-xs text-slate-500">
          Carrier inventory, feature state, and incoming callflow assignments projected from Switch.
        </p>
      </div>
      <button
        type="button"
        :disabled="!accounts.selectedId || phoneNumbers.synchronizing"
        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50 sm:ml-auto"
        @click="synchronize"
      >
        <ArrowPathIcon class="size-4" :class="phoneNumbers.synchronizing && 'animate-spin'" />{{
          phoneNumbers.synchronizing ? 'Synchronizing…' : 'Synchronize numbers'
        }}
      </button>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><HashtagIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ phoneNumbers.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected numbers
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-emerald-50 text-emerald-600"
          ><CheckCircleIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ inServiceOnPage }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            In service on page
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-blue-50 text-blue-600"
          ><LinkIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ assignedOnPage }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Assigned on page
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-amber-50 text-amber-600"
          ><MapPinIcon class="size-5"
        /></span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ e911OnPage }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            E911 provisioned
          </p>
        </div>
      </article>
    </div>

    <form
      class="mb-4 grid gap-3 lg:grid-cols-[minmax(240px,1fr)_180px_180px_auto]"
      @submit.prevent="search"
    >
      <SearchInput
        v-model="phoneNumbers.filters.search"
        label="Search phone numbers"
        placeholder="Search number, carrier, CNAM, route…"
        input-class="h-10 bg-white text-xs shadow-sm"
        live
        @search="search"
      />
      <FormSelect
        v-model="phoneNumbers.filters.state"
        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 shadow-sm outline-none"
      >
        <option value="">All states</option>
        <option value="in_service">In service</option>
        <option value="reserved">Reserved</option>
        <option value="port_in">Port in</option>
        <option value="aging">Aging</option>
      </FormSelect>
      <FormSelect
        v-model="phoneNumbers.filters.assignment"
        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-600 shadow-sm outline-none"
      >
        <option value="">All assignments</option>
        <option value="assigned">Assigned</option>
        <option value="unassigned">Unassigned</option>
      </FormSelect>
      <button
        class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50"
      >
        Apply filters
      </button>
    </form>

    <div
      v-if="phoneNumbers.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
    >
      {{ phoneNumbers.error }}
    </div>
    <div class="mb-4 flex justify-end">
      <span
        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-500"
        >{{ freshnessLabel }}</span
      >
    </div>

    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left">
          <thead
            class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
          >
            <tr>
              <th class="px-5 py-3.5">Number</th>
              <th class="px-5 py-3.5">State</th>
              <th class="px-5 py-3.5">Incoming route</th>
              <th class="px-5 py-3.5">Carrier</th>
              <th class="px-5 py-3.5">Features</th>
              <th class="w-12 px-5 py-3.5"><span class="sr-only">View</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="phoneNumbers.loading">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                Loading projected phone numbers…
              </td>
            </tr>
            <tr v-else-if="phoneNumbers.records.length === 0">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                <HashtagIcon class="mx-auto mb-3 size-8 text-slate-400" />No phone numbers are
                projected for this account.<br /><span class="mt-1 inline-block text-[11px]"
                  >Synchronize to confirm the Switch inventory.</span
                >
              </td>
            </tr>
            <tr
              v-for="record in phoneNumbers.records"
              v-else
              :key="record.id"
              class="cursor-pointer hover:bg-slate-50/60"
              @click="openDetail(record.id)"
            >
              <td class="px-5 py-3.5 font-mono font-semibold text-slate-700">
                {{ record.number }}
              </td>
              <td class="px-5 py-3.5">
                <span
                  class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700"
                  >{{ humanize(record.state) }}</span
                >
              </td>
              <td class="px-5 py-3.5">
                <span v-if="record.assigned_callflow" class="font-semibold text-brand-600">{{
                  record.assigned_callflow.name ?? 'Unnamed callflow'
                }}</span
                ><span v-else class="text-slate-400">Unassigned</span>
              </td>
              <td class="px-5 py-3.5 text-slate-500">{{ record.carrier_name ?? '—' }}</td>
              <td class="px-5 py-3.5">
                <div class="flex max-w-sm flex-wrap gap-1">
                  <span
                    v-for="feature in record.features.slice(0, 3)"
                    :key="feature"
                    class="rounded bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-500"
                    >{{ humanize(feature) }}</span
                  ><span
                    v-if="record.features.length > 3"
                    class="px-1 py-1 text-[9px] text-slate-400"
                    >+{{ record.features.length - 3 }}</span
                  >
                </div>
              </td>
              <td class="px-5 py-3.5">
                <button
                  type="button"
                  :aria-label="`View ${record.number}`"
                  class="grid size-8 place-items-center rounded text-slate-400 hover:bg-brand-50 hover:text-brand-600"
                  @click.stop="openDetail(record.id)"
                >
                  <ChevronRightIcon class="size-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="phoneNumbers.lastPage > 1"
      class="mt-4 flex items-center justify-between text-xs text-slate-500"
    >
      <button
        :disabled="phoneNumbers.page <= 1"
        class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40"
        @click="
          accounts.selectedId && phoneNumbers.load(accounts.selectedId, phoneNumbers.page - 1)
        "
      >
        Previous</button
      ><span>Page {{ phoneNumbers.page }} of {{ phoneNumbers.lastPage }}</span
      ><button
        :disabled="phoneNumbers.page >= phoneNumbers.lastPage"
        class="rounded-md border border-slate-200 bg-white px-4 py-2 disabled:opacity-40"
        @click="
          accounts.selectedId && phoneNumbers.load(accounts.selectedId, phoneNumbers.page + 1)
        "
      >
        Next
      </button>
    </div>
  </div>

  <PhoneNumberDetailPanel
    v-if="panelOpen"
    :record="phoneNumbers.detail"
    :loading="phoneNumbers.detailLoading"
    :error="phoneNumbers.detailError"
    @close="phoneNumbers.closeDetail"
  />
</template>
