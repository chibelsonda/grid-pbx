<script setup lang="ts">
import { computed } from 'vue'
import { BuildingOffice2Icon, CircleStackIcon, CloudArrowDownIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import StatCard from '@/shared/components/StatCard.vue'

const auth = useAuthStore()
const accounts = useAccountStore()
const firstName = computed(() => auth.user?.name.split(' ')[0] ?? 'Admin')
const stats = computed(() => [
  { label: 'Mapped accounts', value: String(accounts.accounts.length), detail: accounts.selected?.name ?? 'No active account', icon: BuildingOffice2Icon, tone: 'primary' as const },
  { label: 'Projection store', value: 'MySQL', detail: 'Optimized local read model', icon: CircleStackIcon, tone: 'success' as const },
  { label: 'Switch sync', value: 'Queued', detail: 'Resilient background imports', icon: CloudArrowDownIcon, tone: 'info' as const },
  { label: 'Extensions', value: 'Live', detail: 'Open People & Extensions', icon: UserGroupIcon, tone: 'warning' as const },
])
</script>

<template>
  <section class="border-b border-slate-200/80 bg-white px-4 py-5 sm:px-6 lg:px-8"><div class="mx-auto max-w-[1500px]"><p class="mb-1 text-[11px] font-medium text-slate-400">GridPBX / Dashboard</p><h1 class="text-xl font-semibold tracking-tight text-slate-800">Good day, {{ firstName }}</h1><p class="mt-1 text-xs text-slate-500">A simpler operational view over your Switch phone system.</p></div></section>
  <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><StatCard v-for="stat in stats" :key="stat.label" v-bind="stat" /></section>
    <section class="mt-6 grid gap-6 lg:grid-cols-2">
      <article class="card-surface p-6"><p class="eyebrow">Current account</p><h2 class="mt-2 text-lg font-semibold text-slate-800">{{ accounts.selected?.name ?? 'No account selected' }}</h2><p class="mt-2 text-xs leading-5 text-slate-500">{{ accounts.selected ? `Realm: ${accounts.selected.realm ?? 'not supplied'}` : 'Map a Switch account in the API environment to begin.' }}</p><RouterLink to="/extensions" class="mt-5 inline-flex rounded-md bg-brand-500 px-4 py-2.5 text-xs font-semibold text-white">View extensions</RouterLink></article>
      <article class="card-surface p-6"><p class="eyebrow">Data flow</p><h2 class="mt-2 text-lg font-semibold text-slate-800">Switch remains the source of truth</h2><p class="mt-2 text-xs leading-5 text-slate-500">The Laravel worker copies selected operational fields into MySQL. This dashboard reads the projection for speed while writes will continue through audited Switch commands.</p><div class="mt-5 flex items-center gap-2 text-[11px] font-semibold text-emerald-700"><span class="size-2 rounded-full bg-success" /> API projection layer ready</div></article>
    </section>
  </div>
</template>
