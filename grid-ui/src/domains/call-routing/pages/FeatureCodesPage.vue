<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  BoltIcon,
  CheckCircleIcon,
  LockClosedIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import SearchInput from '@/shared/components/SearchInput.vue'
import { presentFeatureCode } from '../services/featureCodePresentation'
import { useFeatureCodeStore } from '../stores/featureCodeStore'

const accounts = useAccountStore()
const featureCodes = useFeatureCodeStore()
const search = ref('')

const presentedRecords = computed(() =>
  featureCodes.records.map((record) => ({
    record,
    presentation: presentFeatureCode(record),
  })),
)
const categories = computed(
  () => new Set(presentedRecords.value.map(({ presentation }) => presentation.category)).size,
)
const visibleRecords = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()

  if (!query) return presentedRecords.value

  return presentedRecords.value.filter(({ record, presentation }) =>
    [
      presentation.label,
      presentation.category,
      presentation.action,
      presentation.dialCode,
      presentation.dependency,
      record.root_module,
    ].some((value) => value?.toLocaleLowerCase().includes(query)),
  )
})
const freshnessLabel = computed(() =>
  featureCodes.lastSuccessfulAt
    ? `PBX projection synchronized ${new Date(featureCodes.lastSuccessfulAt).toLocaleString()}`
    : 'PBX projection not synchronized yet',
)

watch(
  () => accounts.selectedId,
  (accountId) => {
    search.value = ''
    featureCodes.reset()
    if (accountId) void featureCodes.load(accountId)
  },
  { immediate: true },
)
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Callflows</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">Feature Codes</h1>
        <p class="mt-1 text-xs text-slate-500">
          Active account star codes projected from Switch callflows.
        </p>
      </div>
      <button
        type="button"
        :disabled="!accounts.selectedId || featureCodes.loading"
        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm disabled:opacity-50 sm:ml-auto"
        @click="accounts.selectedId && featureCodes.load(accounts.selectedId)"
      >
        <ArrowPathIcon class="size-4" :class="featureCodes.loading && 'animate-spin'" />
        Refresh
      </button>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div
      class="mb-5 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900"
    >
      <LockClosedIcon class="mt-0.5 size-4 shrink-0" />
      <p>
        <strong>Read-only inventory.</strong> Enable, disable, and renumber operations remain
        capability-gated because they replace or delete whole live callflows. Use Callflows to
        inspect the projected route structure.
      </p>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-amber-50 text-amber-600">
          <BoltIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ featureCodes.total }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Projected active codes
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4">
        <span class="grid size-10 place-items-center rounded-md bg-blue-50 text-blue-600">
          <CheckCircleIcon class="size-5" />
        </span>
        <div>
          <p class="text-lg font-semibold text-slate-700">{{ categories }}</p>
          <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Runtime categories
          </p>
        </div>
      </article>
      <article class="card-surface flex items-center gap-4 p-4 sm:col-span-2 xl:col-span-1">
        <span class="grid size-10 place-items-center rounded-md bg-slate-100 text-slate-600">
          <LockClosedIcon class="size-5" />
        </span>
        <div>
          <p class="text-sm font-semibold text-slate-700">Mutations gated</p>
          <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
            Safe inventory only
          </p>
        </div>
      </article>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
      <SearchInput
        v-model="search"
        label="Search feature codes"
        placeholder="Search code, action, module, or dependency…"
        input-class="h-10 bg-white text-xs shadow-sm"
        class="w-full sm:max-w-md"
      />
      <span
        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-500 sm:ml-auto"
      >
        {{ freshnessLabel }}
      </span>
    </div>

    <div
      v-if="featureCodes.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-danger"
    >
      {{ featureCodes.error }}
    </div>

    <div class="card-surface overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[850px] text-left">
          <thead
            class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
          >
            <tr>
              <th class="px-5 py-3.5">Feature</th>
              <th class="px-5 py-3.5">Code</th>
              <th class="px-5 py-3.5">Category</th>
              <th class="px-5 py-3.5">Runtime action</th>
              <th class="px-5 py-3.5">Dependency summary</th>
              <th class="px-5 py-3.5">State</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr v-if="featureCodes.loading">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                Loading active feature codes…
              </td>
            </tr>
            <tr v-else-if="!accounts.selectedId">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                Select an account to inspect its feature codes.
              </td>
            </tr>
            <tr v-else-if="visibleRecords.length === 0">
              <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                <MagnifyingGlassIcon class="mx-auto mb-3 size-8" />
                No active feature codes match this search.
              </td>
            </tr>
            <tr v-for="item in visibleRecords" v-else :key="item.record.id">
              <td class="px-5 py-3.5">
                <p class="font-semibold text-slate-700">{{ item.presentation.label }}</p>
                <p class="mt-1 font-mono text-[10px] text-slate-400">
                  {{ item.record.root_module ?? 'Unknown module' }}
                </p>
              </td>
              <td class="px-5 py-3.5 font-mono text-sm font-semibold text-slate-700">
                {{ item.presentation.dialCode }}
              </td>
              <td class="px-5 py-3.5 text-slate-600">{{ item.presentation.category }}</td>
              <td class="px-5 py-3.5 text-slate-600">{{ item.presentation.action }}</td>
              <td class="max-w-sm px-5 py-3.5 leading-5 text-slate-500">
                {{ item.presentation.dependency }}
              </td>
              <td class="px-5 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700"
                >
                  <span class="size-1.5 rounded-full bg-emerald-500" /> Projected active
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
