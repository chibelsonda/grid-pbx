<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Menu, MenuButton, MenuItem, MenuItems, TransitionRoot } from '@headlessui/vue'
import {
  ArrowRightStartOnRectangleIcon,
  Bars3Icon,
  BuildingOffice2Icon,
  ChevronDownIcon,
  ShieldCheckIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { accountRoleLabel } from '@/domains/accounts/accountRole'
import AccountSwitcher from '@/domains/accounts/components/AccountSwitcher.vue'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import GlobalSearch from '@/domains/global-search/components/GlobalSearch.vue'

defineProps<{ sidebarCollapsed: boolean; themeId: string }>()
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
const userContext = computed(() => {
  const role = accounts.selected?.organization_role
  if (!role) return auth.user?.email ?? 'Signed in'

  return accountRoleLabel(role)
})

async function signOut(): Promise<void> {
  await auth.logout()
  accounts.reset()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header
    class="app-header fixed inset-x-0 top-0 z-30 h-[60px] border-b shadow-shell transition-[left] duration-300"
    :class="sidebarCollapsed ? 'lg:left-20' : 'lg:left-[280px]'"
    :data-theme="themeId"
  >
    <div class="flex h-full items-center gap-2 px-4 sm:gap-3 sm:px-6">
      <button
        type="button"
        class="app-header-action grid size-9 place-items-center rounded-full lg:hidden"
        aria-label="Open navigation"
        @click="$emit('toggleMobile')"
      >
        <Bars3Icon class="size-5" />
      </button>

      <div class="w-9 min-w-0 sm:w-auto sm:min-w-48 sm:max-w-md sm:flex-1">
        <GlobalSearch :account-id="accounts.selectedId" :user-id="auth.user?.id ?? null" />
      </div>

      <div class="ml-auto flex h-10 shrink-0 items-center gap-1 sm:gap-2">
        <AccountSwitcher
          :accounts="accounts.accounts"
          :selected-id="accounts.selectedId"
          @select="accounts.select"
        />

        <span class="app-header-divider hidden h-7 w-px md:block" aria-hidden="true" />

        <Menu v-slot="{ open }" as="div" class="relative">
          <MenuButton
            class="app-header-action flex min-h-10 items-center gap-2 rounded-lg px-1.5 text-left transition xl:px-2"
            :class="open && 'app-header-action-active'"
            :aria-label="`Open user menu for ${auth.user?.name ?? 'Grid Admin'}`"
          >
            <span
              class="app-header-avatar grid size-8 place-items-center rounded-full text-xs font-bold text-white shadow-sm ring-2 ring-white/80"
              >{{ initials }}</span
            >
            <span class="hidden min-w-0 xl:block">
              <span class="app-header-foreground block max-w-36 truncate text-xs font-semibold">{{
                auth.user?.name
              }}</span>
              <span class="app-header-muted block max-w-36 truncate text-[11px]">
                {{ userContext }}
              </span>
            </span>
            <ChevronDownIcon class="app-header-muted hidden size-3.5 xl:block" />
          </MenuButton>
          <TransitionRoot
            as="template"
            enter="transition ease-out duration-150"
            enter-from="opacity-0 -translate-y-1 scale-[0.98]"
            enter-to="opacity-100 translate-y-0 scale-100"
            leave="transition ease-in duration-100"
            leave-from="opacity-100 translate-y-0 scale-100"
            leave-to="opacity-0 -translate-y-1 scale-[0.98]"
          >
            <MenuItems
              class="absolute right-0 z-40 mt-2 w-64 origin-top-right overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl ring-1 ring-slate-900/5 focus:outline-none"
            >
              <div class="border-b border-slate-100 px-3 py-3">
                <div class="flex items-center gap-3">
                  <span
                    class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-500 text-xs font-bold text-white"
                  >
                    {{ initials }}
                  </span>
                  <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-slate-700">
                      {{ auth.user?.name ?? 'Grid Admin' }}
                    </p>
                    <p class="mt-0.5 truncate text-[11px] text-slate-500">
                      {{ auth.user?.email ?? 'Signed in to GridPBX' }}
                    </p>
                  </div>
                </div>
                <div
                  v-if="accounts.selected"
                  class="mt-3 flex items-start gap-2 border-t border-slate-100 pt-2.5"
                >
                  <BuildingOffice2Icon class="mt-0.5 size-4 shrink-0 text-brand-500" />
                  <div class="min-w-0">
                    <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                      Current account
                    </p>
                    <p class="truncate text-[11px] font-semibold text-slate-700">
                      {{ accounts.selected.name }}
                    </p>
                    <p class="truncate text-[11px] text-slate-500">{{ userContext }}</p>
                  </div>
                </div>
              </div>

              <div class="p-1.5">
                <MenuItem v-slot="{ active }">
                  <RouterLink
                    :to="{ name: 'settings', hash: '#profile' }"
                    class="flex w-full items-start gap-2.5 rounded-md px-2 py-2 text-left transition"
                    :class="active ? 'bg-brand-50 text-brand-700' : 'text-slate-600'"
                  >
                    <UserCircleIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0">
                      <span class="block text-xs font-semibold">Profile & settings</span>
                      <span class="mt-0.5 block text-[11px] text-slate-500">
                        Identity, appearance, and workspace
                      </span>
                    </span>
                  </RouterLink>
                </MenuItem>
                <MenuItem v-slot="{ active }">
                  <RouterLink
                    :to="{ name: 'settings', hash: '#access-security' }"
                    class="flex w-full items-start gap-2.5 rounded-md px-2 py-2 text-left transition"
                    :class="active ? 'bg-brand-50 text-brand-700' : 'text-slate-600'"
                  >
                    <ShieldCheckIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0">
                      <span class="block text-xs font-semibold">Access & security</span>
                      <span class="mt-0.5 block text-[11px] text-slate-500">
                        Role and granted capabilities
                      </span>
                    </span>
                  </RouterLink>
                </MenuItem>
              </div>

              <div class="border-t border-slate-100 p-1.5">
                <MenuItem v-slot="{ active }">
                  <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-xs font-semibold transition"
                    :class="active ? 'bg-red-50 text-danger' : 'text-slate-600'"
                    @click="signOut"
                  >
                    <ArrowRightStartOnRectangleIcon class="size-4" aria-hidden="true" />
                    Sign out
                  </button>
                </MenuItem>
              </div>
            </MenuItems>
          </TransitionRoot>
        </Menu>
      </div>
    </div>
  </header>
</template>
