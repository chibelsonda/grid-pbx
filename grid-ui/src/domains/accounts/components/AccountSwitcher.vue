<script setup lang="ts">
import { computed, ref } from 'vue'
import { Popover, PopoverButton, PopoverPanel, TransitionRoot } from '@headlessui/vue'
import { BuildingOffice2Icon, CheckIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import type { Account } from '@/domains/accounts/types/account'
import SearchInput from '@/shared/components/SearchInput.vue'

const props = defineProps<{
  accounts: Account[]
  selectedId: string | null
}>()
const emit = defineEmits<{ select: [accountId: string] }>()
const query = ref('')
const panel = ref<HTMLElement | null>(null)
const selectedAccount = computed(
  () => props.accounts.find((account) => account.id === props.selectedId) ?? null,
)
const filteredAccounts = computed(() => {
  const search = query.value.trim().toLocaleLowerCase()
  if (!search) return props.accounts

  return props.accounts.filter((account) =>
    [account.name, account.realm].some((value) => value?.toLocaleLowerCase().includes(search)),
  )
})

function accountContext(account: Account): string {
  return [
    account.organization.name !== account.name ? account.organization.name : null,
    account.realm,
    account.enabled ? null : 'Disabled',
  ]
    .filter(Boolean)
    .join(' · ')
}

function resetSearch(): void {
  query.value = ''
}

function selectAccount(account: Account, close: () => void): void {
  if (!account.enabled) return

  emit('select', account.id)
  resetSearch()
  close()
}

function focusFirstAccount(event: KeyboardEvent): void {
  event.preventDefault()
  panel.value?.querySelector<HTMLButtonElement>('[data-account-option]:not(:disabled)')?.focus()
}
</script>

<template>
  <Popover v-slot="{ open }" class="relative" data-app-header-account-switcher>
    <PopoverButton
      class="app-header-action flex h-10 w-10 items-center gap-2 rounded-lg px-2 text-left transition md:w-48 md:px-3 xl:w-56"
      :class="open && 'app-header-action-active'"
      :aria-label="`Current account: ${selectedAccount?.name ?? 'No mapped account'}. Open account search`"
      @click="resetSearch"
    >
      <BuildingOffice2Icon class="app-header-muted size-4 shrink-0" aria-hidden="true" />
      <span class="hidden min-w-0 flex-1 md:block">
        <span class="app-header-muted block text-[9px] font-semibold tracking-wide uppercase">
          Current account
        </span>
        <span class="app-header-foreground block truncate text-xs font-semibold">
          {{ selectedAccount?.name ?? 'No mapped account' }}
        </span>
      </span>
      <ChevronDownIcon
        class="app-header-muted hidden size-3.5 shrink-0 transition-transform md:block"
        :class="open && 'rotate-180'"
        aria-hidden="true"
      />
    </PopoverButton>

    <TransitionRoot
      enter="transition ease-out duration-150"
      enter-from="opacity-0 -translate-y-1 scale-[0.98]"
      enter-to="opacity-100 translate-y-0 scale-100"
      leave="transition ease-in duration-100"
      leave-from="opacity-100 translate-y-0 scale-100"
      leave-to="opacity-0 -translate-y-1 scale-[0.98]"
    >
      <PopoverPanel
        v-slot="{ close }"
        ref="panel"
        focus
        role="dialog"
        aria-label="Account search"
        class="absolute right-0 z-50 mt-2 w-[min(20rem,calc(100vw-1rem))] origin-top-right overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl ring-1 ring-slate-900/5 focus:outline-none"
      >
        <div class="border-b border-slate-100 px-4 py-3">
          <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">Browsing</p>
          <p class="mt-0.5 truncate text-xs font-semibold text-slate-700">
            {{ selectedAccount?.name ?? 'No mapped account' }}
          </p>
          <p v-if="selectedAccount" class="mt-0.5 truncate text-[10px] text-slate-500">
            {{ accountContext(selectedAccount) || selectedAccount.organization.name }}
          </p>
        </div>

        <div class="border-b border-slate-100 p-3">
          <SearchInput
            v-model="query"
            label="Search accounts"
            placeholder="Search accounts…"
            input-class="h-9 text-xs"
            autofocus
            autocomplete="off"
            @keydown.down="focusFirstAccount"
          />
          <p class="mt-2 text-[10px] text-slate-500" role="status">
            {{ filteredAccounts.length }}
            {{ filteredAccounts.length === 1 ? 'account' : 'accounts' }} available
          </p>
        </div>

        <ul class="max-h-64 overflow-y-auto p-1.5" aria-label="Available accounts">
          <li v-for="account in filteredAccounts" :key="account.id">
            <button
              type="button"
              data-account-option
              :disabled="!account.enabled"
              class="flex w-full items-center gap-3 rounded-md px-2.5 py-2.5 text-left transition"
              :class="[
                account.id === selectedId
                  ? 'bg-brand-50 text-brand-700'
                  : 'text-slate-700 hover:bg-slate-50',
                !account.enabled && 'cursor-not-allowed opacity-45',
              ]"
              :aria-label="`Switch to ${account.name}`"
              @click="selectAccount(account, close)"
            >
              <BuildingOffice2Icon class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-xs font-semibold">{{ account.name }}</span>
                <span class="mt-0.5 block truncate text-[10px] text-slate-500">
                  {{ accountContext(account) || account.organization.name }}
                </span>
              </span>
              <CheckIcon
                v-if="account.id === selectedId"
                class="size-4 shrink-0 text-brand-600"
                aria-hidden="true"
              />
            </button>
          </li>
          <li v-if="filteredAccounts.length === 0" class="px-3 py-8 text-center">
            <p class="text-xs font-semibold text-slate-600">No matching accounts</p>
            <p class="mt-1 text-[10px] leading-4 text-slate-500">
              Search by projected account name, realm, or organization.
            </p>
          </li>
        </ul>

        <p class="border-t border-slate-100 px-4 py-2.5 text-[9px] leading-4 text-slate-400">
          Only accounts already authorized for your GridPBX organization are searchable.
        </p>
      </PopoverPanel>
    </TransitionRoot>
  </Popover>
</template>
