<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowPathIcon,
  BuildingOffice2Icon,
  CheckBadgeIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  CircleStackIcon,
  ExclamationTriangleIcon,
  NoSymbolIcon,
  ShieldCheckIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import AppAlert from '@/shared/components/AppAlert.vue'
import CircularCountBadge from '@/shared/components/CircularCountBadge.vue'
import SearchInput from '@/shared/components/SearchInput.vue'
import ProjectionFreshness from '@/shared/components/ProjectionFreshness.vue'
import ProjectionSyncButton from '@/shared/components/ProjectionSyncButton.vue'
import DescendantOnboardingPanel from '../components/DescendantOnboardingPanel.vue'
import ResellerDiagnosticDetails from '../components/ResellerDiagnosticDetails.vue'
import { useResellerStore } from '../stores/resellerStore'
import type { AccountHierarchy, DescendantOnboardingInput, ResellerStatus } from '../types/reseller'

type PortfolioQuantity = AccountHierarchy['portfolio']['quantities'][number]
type QuantityGroup = {
  key: string
  scope: PortfolioQuantity['scope']
  category: string
  total: number
  items: PortfolioQuantity[]
}
type AdministrationCapability = keyof ResellerStatus['administration']

const administrationOperations: Array<{
  capability: AdministrationCapability
  label: string
}> = [
  { capability: 'account_creation_available', label: 'Account creation' },
  { capability: 'account_move_available', label: 'Account move' },
  { capability: 'account_deletion_available', label: 'Account deletion' },
  { capability: 'limit_mutations_available', label: 'Limits changes' },
  { capability: 'service_plan_mutations_available', label: 'Service plan changes' },
  {
    capability: 'service_override_mutations_available',
    label: 'Service overrides and manual quantities',
  },
  { capability: 'top_up_available', label: 'Account top-up' },
  {
    capability: 'switch_service_synchronization_available',
    label: 'Switch service synchronization',
  },
  {
    capability: 'switch_service_reconciliation_available',
    label: 'Switch service reconciliation',
  },
]

const accounts = useAccountStore()
const reseller = useResellerStore()
const route = useRoute()
const router = useRouter()
const onboardingOpen = ref(false)
const quantityFilter = ref('')
const activeAdministrationIndex = ref(0)

const administrationSections = [
  { id: 'reseller-overview', label: 'Overview', icon: CheckBadgeIcon },
  { id: 'account-hierarchy', label: 'Account hierarchy', icon: UserGroupIcon },
  { id: 'services-billing', label: 'Services & billing', icon: CircleStackIcon },
  { id: 'administration-safeguards', label: 'Administration', icon: ShieldCheckIcon },
] as const

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
const quantityGroups = computed<QuantityGroup[]>(() => {
  const groups = new Map<string, QuantityGroup>()
  const search = quantityFilter.value.trim().toLocaleLowerCase()

  for (const quantity of hierarchy.value?.portfolio.quantities ?? []) {
    const searchable = `${quantity.scope} ${quantity.category} ${quantity.item}`.toLocaleLowerCase()
    if (search && !searchable.includes(search)) continue

    const key = `${quantity.scope}:${quantity.category}`
    const group = groups.get(key) ?? {
      key,
      scope: quantity.scope,
      category: quantity.category,
      total: 0,
      items: [],
    }
    group.total += quantity.quantity
    group.items.push(quantity)
    groups.set(key, group)
  }

  return [...groups.values()]
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

const decimal = (value: number): string =>
  new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)

const formatQuantity = (value: number): string =>
  new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 4,
  }).format(value)

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

async function synchronizeDescendant(descendantAccountId: string): Promise<void> {
  if (!accounts.selectedId) return

  await reseller.synchronizeDescendant(accounts.selectedId, descendantAccountId)
}

const projectionStatusClass = (status: 'healthy' | 'syncing' | 'stale' | 'error'): string =>
  ({
    healthy: 'bg-emerald-50 text-emerald-700',
    syncing: 'bg-sky-50 text-sky-700',
    stale: 'bg-amber-50 text-amber-800',
    error: 'bg-red-50 text-red-700',
  })[status]

watch(
  () => route.hash,
  (hash) => {
    const sectionIndex = administrationSections.findIndex(({ id }) => `#${id}` === hash)
    if (sectionIndex >= 0) activeAdministrationIndex.value = sectionIndex
  },
  { immediate: true },
)

