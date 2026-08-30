<script setup lang="ts">
import type { ResellerAffectedAccount } from '../types/reseller'

defineProps<{
  guidance: string
  accounts: ResellerAffectedAccount[]
}>()

const statusClass = (status: ResellerAffectedAccount['service_projection_status']): string =>
  ({
    healthy: 'bg-emerald-50 text-emerald-700',
    syncing: 'bg-sky-50 text-sky-700',
    stale: 'bg-amber-50 text-amber-800',
    error: 'bg-red-50 text-red-700',
  })[status]
</script>

<template>
  <div class="grid gap-3 bg-white/60 px-4 py-3">
    <div>
      <p class="text-[10px] font-bold tracking-wide text-slate-500 uppercase">Recovery guidance</p>
      <p class="mt-1 text-xs leading-5 text-slate-700">{{ guidance }}</p>
    </div>

    <div v-if="accounts.length" class="grid gap-2">
      <p class="text-[10px] font-bold tracking-wide text-slate-500 uppercase">Affected accounts</p>
      <article
        v-for="account in accounts"
        :key="account.id"
        class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-slate-200 bg-white px-3 py-2"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-semibold text-slate-800">{{ account.name }}</p>
          <p class="mt-0.5 truncate text-[10px] text-slate-600">
            {{ account.realm || 'No realm reported' }}
          </p>
        </div>
        <span
          class="rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
          :class="statusClass(account.service_projection_status)"
        >
          {{ account.service_projection_status }}
        </span>
      </article>
    </div>
    <p v-else class="text-[11px] leading-4 text-slate-600">
      This blocker is not tied to a projected account record. Follow the guidance above without
      attempting a reseller-role mutation.
    </p>
  </div>
</template>
