<script setup lang="ts">
import {
  ArrowRightIcon,
  BanknotesIcon,
  CircleStackIcon,
  ServerStackIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { ServiceOverview } from '../types/service'

defineProps<{ overview: ServiceOverview }>()
defineEmits<{ close: [] }>()

const amount = (value: number): string =>
  new Intl.NumberFormat(undefined, { maximumFractionDigits: 8 }).format(value)
</script>

<template>
  <CrudSlideOver
    title="Service details"
    eyebrow="GridPBX / Services"
    description="Read-only plan, quantity, and limit projection."
    width="medium"
    @close="$emit('close')"
  >
    <div class="grid gap-5">
      <article class="card-surface overflow-hidden">
        <div class="flex items-start gap-3 p-5">
          <span
            class="grid size-10 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600"
          >
            <BanknotesIcon class="size-5" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 class="text-sm font-semibold text-slate-800">Billing workspace</h2>
                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                  Invoice sources, payment confirmations, reconciliation details, and Switch
                  transactions now have a dedicated read-only workspace.
                </p>
              </div>
              <span
                class="rounded-full px-2.5 py-1 text-[10px] font-semibold"
                :class="
                  overview.reconciliation.status === 'healthy'
                    ? 'bg-emerald-50 text-emerald-700'
                    : overview.reconciliation.status === 'attention'
                      ? 'bg-amber-50 text-amber-800'
                      : 'bg-red-50 text-red-700'
                "
              >
                {{
                  overview.reconciliation.status === 'error'
                    ? 'Requires attention'
                    : overview.reconciliation.status
                }}
              </span>
            </div>
            <dl class="mt-4 grid grid-cols-3 gap-2 text-xs">
              <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
                <dt class="text-[10px] text-slate-500">Recurring</dt>
                <dd class="mt-1 font-semibold text-slate-800">
                  {{ amount(overview.billing_impact.recurring_amount) }}
                </dd>
              </div>
              <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
                <dt class="text-[10px] text-slate-500">Due today</dt>
                <dd class="mt-1 font-semibold text-slate-800">
                  {{ amount(overview.billing_impact.due_today) }}
                </dd>
              </div>
              <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3">
                <dt class="text-[10px] text-slate-500">Invoice groups</dt>
                <dd class="mt-1 font-semibold text-slate-800">
                  {{ overview.billing_impact.invoice_count }}
                </dd>
              </div>
            </dl>
            <RouterLink
              to="/billing"
              class="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600"
            >
              Open billing workspace <ArrowRightIcon class="size-4" />
            </RouterLink>
          </div>
        </div>
      </article>

      <article class="card-surface p-5">
        <div class="mb-4 flex items-center gap-2">
          <ServerStackIcon class="size-4 text-brand-500" />
          <h2 class="text-sm font-semibold text-slate-700">Limits</h2>
        </div>
        <p v-if="!overview.limits" class="text-xs text-slate-500">
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
            <dt class="text-slate-500">{{ row.label }}</dt>
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
            class="rounded-md border border-slate-200 p-3"
          >
            <p class="text-xs font-semibold text-slate-700">
              {{ plan.name || 'Assigned service plan' }}
            </p>
            <p class="mt-1 text-[10px] text-slate-500">
              {{ plan.description || plan.category || 'No public description' }}
            </p>
          </div>
        </div>
        <p v-else class="text-xs text-slate-500">No plans are assigned.</p>
      </article>

      <p class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[11px] text-amber-800">
        Plan assignment, limit changes, top-ups, manual quantities, and production charges remain
        disabled until explicit reseller and billing authorization is designed.
      </p>
    </div>
  </CrudSlideOver>
</template>