function selectAdministrationSection(index: number): void {
  activeAdministrationIndex.value = index
  const section = administrationSections[index] ?? administrationSections[0]
  void router.replace({ hash: `#${section.id}` })
}

watch(
  [() => accounts.selectedId, canView],
  ([accountId, allowed]) => {
    reseller.reset()
    quantityFilter.value = ''
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
        <p class="mt-1 text-xs text-heading-description">
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
      <AppAlert
        v-if="reseller.error"
        :message="reseller.error"
        tone="error"
        class="mb-4"
        @dismiss="reseller.error = null"
      />

      <AppAlert
        v-if="reseller.onboardingNotice && reseller.onboardingNoticeTone === 'warning'"
        title="Follow-up required"
        :message="reseller.onboardingNotice"
        tone="warning"
        class="mb-4"
        data-testid="reseller-onboarding-notice"
        @dismiss="reseller.onboardingNotice = null"
      />

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

      <div v-else class="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start">
        <nav
          aria-label="Reseller administration sections"
          class="card-surface overflow-hidden lg:sticky lg:top-20"
        >
          <div class="border-b border-slate-200 px-4 py-3">
            <p class="text-xs font-semibold text-slate-700">Administration sections</p>
            <p class="mt-0.5 text-[10px] text-slate-500">Choose a category.</p>
          </div>
          <div class="flex gap-1 overflow-x-auto p-3 lg:flex-col">
            <button
              v-for="(section, index) in administrationSections"
              :key="section.id"
              type="button"
              class="flex h-10 shrink-0 items-center gap-3 rounded-md px-3 text-left text-[12px] font-semibold transition-colors lg:w-full"
              :class="
                index === activeAdministrationIndex
                  ? 'bg-brand-50 text-brand-700'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800'
              "
              :aria-current="index === activeAdministrationIndex ? 'page' : undefined"
              @click="selectAdministrationSection(index)"
            >
              <component :is="section.icon" class="size-[18px] shrink-0" />
              <span>{{ section.label }}</span>
            </button>
          </div>
        </nav>

        <div class="grid min-w-0 gap-5">
          <div
            v-if="activeAdministrationIndex === 0"
            id="reseller-overview"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
          >
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
            v-if="activeAdministrationIndex === 0 && unresolvedCount > 0"
            class="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900"
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

          <section
            v-if="activeAdministrationIndex === 2"
            id="services-billing"
            class="card-surface overflow-hidden"
            data-testid="hierarchy-service-totals"
          >
            <div class="border-b border-slate-200 px-5 py-5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 class="text-sm font-semibold text-slate-800">Hierarchy service totals</h2>
                  <p class="mt-0.5 text-[10px] leading-4 text-heading-description">
                    Aggregated only from the selected account and its managed descendants. Quantity
                    scopes remain separate to preserve their Switch billing meaning.
                  </p>
                </div>
                <span
                  class="rounded-full px-2.5 py-1 text-[10px] font-semibold"
                  :class="
                    hierarchy.portfolio.accounts.attention === 0
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-amber-50 text-amber-800'
                  "
                >
                  {{ hierarchy.portfolio.accounts.attention }} requiring attention
                </span>
              </div>
            </div>

            <div class="p-5">
              <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-md border border-slate-200 bg-slate-50/70 p-4">
                  <p class="text-lg font-semibold text-slate-800">
                    {{ hierarchy.portfolio.accounts.total }}
                  </p>
                  <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    Managed accounts
                  </p>
                  <p class="mt-2 text-xs text-slate-600">
                    {{ hierarchy.portfolio.accounts.projected }} projected ·
                    {{ hierarchy.portfolio.accounts.healthy }} healthy
                  </p>
                </article>
                <article class="rounded-md border border-slate-200 bg-slate-50/70 p-4">
                  <p class="text-lg font-semibold text-slate-800">
                    {{ hierarchy.portfolio.billing_ownership.projected }}
                  </p>
                  <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    Billing owners resolved
                  </p>
                  <p class="mt-2 text-xs text-slate-600">
                    {{ hierarchy.portfolio.billing_ownership.unresolved }} unresolved
                  </p>
                </article>
                <article class="rounded-md border border-slate-200 bg-slate-50/70 p-4">
                  <p class="text-lg font-semibold text-slate-800">
                    {{ decimal(hierarchy.portfolio.billing.recurring_amount) }}
                  </p>
                  <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    Recurring amount
                  </p>
                  <p class="mt-2 text-xs text-slate-600">Currency follows Switch billing.</p>
                </article>
                <article class="rounded-md border border-slate-200 bg-slate-50/70 p-4">
                  <p class="text-lg font-semibold text-slate-800">
                    {{ decimal(hierarchy.portfolio.billing.due_today) }}
                  </p>
                  <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    Due today
                  </p>
                  <p class="mt-2 text-xs text-slate-600">Projected hierarchy total</p>
                </article>
              </div>

              <div v-if="hierarchy.portfolio.warnings.length" class="mt-4 grid gap-2">
                <Disclosure
                  v-for="warning in hierarchy.portfolio.warnings"
                  :key="warning.code"
                  v-slot="{ open }"
                  as="div"
                  class="overflow-hidden rounded-md border border-amber-200 bg-amber-50 text-amber-900"
                >
                  <DisclosureButton
                    class="flex w-full items-start gap-3 p-3 text-left hover:bg-amber-100/70"
                  >
                    <ExclamationTriangleIcon class="mt-0.5 size-4 shrink-0" />
                    <p class="min-w-0 flex-1 text-xs leading-5">
                      <strong>{{ warning.count }}</strong> {{ warning.message }}
                    </p>
                    <ChevronDownIcon
                      class="mt-0.5 size-4 shrink-0 transition-transform"
                      :class="open && 'rotate-180'"
                    />
                  </DisclosureButton>
                  <DisclosurePanel class="border-t border-amber-200">
                    <ResellerDiagnosticDetails
                      :guidance="warning.guidance"
                      :accounts="warning.affected_accounts"
                    />
                  </DisclosurePanel>
                </Disclosure>
              </div>

              <div class="mt-5 border-t border-slate-200 pt-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 class="text-xs font-semibold text-slate-800">Projected quantities</h3>
                    <p class="mt-1 text-[11px] text-heading-description">
                      Grouped by Switch scope and service category.
                    </p>
                  </div>
                  <SearchInput
                    v-if="hierarchy.portfolio.quantities.length"
                    v-model="quantityFilter"
                    label="Filter projected quantities"
                    placeholder="Filter quantities…"
                    class="w-full sm:max-w-72"
                    input-class="h-9 bg-white text-xs"
                  />
                </div>
                <div
                  v-if="quantityGroups.length"
                  class="mt-3 overflow-hidden rounded-md border border-slate-200"
                >
                  <Disclosure
                    v-for="group in quantityGroups"
                    :key="group.key"
                    v-slot="{ open }"
                    as="div"
                    class="border-b border-slate-200 last:border-b-0"
                  >
                    <DisclosureButton
                      data-testid="service-quantity-group"
                      class="grid w-full grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 bg-white px-4 py-3 text-left hover:bg-slate-50"
                    >
                      <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-slate-800">
                          {{ humanize(group.category) }}
                        </p>
                        <p class="mt-0.5 text-[10px] text-slate-600">
                          {{ humanize(group.scope) }} · {{ group.items.length }}
                          {{ group.items.length === 1 ? 'item' : 'items' }}
                        </p>
                      </div>
                      <span
                        class="text-xs font-semibold text-slate-800"
                        data-testid="service-quantity-total"
                      >
                        {{ formatQuantity(group.total) }}
                      </span>
                      <ChevronDownIcon
                        class="size-4 text-slate-500 transition-transform"
                        :class="open && 'rotate-180'"
                      />
                    </DisclosureButton>
                    <DisclosurePanel class="divide-y divide-slate-200 border-t border-slate-200">
                      <div
                        v-for="quantity in group.items"
                        :key="quantity.item"
                        data-testid="service-quantity-item"
                        class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 bg-slate-50/60 px-4 py-2.5"
                      >
                        <p class="truncate pl-3 text-xs text-slate-700">
                          {{ humanize(quantity.item) }}
                        </p>
                        <span class="text-[11px] font-semibold text-slate-700">
                          {{ formatQuantity(quantity.quantity) }}
                        </span>
                      </div>
                    </DisclosurePanel>
                  </Disclosure>
                </div>
                <p
                  v-else-if="hierarchy.portfolio.quantities.length"
                  class="mt-3 rounded-md bg-slate-50 p-4 text-xs text-slate-600"
                >
                  No projected quantities match “{{ quantityFilter }}”.
                </p>
                <p v-else class="mt-3 rounded-md bg-slate-50 p-4 text-xs text-slate-600">
                  Switch has not projected service quantities for this managed hierarchy.
                </p>
              </div>
            </div>
          </section>

          <div class="contents">
            <section
              v-if="activeAdministrationIndex === 1"
              id="account-hierarchy"
              class="card-surface overflow-hidden"
            >
              <div class="border-b border-slate-200 px-5 py-5">
                <h2 class="text-sm font-semibold text-slate-800">Account hierarchy</h2>
                <p class="mt-0.5 text-[10px] leading-4 text-heading-description">
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
                    <CircularCountBadge
                      class="ml-auto border-slate-200 bg-slate-100 text-slate-600"
                      :count="hierarchy.children.length"
                      :label="`${hierarchy.children.length} direct child accounts`"
                    />
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
                        <p class="truncate text-xs font-semibold text-slate-800">
                          {{ child.name }}
                        </p>
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

            <div class="contents">
              <section v-if="activeAdministrationIndex === 2" class="card-surface overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-5">
                  <h2 class="text-sm font-semibold text-slate-800">Billing ownership</h2>
                  <p class="mt-0.5 text-[10px] leading-4 text-heading-description">
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

              <section
                v-if="activeAdministrationIndex === 3"
                id="administration-safeguards"
                class="card-surface overflow-hidden"
                data-testid="account-administration-capabilities"
              >
                <div class="border-b border-slate-200 px-5 py-5">
                  <div class="flex items-start gap-3">
                    <NoSymbolIcon class="mt-0.5 size-5 shrink-0 text-slate-500" />
                    <div>
                      <h2 class="text-sm font-semibold text-slate-800">
                        Lifecycle and billing operations
                      </h2>
                      <p class="mt-0.5 text-[10px] leading-4 text-heading-description">
                        Switch account and billing mutations remain unavailable until their
                        security, confirmation, audit, and recovery contracts are complete.
                      </p>
                    </div>
                  </div>
                </div>
                <div class="divide-y divide-slate-200">
                  <div
                    v-for="operation in administrationOperations"
                    :key="operation.capability"
                    class="flex items-center justify-between gap-4 px-5 py-2.5"
                  >
                    <span class="text-xs font-medium text-slate-700">{{ operation.label }}</span>
                    <span class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                      {{
                        status.administration[operation.capability] ? 'Available' : 'Unavailable'
                      }}
                    </span>
                  </div>
                </div>
                <p
                  class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-[11px] leading-5 text-slate-600"
                >
                  GridPBX projection refresh is read-only and is separate from Switch billing-side
                  synchronization or reconciliation.
                </p>
              </section>

              <section
                v-if="activeAdministrationIndex === 3"
                class="overflow-hidden rounded-md border border-sky-200 bg-sky-50"
                data-testid="reseller-mutation-preflight"
              >
                <div class="border-b border-sky-200 p-5">
                  <div class="flex gap-3">
                    <ShieldCheckIcon class="mt-0.5 size-5 shrink-0 text-sky-700" />
                    <div>
                      <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-semibold text-sky-900">
                          Protected administration boundary
                        </h2>
                        <span
                          class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                          :class="
                            hierarchy.mutation_preflight.operationally_ready
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-amber-100 text-amber-900'
                          "
                        >
                          {{
                            hierarchy.mutation_preflight.operationally_ready
                              ? 'Dependencies ready'
                              : 'Dependencies blocked'
                          }}
                        </span>
                      </div>
                      <p class="mt-0.5 text-[10px] leading-4 text-sky-800">
                        Read-only {{ hierarchy.mutation_preflight.operation }} preflight. The
                        mutation remains disabled until platform policy, confirmation, auditing, and
                        recovery are defined.
                      </p>
                    </div>
                  </div>
                </div>
                <div class="divide-y divide-sky-200/80">
                  <template v-for="check in hierarchy.mutation_preflight.checks" :key="check.code">
                    <div v-if="check.passed" class="flex items-start gap-3 px-5 py-3">
                      <CheckCircleIcon class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                      <div class="min-w-0">
                        <p class="text-xs font-semibold text-sky-950">{{ humanize(check.code) }}</p>
                        <p class="mt-0.5 text-[11px] leading-4 text-sky-800">
                          {{ check.message }}
                        </p>
                      </div>
                    </div>
                    <Disclosure v-else v-slot="{ open }" as="div">
                      <DisclosureButton
                        class="flex w-full items-start gap-3 px-5 py-3 text-left hover:bg-sky-100/70"
                      >
                        <ExclamationTriangleIcon class="mt-0.5 size-4 shrink-0 text-amber-700" />
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-semibold text-sky-950">
                            {{ humanize(check.code) }}
                          </p>
                          <p class="mt-0.5 text-[11px] leading-4 text-sky-800">
                            {{ check.message }}
                            <span v-if="check.count > 0"> ({{ check.count }} blocking) </span>
                          </p>
                        </div>
                        <ChevronDownIcon
                          class="mt-0.5 size-4 shrink-0 text-sky-700 transition-transform"
                          :class="open && 'rotate-180'"
                        />
                      </DisclosureButton>
                      <DisclosurePanel class="border-t border-sky-200/80">
                        <ResellerDiagnosticDetails
                          :guidance="check.guidance"
                          :accounts="check.affected_accounts"
                        />
                      </DisclosurePanel>
                    </Disclosure>
                  </template>
                </div>
              </section>
            </div>
          </div>

          <section
            v-if="activeAdministrationIndex === 2 && hierarchy.descendants.length"
            class="card-surface overflow-hidden"
          >
            <div class="border-b border-slate-200 px-5 py-5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 class="text-sm font-semibold text-slate-800">Descendant service ownership</h2>
                  <p class="mt-0.5 text-[10px] leading-4 text-heading-description">
                    Billing reseller resolution and service-projection health for every managed
                    descendant.
                  </p>
                </div>
                <span
                  class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-600"
                >
                  {{ hierarchy.descendants.length }} managed
                </span>
              </div>
            </div>

            <AppAlert
              v-if="reseller.descendantSyncError"
              :message="reseller.descendantSyncError"
              tone="error"
              class="m-5"
              @dismiss="reseller.descendantSyncError = null"
            />

            <div class="divide-y divide-slate-200">
              <article
                v-for="descendant in hierarchy.descendants"
                :key="descendant.id"
                class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(180px,1.25fr)_minmax(160px,1fr)_minmax(120px,0.65fr)_minmax(170px,0.9fr)_auto] md:items-center"
              >
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span
                      class="size-2 shrink-0 rounded-full"
                      :class="descendant.enabled ? 'bg-emerald-500' : 'bg-slate-400'"
                    ></span>
                    <p class="truncate text-xs font-semibold text-slate-800">
                      {{ descendant.name }}
                    </p>
                  </div>
                  <p class="mt-1 truncate pl-4 text-[11px] text-slate-600">
                    {{ descendant.realm || 'No realm reported' }}
                  </p>
                </div>

                <div class="min-w-0">
                  <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    Billing owner
                  </p>
                  <p class="mt-1 truncate text-xs font-medium text-slate-700">
                    {{ descendant.service_projection.billing_reseller?.name || 'Not reported' }}
                  </p>
                  <p
                    v-if="descendant.service_projection.billing_reseller"
                    class="mt-0.5 text-[10px]"
                    :class="
                      descendant.service_projection.billing_reseller_projected
                        ? 'text-emerald-700'
                        : 'text-amber-800'
                    "
                  >
                    {{
                      descendant.service_projection.billing_reseller_projected
                        ? 'Projected in GridPBX'
                        : 'Not projected'
                    }}
                  </p>
                </div>

                <div>
                  <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    Health
                  </p>
                  <span
                    class="mt-1 inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold"
                    :class="projectionStatusClass(descendant.service_projection.status)"
                  >
                    {{ humanize(descendant.service_projection.status) }}
                  </span>
                </div>

                <ProjectionFreshness
                  :last-synchronized-at="descendant.service_projection.last_successful_at"
                  :status="descendant.service_projection.status"
                />

                <ProjectionSyncButton
                  :aria-label="`Synchronize services for ${descendant.name}`"
                  :synchronizing="reseller.syncingDescendantId === descendant.id"
                  :disabled="
                    !descendant.enabled ||
                    reseller.syncingDescendantId !== null ||
                    descendant.service_projection.status === 'syncing'
                  "
                  class="px-3"
                  @sync="synchronizeDescendant(descendant.id)"
                />
              </article>
            </div>
          </section>
        </div>
      </div>
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
