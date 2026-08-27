<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Bars3Icon, ChevronDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useAuthStore } from '@/domains/auth/stores/authStore'

defineEmits<{ toggleMobile: [] }>()

const router = useRouter()
const auth = useAuthStore()
const accounts = useAccountStore()
const initials = computed(() =>
  (auth.user?.name ?? 'Grid Admin')
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase(),
)

async function signOut(): Promise<void> {
  await auth.logout()
  accounts.reset()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header class="fixed inset-x-0 top-0 z-30 h-[60px] bg-white shadow-shell lg:left-auto">
    <div class="flex h-full items-center gap-3 px-4 sm:px-6">
      <button type="button" class="grid size-9 place-items-center rounded-full text-brand-500 hover:bg-brand-50 lg:hidden" aria-label="Open navigation" @click="$emit('toggleMobile')">
        <Bars3Icon class="size-5" />
      </button>

      <div class="relative hidden w-full max-w-sm sm:block">
        <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" placeholder="Search this workspace..." class="h-9 w-full rounded-full bg-slate-100 pr-4 pl-10 text-xs text-slate-700 placeholder:text-slate-400 focus:bg-white focus:outline-none" />
      </div>

      <div class="ml-auto flex items-center gap-3">
        <div class="relative hidden md:block">
          <select
            :value="accounts.selectedId ?? ''"
            class="h-9 min-w-48 appearance-none rounded-md border border-slate-200 bg-white pr-9 pl-3 text-xs font-semibold text-slate-700 shadow-sm"
            aria-label="Current account"
            @change="accounts.select(($event.target as HTMLSelectElement).value || null)"
          >
            <option v-if="accounts.accounts.length === 0" value="">No mapped account</option>
            <option v-for="account in accounts.accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
          </select>
          <ChevronDownIcon class="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 text-slate-400" />
        </div>

        <span class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-info text-xs font-bold text-white">{{ initials }}</span>
        <div class="hidden text-left sm:block">
          <span class="block text-xs font-semibold text-slate-700">{{ auth.user?.name }}</span>
          <button type="button" class="text-[10px] text-slate-400 hover:text-danger" @click="signOut">Sign out</button>
        </div>
      </div>
    </div>
  </header>
</template>
