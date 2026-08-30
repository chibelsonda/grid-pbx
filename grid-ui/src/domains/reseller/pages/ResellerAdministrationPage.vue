<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowPathIcon,
  BuildingOffice2Icon,
  CheckBadgeIcon,
  CircleStackIcon,
  ExclamationTriangleIcon,
  ShieldCheckIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import DescendantOnboardingPanel from '../components/DescendantOnboardingPanel.vue'
import { useResellerStore } from '../stores/resellerStore'
import type { DescendantOnboardingInput } from '../types/reseller'

const accounts = useAccountStore()
const reseller = useResellerStore()
const onboardingOpen = ref(false)

const canView = computed(() => accounts.selected?.permissions.can_view_services ?? false)
const canOnboard = computed(() => accounts.selected?.permissions.can_onboard_descendants ?? false)
const hierarchy = computed(() => reseller.hierarchy)
const status = computed(() => reseller.status)
const unresolvedCount = computed(() => hierarchy.value?.coverage.unresolved_descendants_count ?? 0)
const coveragePercent = computed(() => {
  const coverage = hierarchy.value?.coverage

  if (!coverage || coverage.switch_descendants_count === 0) return 100

  return Math.min(
    100,
    Math.round((coverage.projected_descendants_count / coverage.switch_descendants_count) * 100),
  )
})

const humanize = (value: string | null): string => {
  if (!value) return 'Not reported'

  return value
    .split('_')
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join(' ')
}

