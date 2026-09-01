import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import type { Account } from '@/domains/accounts/types/account'
import { useAuthStore } from '@/domains/auth/stores/authStore'
import AccountSwitcher from '@/domains/accounts/components/AccountSwitcher.vue'
import AppHeader from './AppHeader.vue'

const permissions: Account['permissions'] = {
  can_manage_extensions: true,
  can_manage_devices: true,
  can_manage_voicemail: true,
  can_manage_call_routing: true,
  can_manage_media: true,
  can_sync_call_detail_records: true,
  can_view_services: true,
  can_manage_account_settings: true,
  can_onboard_descendants: false,
}

function account(id: string, name: string, enabled = true): Account {
  return {
    id,
    name,
    realm: null,
    timezone: 'America/New_York',
    enabled,
    organization: { id: `organization-${id}`, name },
    organization_role: 'account_admin',
    permissions,
  }
}

async function mountHeader() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  const accounts = useAccountStore()
  auth.user = { id: 'user-public-id', name: 'Grid Admin', email: 'admin@example.test' }
  accounts.accounts = [account('account-one', 'GridPBX'), account('account-two', 'Branch Office')]
  accounts.selectedId = 'account-one'

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/settings', name: 'settings', component: { template: '<div />' } },
    ],
  })
  await router.push('/')
  await router.isReady()

  const wrapper = mount(AppHeader, {
    props: { sidebarCollapsed: false, themeId: 'light' },
    global: {
      plugins: [pinia, router],
      stubs: {
        GlobalSearch: { template: '<button aria-label="Search this workspace">Search</button>' },
      },
    },
  })

  return { accounts, router, wrapper }
}

describe('AppHeader', () => {
  it('groups the current account with a labelled user control', async () => {
    const { accounts, wrapper } = await mountHeader()
    const accountSelector = wrapper.getComponent(AccountSwitcher)

    expect(wrapper.find('[data-app-header-account-switcher]').exists()).toBe(true)
    expect(accountSelector.props('selectedId')).toBe('account-one')
    expect(wrapper.get('[aria-label="Open user menu for Grid Admin"]').text()).toContain(
      'Account Admin',
    )

    accountSelector.vm.$emit('select', 'account-two')
    await nextTick()
    expect(accounts.selectedId).toBe('account-two')
  })

  it('keeps searchable account switching separate from the user menu on small screens', async () => {
    const { accounts, wrapper } = await mountHeader()

    await wrapper.get('[aria-label^="Current account: GridPBX"]').trigger('click')
    await nextTick()
    await wrapper.get('input[aria-label="Search accounts"]').setValue('Branch')
    await wrapper.get('[aria-label="Switch to Branch Office"]').trigger('click')
    expect(accounts.selectedId).toBe('account-two')
    await new Promise((resolve) => setTimeout(resolve, 120))

    await wrapper.get('[aria-label="Open user menu for Grid Admin"]').trigger('click')
    await nextTick()

    expect(wrapper.text()).toContain('admin@example.test')
    expect(wrapper.text()).toContain('Current account')
    expect(wrapper.text()).toContain('Profile & settings')
    expect(wrapper.text()).toContain('Access & security')
    expect(wrapper.find('input[aria-label="Search accounts"]').exists()).toBe(false)
  })

  it('links directly to the implemented profile and access settings sections', async () => {
    const { wrapper } = await mountHeader()

    await wrapper.get('[aria-label="Open user menu for Grid Admin"]').trigger('click')
    await nextTick()
    expect(wrapper.get('a[href="/settings#profile"]').text()).toContain('Profile & settings')
    expect(wrapper.get('a[href="/settings#access-security"]').text()).toContain('Access & security')
  })

  it('prevents disabled accounts from being selected in the responsive menu', async () => {
    const { accounts, wrapper } = await mountHeader()
    accounts.accounts[1]!.enabled = false

    await wrapper.get('[aria-label^="Current account: GridPBX"]').trigger('click')
    await nextTick()

    const disabledAccount = wrapper.get('[aria-label="Switch to Branch Office"]')
    expect(disabledAccount.attributes('disabled')).toBeDefined()
    await disabledAccount.trigger('click')
    expect(accounts.selectedId).toBe('account-one')
  })
})
