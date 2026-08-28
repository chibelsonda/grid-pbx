<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Menu, MenuButton, MenuItem, MenuItems, TransitionRoot } from '@headlessui/vue'
import { Bars3Icon, ChevronDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import FormListbox, { type ListboxOptionValue } from '@/shared/components/FormListbox.vue'

defineProps<{ sidebarCollapsed: boolean }>()
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
const accountOptions = computed<ListboxOptionValue[]>(() =>
  accounts.accounts.length
    ? accounts.accounts.map((account) => ({ value: account.id, label: account.name }))
    : [{ value: null, label: 'No mapped account', disabled: true }],
)

async function signOut(): Promise<void> {
  await auth.logout()
  accounts.reset()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-30 h-[60px] bg-white shadow-shell transition-[left] duration-300"
    :class="sidebarCollapsed ? 'lg:left-20' : 'lg:left-[280px]'"
  >
    <div class="flex h-full items-center gap-3 px-4 sm:px-6">
      <button
        type="button"
        class="grid size-9 place-items-center rounded-full text-brand-500 hover:bg-brand-50 lg:hidden"
        aria-label="Open navigation"
        @click="$emit('toggleMobile')"
      >
        <Bars3Icon class="size-5" />
      </button>

      <div class="relative hidden w-full max-w-sm sm:block">
        <MagnifyingGlassIcon
          class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
        />
        <input
          type="search"
          placeholder="Search this workspace..."
          class="h-9 w-full rounded-full bg-slate-100 pr-4 pl-10 text-xs text-slate-700 placeholder:text-slate-400 focus:bg-white focus:outline-none"
        />
      </div>

      <div class="ml-auto flex items-center gap-3">
        <div class="hidden min-w-48 md:block">
          <FormListbox
            :model-value="accounts.selectedId"
            :options="accountOptions"
            aria-label="Current account"
            size="small"
            @update:model-value="accounts.select(typeof $event === 'string' ? $event : null)"
          />
        </div>

        <Menu as="div" class="relative">
          <MenuButton class="flex items-center gap-2 rounded-md p-1 text-left hover:bg-slate-50">
            <span
              class="grid size-8 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-info text-xs font-bold text-white"
              >{{ initials }}</span
            >
            <span class="hidden sm:block">
              <span class="block text-xs font-semibold text-slate-700">{{ auth.user?.name }}</span>
              <span class="block text-[10px] text-slate-400">Account menu</span>
            </span>
            <ChevronDownIcon class="hidden size-3.5 text-slate-400 sm:block" />
          </MenuButton>
          <TransitionRoot
            leave="transition ease-in duration-100"
            leave-from="opacity-100"
            leave-to="opacity-0"
          >
            <MenuItems
              class="absolute right-0 z-40 mt-2 w-44 origin-top-right rounded-md border border-slate-200 bg-white p-1 shadow-xl focus:outline-none"
            >
              <MenuItem v-slot="{ active }">
                <button
                  type="button"
                  class="w-full rounded px-3 py-2 text-left text-xs font-semibold"
                  :class="active ? 'bg-red-50 text-danger' : 'text-slate-600'"
                  @click="signOut"
                >
                  Sign out
                </button>
              </MenuItem>
            </MenuItems>
          </TransitionRoot>
        </Menu>
      </div>
    </div>
  </header>
</template>