const dateTime = (value: string | null): string => {
  if (!value) return 'Not synchronized'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function openOnboarding(): Promise<void> {
  if (!accounts.selectedId) return
  onboardingOpen.value = true
  await reseller.loadOnboardingCandidates(accounts.selectedId)
}

async function onboardDescendant(input: DescendantOnboardingInput): Promise<void> {
  const accountId = accounts.selectedId
  if (!accountId) return
  if (!(await reseller.onboardDescendant(accountId, input))) return

  onboardingOpen.value = false
  await Promise.all([accounts.load(), reseller.load(accountId)])
}

watch(
  [() => accounts.selectedId, canView],
  ([accountId, allowed]) => {
    reseller.reset()
    if (accountId && allowed) void reseller.load(accountId)
  },
  { immediate: true },
)
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex flex-col gap-4 sm:flex-row sm:items-center">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-500">GridPBX / Workspace</p>
        <h1 class="text-xl font-semibold text-slate-800">Reseller administration</h1>
        <p class="mt-1 text-xs text-slate-600">
          Read-only Switch hierarchy, billing ownership, and projection coverage.
        </p>
      </div>
      <button
        v-if="canView"
        type="button"
        :disabled="reseller.loading || !accounts.selectedId"
        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50 sm:ml-auto"
        @click="accounts.selectedId && reseller.load(accounts.selectedId)"
      >
        <ArrowPathIcon class="size-4" :class="reseller.loading && 'animate-spin'" />
        Refresh view
      </button>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div
      v-if="!canView"
      class="rounded-md border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"
    >
      Reseller hierarchy and billing ownership are available only to account, reseller, or platform
      administrators.
    </div>

    <template v-else>
      <div
        v-if="reseller.error"
        class="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-700"
      >
        {{ reseller.error }}
      </div>

      <div v-if="reseller.loading" class="card-surface p-14 text-center text-xs text-slate-500">
        Loading reseller administration…
      </div>

      <div
        v-else-if="!hierarchy || !status"
        class="card-surface grid min-h-64 place-items-center p-8 text-center"
      >
        <div>
          <BuildingOffice2Icon class="mx-auto size-9 text-slate-400" />
          <p class="mt-3 text-sm font-semibold text-slate-700">No reseller projection available</p>
          <p class="mt-1 text-xs text-slate-500">
            Refresh the selected account projection before reviewing its hierarchy.
          </p>
        </div>
      </div>

      <template v-else>
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article class="card-surface flex items-center gap-4 p-4">
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600"
            >
              <BuildingOffice2Icon class="size-5" />
            </span>
            <div class="min-w-0">
              <p class="text-xs leading-4 font-semibold text-slate-800">
                {{ status.account.is_reseller ? 'Reseller' : 'Customer account' }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Switch account role
              </p>
            </div>
          </article>

          <article class="card-surface flex items-center gap-4 p-4">
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-violet-50 text-violet-600"
            >
              <ShieldCheckIcon class="size-5" />
            </span>
            <div class="min-w-0">
              <p class="text-xs leading-4 font-semibold text-slate-800">
                {{
                  status.account.is_superduper_admin ? 'Super administrator' : 'Standard account'
                }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Platform authority
              </p>
            </div>
          </article>

          <article class="card-surface flex items-center gap-4 p-4">
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md bg-sky-50 text-sky-600"
            >
              <CircleStackIcon class="size-5" />
            </span>
            <div class="min-w-0">
              <p class="text-xs leading-4 font-semibold text-slate-800">
                {{ humanize(status.account.billing_mode) }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Billing mode
              </p>
            </div>
          </article>

          <article class="card-surface flex items-center gap-4 p-4">
            <span
              class="grid size-10 shrink-0 place-items-center rounded-md"
              :class="
                unresolvedCount === 0
                  ? 'bg-emerald-50 text-emerald-600'
                  : 'bg-amber-50 text-amber-700'
              "
            >
              <CheckBadgeIcon v-if="unresolvedCount === 0" class="size-5" />
              <ExclamationTriangleIcon v-else class="size-5" />
            </span>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-800">
                {{ hierarchy.coverage.projected_descendants_count }} /
                {{ hierarchy.coverage.switch_descendants_count }}
              </p>
              <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Descendants projected
              </p>
            </div>
          </article>
        </div>

        <div
          v-if="unresolvedCount > 0"
          class="mb-5 flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900"
          data-testid="reseller-coverage-warning"
        >
          <ExclamationTriangleIcon class="mt-0.5 size-5 shrink-0" />
          <div>
            <p class="text-sm font-semibold">Incomplete hierarchy projection</p>
            <p class="mt-1 text-xs leading-5 text-amber-800">
              {{ unresolvedCount }} Switch
              {{ unresolvedCount === 1 ? 'descendant is' : 'descendants are' }} not mapped to this
              GridPBX organization. Unmapped accounts remain read-only and cannot be selected as
              management targets.
            </p>
          </div>
          <button
            v-if="canOnboard"
            type="button"
            class="ml-auto shrink-0 rounded-md border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-900 shadow-sm hover:bg-amber-100"
            @click="openOnboarding"
          >
            Review descendants
          </button>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
          <section class="card-surface overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-semibold text-slate-800">Account hierarchy</h2>
              <p class="mt-1 text-xs text-slate-600">
                Only accounts projected into the current GridPBX organization are shown.
              </p>
            </div>

            <div class="p-5">
              <p class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                Hierarchy path
              </p>
              <div class="mt-3 flex flex-wrap items-center gap-2">
                <template v-for="ancestor in hierarchy.ancestors" :key="ancestor.id">
                  <span
                    class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700"
                  >
                    {{ ancestor.name }}
                  </span>
                  <span class="text-slate-400">/</span>
                </template>
                <span
                  class="rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700"
                >
                  {{ hierarchy.account.name }}
                </span>
              </div>

              <div class="mt-6">
                <div class="flex items-center justify-between gap-4">
                  <p class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                    Projection coverage
                  </p>
                  <span class="text-xs font-semibold text-slate-700">{{ coveragePercent }}%</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                  <div
                    class="h-full rounded-full transition-[width]"
                    :class="unresolvedCount === 0 ? 'bg-emerald-500' : 'bg-amber-500'"
                    :style="{ width: `${coveragePercent}%` }"
                  ></div>
                </div>
                <p class="mt-2 text-xs text-slate-600">
                  Hierarchy synchronized {{ dateTime(hierarchy.projection.last_synced_at) }}.
                </p>
              </div>

              <div class="mt-6 border-t border-slate-200 pt-5">
                <div class="flex items-center gap-2">
                  <UserGroupIcon class="size-4 text-slate-500" />
                  <h3 class="text-xs font-semibold text-slate-800">Direct child accounts</h3>
                  <span
                    class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                  >
                    {{ hierarchy.children.length }}
                  </span>
                </div>
                <div v-if="hierarchy.children.length" class="mt-3 grid gap-2 sm:grid-cols-2">
                  <article
                    v-for="child in hierarchy.children"
                    :key="child.id"
                    class="rounded-md border border-slate-200 bg-slate-50/70 p-3"
                  >
                    <div class="flex items-center gap-2">
                      <span
                        class="size-2 rounded-full"
                        :class="child.enabled ? 'bg-emerald-500' : 'bg-slate-400'"
                      ></span>
                      <p class="truncate text-xs font-semibold text-slate-800">{{ child.name }}</p>
                    </div>
                    <p class="mt-1 truncate text-[11px] text-slate-600">
                      {{ child.realm || 'No realm reported' }}
                    </p>
                  </article>
                </div>
                <p
                  v-else
                  class="mt-3 rounded-md bg-slate-50 p-4 text-center text-xs text-slate-600"
                >
                  No direct child account is projected for this organization.
                </p>
              </div>
            </div>
          </section>

          <div class="space-y-5">
            <section class="card-surface overflow-hidden">
              <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-800">Billing ownership</h2>
                <p class="mt-1 text-xs text-slate-600">
                  Resolved from the Switch service projection.
                </p>
              </div>
              <div class="p-5">
                <template v-if="status.billing_reseller">
                  <div class="flex items-start gap-3">
                    <span
                      class="grid size-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-600"
                    >
                      <BuildingOffice2Icon class="size-5" />
                    </span>
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold text-slate-800">
                        {{ status.billing_reseller.name }}
                      </p>
                      <p class="mt-1 truncate text-xs text-slate-600">
                        {{ status.billing_reseller.realm || 'No realm reported' }}
                      </p>
                    </div>
                  </div>
                  <span
                    class="mt-4 inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold"
                    :class="
                      status.billing_reseller_projected
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-800'
                    "
                  >
                    {{
                      status.billing_reseller_projected ? 'Projected in GridPBX' : 'Not projected'
                    }}
                  </span>
                </template>
                <p v-else class="text-xs leading-5 text-slate-600">
                  Switch has not reported a billing reseller for this account.
                </p>
                <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-600">
                  Service projection synchronized
                  {{ dateTime(status.service_projection_last_synced_at) }}.
                </p>
              </div>
            </section>

            <section class="rounded-md border border-sky-200 bg-sky-50 p-5">
              <div class="flex gap-3">
                <ShieldCheckIcon class="mt-0.5 size-5 shrink-0 text-sky-700" />
                <div>
                  <h2 class="text-sm font-semibold text-sky-900">
                    Protected administration boundary
                  </h2>
                  <p class="mt-1 text-xs leading-5 text-sky-800">
                    Reseller promotion and demotion are intentionally unavailable. These operations
                    require platform policy, dependency preflight, explicit confirmation, and an
                    auditable recovery plan before GridPBX can expose them.
                  </p>
                </div>
              </div>
            </section>
          </div>
        </div>
      </template>
    </template>
  </div>

  <DescendantOnboardingPanel
    v-if="onboardingOpen"
    :data="reseller.onboardingCandidates"
    :loading="reseller.candidatesLoading"
    :saving="reseller.onboarding"
    :error="reseller.onboardingError"
    :field-errors="reseller.fieldErrors"
    @close="onboardingOpen = false"
    @retry="accounts.selectedId && reseller.loadOnboardingCandidates(accounts.selectedId)"
    @save="onboardDescendant"
  />
</template>
