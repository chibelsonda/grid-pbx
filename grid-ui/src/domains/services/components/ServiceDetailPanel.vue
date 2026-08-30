<script setup lang="ts">
import { BanknotesIcon, CircleStackIcon, ServerStackIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { ServiceOverview } from '../types/service'
defineProps<{ overview: ServiceOverview }>()
defineEmits<{ close: [] }>()
const amount = (value: number | string | null): string => {
  if (value === null) return 'Not reported'

  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 8 }).format(Number(value))
}
const label = (value: string): string =>
  value
    .replaceAll('-', ' ')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())
</script>

<template>
  <CrudSlideOver
    title="Service details"
    eyebrow="GridPBX / Services"
    description="Read-only plan, quantity, limit, and billing-impact projection."
    width="medium"
    @close="$emit('close')"
  >
    <div class="grid gap-5">
      <article class="card-surface p-5">
        <div class="flex items-center gap-3">
          <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><BanknotesIcon class="size-5"
          /></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Billing impact</h2>
            <p class="text-[10px] text-slate-400">
              Informational only; no billing mutation is available.
            </p>
          </div>
        </div>
        <dl class="mt-5 grid gap-3 text-xs">
          <div class="flex justify-between">
            <dt class="text-slate-400">Due today</dt>
            <dd class="font-semibold text-slate-700">
              {{ amount(overview.billing_impact.due_today) }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-slate-400">Recurring</dt>
            <dd class="font-semibold text-slate-700">
              {{ amount(overview.billing_impact.recurring_amount) }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-slate-400">Invoice groups</dt>
            <dd class="font-semibold text-slate-700">
              {{ overview.billing_impact.invoice_count }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-slate-400">Next cycle</dt>
            <dd class="font-semibold text-slate-700">
              {{
                overview.billing_cycle.next_at
                  ? new Date(overview.billing_cycle.next_at).toLocaleString()
                  : 'Not reported'
              }}
            </dd>
          </div>
        </dl>
      </article>
      <article class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span
            class="grid size-10 shrink-0 place-items-center rounded-md bg-violet-50 text-violet-600"
            ><CircleStackIcon class="size-5"
          /></span>
          <div class="min-w-0">
            <h2 class="text-sm font-semibold text-slate-700">Switch billing activity</h2>
            <p class="text-[10px] leading-4 text-slate-500">
              Read-only ledger and transaction projections. Amounts use the account currency
              configured in Switch; no currency is assumed when it is not reported.
            </p>
          </div>
        </div>

        <p v-if="!overview.billing" class="mt-5 text-xs text-slate-500">
          No billing activity projection is available. Run a service synchronization to discover
          supported endpoints.
        </p>
        <template v-else>
          <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-md border border-slate-200 bg-slate-50/60 p-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Ledger total
              </p>
              <p class="mt-1 text-sm font-semibold text-slate-700">
                {{ amount(overview.billing.ledger_total) }}
              </p>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50/60 p-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Ledger sources
              </p>
              <p class="mt-1 text-sm font-semibold text-slate-700">
                {{ overview.billing.ledger_source_count }}
              </p>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50/60 p-3">
              <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                Transactions projected
              </p>
              <p class="mt-1 text-sm font-semibold text-slate-700">
                {{ overview.billing.transaction_count }}
              </p>
            </div>
          </div>

          <div
            v-if="
              !overview.billing.availability.ledgers ||
              !overview.billing.availability.ledger_total ||
              !overview.billing.availability.transactions
            "
            class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-[11px] leading-4 text-amber-800"
          >
            This Switch version did not expose every read-only billing endpoint. Existing projected
            history is retained and no write fallback is attempted.
          </div>

          <div class="mt-5">
            <h3 class="text-xs font-semibold text-slate-700">Ledger sources</h3>
            <div v-if="overview.billing.ledger_summaries.length" class="mt-2 grid gap-2">
              <div
                v-for="ledger in overview.billing.ledger_summaries"
                :key="ledger.id"
                class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2.5"
              >
                <div class="min-w-0">
                  <p class="truncate text-xs font-semibold text-slate-700">
                    {{ label(ledger.source_service) }}
                  </p>
                  <p class="mt-0.5 text-[10px] text-slate-500">
                    <template v-if="ledger.usage_quantity !== null">
                      {{ amount(ledger.usage_quantity) }} {{ ledger.usage_unit || 'units' }}
                    </template>
                    <template v-else>No usage quantity reported</template>
                  </p>
                </div>
                <p class="shrink-0 text-xs font-semibold text-slate-700">
                  {{ amount(ledger.amount) }}
                </p>
              </div>
            </div>
            <p v-else class="mt-2 text-xs text-slate-500">No ledger sources were reported.</p>
          </div>

          <div class="mt-5">
            <h3 class="text-xs font-semibold text-slate-700">Recent transactions</h3>
            <div
              v-if="overview.billing.transactions.length"
              class="mt-2 divide-y divide-slate-100 rounded-md border border-slate-200"
            >
              <div
                v-for="transaction in overview.billing.transactions"
                :key="transaction.id"
                class="flex items-start justify-between gap-4 px-3 py-3"
              >
                <div class="min-w-0">
                  <p class="truncate text-xs font-semibold text-slate-700">
                    {{ transaction.description || label(transaction.reason || 'Transaction') }}
                  </p>
                  <p class="mt-0.5 text-[10px] text-slate-500">
                    {{ transaction.type ? label(transaction.type) : 'Type not reported' }}
                    <template v-if="transaction.created_at">
                      · {{ new Date(transaction.created_at).toLocaleString() }}
                    </template>
                  </p>
                </div>
                <p class="shrink-0 text-xs font-semibold text-slate-700">
                  {{ amount(transaction.amount) }}
                </p>
              </div>
            </div>
            <p v-else class="mt-2 text-xs text-slate-500">No transactions were reported.</p>
          </div>
        </template>
      </article>
      <article class="card-surface p-5">
        <div class="mb-4 flex items-center gap-2">
          <ServerStackIcon class="size-4 text-brand-500" />
          <h2 class="text-sm font-semibold text-slate-700">Limits</h2>
        </div>
        <p v-if="!overview.limits" class="text-xs text-slate-400">
          No limits projection is available.
        </p>
        <dl v-else class="grid gap-3 text-xs">
          <div
            v-for="row in [
              { label: 'Inbound trunks', value: overview.limits.inbound_trunks },
              { label: 'Outbound trunks', value: overview.limits.outbound_trunks },
              { label: 'Two-way trunks', value: overview.limits.twoway_trunks },
              { label: 'Burst trunks', value: overview.limits.burst_trunks },
              { label: 'Call ceiling', value: overview.limits.calls ?? 'Not set' },
              {
                label: 'Resource call ceiling',
                value: overview.limits.resource_consuming_calls ?? 'Not set',
              },
            ]"
            :key="row.label"
            class="flex justify-between"
          >
            <dt class="text-slate-400">{{ row.label }}</dt>
            <dd class="font-semibold text-slate-700">{{ row.value }}</dd>
          </div>
        </dl>
      </article>
      <article class="card-surface p-5">
        <div class="mb-4 flex items-center gap-2">
          <CircleStackIcon class="size-4 text-brand-500" />
          <h2 class="text-sm font-semibold text-slate-700">Assigned plans</h2>
        </div>
        <div v-if="overview.plans.length" class="grid gap-3">
          <div
            v-for="plan in overview.plans"
            :key="plan.id"
            class="rounded-md border border-slate-100 p-3"
          >
            <p class="text-xs font-semibold text-slate-700">
              {{ plan.name || 'Assigned service plan' }}
            </p>
            <p class="mt-1 text-[10px] text-slate-400">
              {{ plan.description || plan.category || 'No public description' }}
            </p>
          </div>
        </div>
        <p v-else class="text-xs text-slate-400">No plans are assigned.</p>
      </article>
      <p class="rounded-md border border-amber-100 bg-amber-50 p-4 text-[11px] text-amber-800">
        Plan assignment, limit changes, top-ups, manual quantities, and charge acceptance remain
        disabled until explicit reseller and billing authorization is designed.
      </p>
    </div>
  </CrudSlideOver>
</template>
