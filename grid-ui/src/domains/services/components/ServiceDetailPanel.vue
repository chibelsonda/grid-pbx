<script setup lang="ts">
import { BanknotesIcon, CircleStackIcon, ServerStackIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { ServiceOverview } from '../types/service'
defineProps<{ overview: ServiceOverview }>()
defineEmits<{ close: [] }>()
const amount = (value: number): string =>
  new Intl.NumberFormat(undefined, { maximumFractionDigits: 4 }).format(value)
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
