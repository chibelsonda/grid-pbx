<script setup lang="ts">
import { computed, watch } from 'vue'
import {
  ArrowPathIcon,
  ArrowsRightLeftIcon,
  ChatBubbleLeftRightIcon,
  CheckCircleIcon,
  ClockIcon,
  LinkIcon,
  NoSymbolIcon,
  PhoneArrowUpRightIcon,
  ServerStackIcon,
  ShoppingCartIcon,
  SignalIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import OperationalStatusCard from '../components/OperationalStatusCard.vue'
import { useOperationalStatusStore } from '../stores/operationalStatusStore'

const accounts = useAccountStore()
const operationalStatus = useOperationalStatusStore()

const observedAt = computed(() => {
  if (!operationalStatus.status) return null

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'medium',
  }).format(new Date(operationalStatus.status.observed_at))
})

watch(
  () => accounts.selectedId,
  (accountId) => {
    operationalStatus.reset()
    if (accountId) void operationalStatus.load(accountId)
  },
  { immediate: true },
)
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white py-5">
    <div class="page-container flex items-center gap-4">
      <div>
        <p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Operations</p>
        <h1 class="text-xl font-semibold tracking-tight text-slate-800">System status</h1>
        <p class="mt-1 text-xs text-slate-500">
          Safe, read-only capabilities reported by the selected Switch account.
        </p>
      </div>
      <button
        type="button"
        :disabled="!accounts.selectedId || operationalStatus.loading"
        class="ml-auto inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white disabled:opacity-50"
        @click="accounts.selectedId && operationalStatus.load(accounts.selectedId)"
      >
        <ArrowPathIcon class="size-4" :class="operationalStatus.loading && 'animate-spin'" />
        Refresh
      </button>
    </div>
  </section>

  <div class="page-container py-4 sm:py-6 lg:py-8">
    <div
      v-if="operationalStatus.error"
      class="mb-4 rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ operationalStatus.error }}
    </div>
    <div
      v-if="operationalStatus.loading"
      class="card-surface p-14 text-center text-xs text-slate-400"
    >
      Loading operational status…
    </div>
    <div
      v-else-if="!accounts.selectedId"
      class="card-surface p-14 text-center text-xs text-slate-400"
    >
      Select an account to inspect its operational capabilities.
    </div>
    <div v-else-if="operationalStatus.status" class="grid gap-5 lg:grid-cols-2 xl:grid-cols-4">
      <OperationalStatusCard title="Presence">
        <template #icon><SignalIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold"
            :class="
              operationalStatus.status.presence.subscription_diagnostics_available
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <CheckCircleIcon
              v-if="operationalStatus.status.presence.subscription_diagnostics_available"
              class="size-3.5"
            />
            <NoSymbolIcon v-else class="size-3.5" />
            {{
              operationalStatus.status.presence.subscription_diagnostics_available
                ? 'Diagnostics available'
                : 'Unavailable'
            }}
          </span>
        </template>
        <p class="text-xs leading-5 text-slate-500">
          The installed endpoint reports SIP subscription diagnostics. It does not provide a
          trustworthy live user-presence state.
        </p>
        <div
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3 text-[11px] leading-5 text-amber-800"
        >
          Live presence status and set/reset commands remain capability-gated.
        </div>
      </OperationalStatusCard>

      <OperationalStatusCard title="Number acquisition" icon-class="text-teal-600">
        <template #icon><ShoppingCartIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold"
            :class="
              operationalStatus.status.number_management.carrier_configuration_available
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <CheckCircleIcon
              v-if="operationalStatus.status.number_management.carrier_configuration_available"
              class="size-3.5"
            />
            <NoSymbolIcon v-else class="size-3.5" />
            {{
              operationalStatus.status.number_management.carrier_configuration_available
                ? 'Carrier endpoint available'
                : 'Unavailable'
            }}
          </span>
        </template>
        <p class="text-xs leading-5 text-slate-500">
          Only the account-scoped carrier configuration endpoint shape is reported. Carrier names,
          modules, provider credentials, available numbers, quotes, and charges remain private.
        </p>
        <div
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3 text-[11px] leading-5 text-amber-800"
        >
          Search, purchase, reservation, and release remain capability-gated pending provider,
          billing, confirmation, idempotency, dependency, and recovery controls.
        </div>
      </OperationalStatusCard>

      <OperationalStatusCard title="Connectivity / trunks" icon-class="text-indigo-600">
        <template #icon><ServerStackIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold"
            :class="
              operationalStatus.status.connectivity.summary_available
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <CheckCircleIcon
              v-if="operationalStatus.status.connectivity.summary_available"
              class="size-3.5"
            />
            <NoSymbolIcon v-else class="size-3.5" />
            {{
              operationalStatus.status.connectivity.summary_available
                ? 'Summary available'
                : 'Unavailable'
            }}
          </span>
        </template>
        <dl class="grid grid-cols-2 gap-3 text-xs">
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">PBXs</dt>
            <dd class="mt-1 font-medium text-slate-700">
              {{ operationalStatus.status.connectivity.configured_pbx_count ?? '—' }} configured
            </dd>
          </div>
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
              Local resources
            </dt>
            <dd class="mt-1 font-medium text-slate-700">
              {{ operationalStatus.status.connectivity.local_resource_count ?? '—' }} configured
            </dd>
          </div>
        </dl>
        <p class="mt-4 text-xs leading-5 text-slate-500">
          Only aggregate collection counts are exposed. Raw IDs, servers, DIDs, routes, credentials,
          selectors, limits, and failover data remain private.
        </p>
        <div
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3 text-[11px] leading-5 text-amber-800"
        >
          Connectivity, Resource, selector, limit, and failover changes remain capability-gated.
        </div>
      </OperationalStatusCard>

      <OperationalStatusCard title="Parked calls" icon-class="text-violet-600">
        <template #icon><PhoneArrowUpRightIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold"
            :class="
              operationalStatus.status.parking.summary_available
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <CheckCircleIcon
              v-if="operationalStatus.status.parking.summary_available"
              class="size-3.5"
            />
            <NoSymbolIcon v-else class="size-3.5" />
            {{
              operationalStatus.status.parking.summary_available
                ? 'Summary available'
                : 'Unavailable'
            }}
          </span>
        </template>
        <p class="text-3xl font-semibold text-slate-800">
          {{ operationalStatus.status.parking.active_call_count ?? '—' }}
        </p>
        <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
          Active parked calls
        </p>
        <p class="mt-4 text-xs leading-5 text-slate-500">
          Only the aggregate count is exposed. Raw call identifiers, SIP data, and parking-slot
          payloads remain private.
        </p>
        <div
          class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-3 text-[11px] leading-5 text-slate-600"
        >
          Park and retrieve actions require an active phone call and are not available as REST
          controls.
        </div>
      </OperationalStatusCard>

      <OperationalStatusCard title="Webhooks" icon-class="text-cyan-600">
        <template #icon><LinkIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold"
            :class="
              operationalStatus.status.webhooks.configuration_summary_available
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <CheckCircleIcon
              v-if="operationalStatus.status.webhooks.configuration_summary_available"
              class="size-3.5"
            />
            <NoSymbolIcon v-else class="size-3.5" />
            {{
              operationalStatus.status.webhooks.configuration_summary_available
                ? 'Summary available'
                : 'Unavailable'
            }}
          </span>
        </template>
        <p class="text-3xl font-semibold text-slate-800">
          {{ operationalStatus.status.webhooks.enabled_count ?? '—' }}
          <span class="text-base font-medium text-slate-400">
            / {{ operationalStatus.status.webhooks.configured_count ?? '—' }}
          </span>
        </p>
        <p class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
          Enabled / configured
        </p>
        <p class="mt-4 text-xs leading-5 text-slate-500">
          {{ operationalStatus.status.webhooks.available_event_count ?? 'No' }} installed event
          types are discoverable. URLs, custom data, raw IDs, and delivery payloads remain private.
        </p>
        <div
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3 text-[11px] leading-5 text-amber-800"
        >
          Configuration changes and delivery history remain capability-gated pending hardened
          outbound delivery and redacted attempt records.
        </div>
      </OperationalStatusCard>

      <OperationalStatusCard title="SMS / MMS" icon-class="text-fuchsia-600">
        <template #icon><ChatBubbleLeftRightIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-600"
          >
            <NoSymbolIcon class="size-3.5" />
            Sending unavailable
          </span>
        </template>
        <dl class="grid grid-cols-2 gap-3 text-xs">
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">SMS</dt>
            <dd class="mt-1 font-medium text-slate-700">
              {{
                operationalStatus.status.messaging.sms_inventory_available
                  ? 'Inventory available'
                  : 'Unavailable'
              }}
            </dd>
          </div>
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">MMS</dt>
            <dd class="mt-1 font-medium text-slate-700">
              {{
                operationalStatus.status.messaging.mms_inventory_available
                  ? 'Inventory available'
                  : 'Unavailable'
              }}
            </dd>
          </div>
        </dl>
        <p class="mt-4 text-xs leading-5 text-slate-500">
          Only endpoint availability is reported. Message bodies, participants, raw IDs, and
          attachments remain private.
        </p>
        <div
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3 text-[11px] leading-5 text-amber-800"
        >
          Sending and message content remain capability-gated pending carrier enablement, consent,
          abuse controls, billing, and retention policy.
        </div>
      </OperationalStatusCard>

      <OperationalStatusCard title="Number porting" icon-class="text-orange-600">
        <template #icon><ArrowsRightLeftIcon class="size-5" /></template>
        <template #status>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold"
            :class="
              operationalStatus.status.number_porting.inventory_available
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <CheckCircleIcon
              v-if="operationalStatus.status.number_porting.inventory_available"
              class="size-3.5"
            />
            <NoSymbolIcon v-else class="size-3.5" />
            {{
              operationalStatus.status.number_porting.inventory_available
                ? 'Inventory endpoint available'
                : 'Unavailable'
            }}
          </span>
        </template>
        <p class="text-xs leading-5 text-slate-500">
          Only collection availability is reported. Request numbers, losing-carrier billing details,
          PINs, comments, raw authority identities, and uploads remain private.
        </p>
        <div
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-3 text-[11px] leading-5 text-amber-800"
        >
          Create, submit, schedule, complete, cancel, document access, and carrier automation remain
          capability-gated.
        </div>
      </OperationalStatusCard>

      <p class="flex items-center gap-2 text-[11px] text-slate-400 lg:col-span-2 xl:col-span-4">
        <ClockIcon class="size-4" /> Observed {{ observedAt }}. Results are cached for up to 10
        seconds.
      </p>
    </div>
  </div>
</template>
