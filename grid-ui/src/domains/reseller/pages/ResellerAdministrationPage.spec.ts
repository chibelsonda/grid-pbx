import { mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import type { Account } from '@/domains/accounts/types/account'
import { resellerApi } from '../api/resellerApi'
import type { AccountHierarchy, ResellerStatus } from '../types/reseller'
import ResellerAdministrationPage from './ResellerAdministrationPage.vue'

vi.mock('../api/resellerApi', () => ({
  resellerApi: {
    hierarchy: vi.fn(),
    status: vi.fn(),
    onboardingCandidates: vi.fn(),
    onboardDescendant: vi.fn(),
  },
}))

const permissions: Account['permissions'] = {
  can_manage_extensions: true,
  can_manage_devices: true,
  can_manage_voicemail: true,
  can_manage_call_routing: true,
  can_manage_media: true,
  can_sync_call_detail_records: true,
  can_view_services: true,
  can_manage_account_settings: true,
  can_onboard_descendants: true,
}

const selectedAccount: Account = {
  id: 'account-public-id',
  name: 'GridPBX',
  realm: 'gridpbx.example.test',
  timezone: 'America/New_York',
  enabled: true,
  organization: { id: 'organization-public-id', name: 'GridPBX' },
  organization_role: 'reseller_admin',
  permissions,
}

const projectedAccount = {
  id: 'account-public-id',
  name: 'GridPBX',
  realm: 'gridpbx.example.test',
  enabled: true,
  is_reseller: true,
  is_superduper_admin: false,
  billing_mode: 'limits_only',
  descendants_count: 0,
  service_projection: {
    status: 'healthy' as const,
    last_successful_at: '2026-08-30T10:01:00Z',
    billing_reseller: null,
    billing_reseller_projected: true,
  },
}

const hierarchy: AccountHierarchy = {
  account: projectedAccount,
  parent: null,
  ancestors: [],
  children: [],
  descendants: [],
  coverage: {
    switch_descendants_count: 0,
    projected_descendants_count: 0,
    unresolved_descendants_count: 0,
    parent_projected: true,
  },
  projection: { last_synced_at: '2026-08-30T10:00:00Z' },
  portfolio: {
    accounts: { total: 1, projected: 1, healthy: 1, attention: 0 },
    billing_ownership: { projected: 1, unresolved: 0 },
    billing: { due_today: 0, recurring_amount: 25 },
    quantities: [
      {
        scope: 'account',
        category: 'account_apps',
        item: 'conference',
        quantity: 8,
      },
      {
        scope: 'manual',
        category: 'usage_allowance',
        item: 'shared_capacity',
        quantity: 1.25,
      },
    ],
    warnings: [],
  },
  mutation_preflight: {
    operation: 'demote',
    operationally_ready: false,
    mutation_available: false,
    checks: [
      {
        code: 'platform_policy_available',
        passed: false,
        count: 1,
        message: 'Platform policy is required.',
        guidance: 'Obtain an approved policy.',
        affected_accounts: [],
      },
    ],
  },
}

const status: ResellerStatus = {
  account: projectedAccount,
  billing_reseller: projectedAccount,
  billing_reseller_projected: true,
  service_projection_last_synced_at: '2026-08-30T10:01:00Z',
  mutations: {
    promote: { available: false, reason: 'platform_policy_required' },
    demote: { available: false, reason: 'platform_policy_required' },
  },
  administration: {
    account_creation_available: false,
    account_move_available: false,
    account_deletion_available: false,
    limit_mutations_available: false,
    service_plan_mutations_available: false,
    service_override_mutations_available: false,
    top_up_available: false,
    switch_service_synchronization_available: false,
    switch_service_reconciliation_available: false,
  },
}

async function mountPage(hash = '') {
  window.localStorage.clear()
  vi.mocked(resellerApi.hierarchy).mockResolvedValue(hierarchy)
  vi.mocked(resellerApi.status).mockResolvedValue(status)

  const pinia = createPinia()
  setActivePinia(pinia)
  const accounts = useAccountStore()
  accounts.accounts = [selectedAccount]
  accounts.selectedId = selectedAccount.id

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/reseller', component: ResellerAdministrationPage }],
  })
  await router.push(`/reseller${hash}`)
  await router.isReady()

  const wrapper = mount(ResellerAdministrationPage, {
    global: { plugins: [pinia, router] },
  })
  await vi.waitFor(() =>
    expect(wrapper.find('nav[aria-label="Reseller administration sections"]').exists()).toBe(true),
  )

  return { router, wrapper }
}

async function selectSection(wrapper: VueWrapper, label: string): Promise<void> {
  const button = wrapper
    .get('nav[aria-label="Reseller administration sections"]')
    .findAll('button')
    .find((candidate) => candidate.text() === label)

  if (!button) throw new Error(`Reseller administration section not found: ${label}`)
  await button.trigger('click')
}

describe('ResellerAdministrationPage', () => {
  it('organizes reseller information into focused, hash-linked sections', async () => {
    const { router, wrapper } = await mountPage()
    const navigation = wrapper.get('nav[aria-label="Reseller administration sections"]')

    expect(navigation.findAll('button').map((button) => button.text())).toEqual([
      'Overview',
      'Account hierarchy',
      'Services & billing',
      'Administration',
    ])
    expect(wrapper.find('#reseller-overview').exists()).toBe(true)
    expect(wrapper.find('#account-hierarchy').exists()).toBe(false)
    expect(wrapper.find('#services-billing').exists()).toBe(false)
    expect(wrapper.find('#administration-safeguards').exists()).toBe(false)

    await selectSection(wrapper, 'Services & billing')
    await vi.waitFor(() => expect(router.currentRoute.value.hash).toBe('#services-billing'))
    await vi.waitFor(() =>
      expect(
        navigation
          .findAll('button')
          .find((button) => button.text() === 'Services & billing')
          ?.attributes('aria-current'),
      ).toBe('page'),
    )
    expect(wrapper.find('#reseller-overview').exists()).toBe(false)
    expect(wrapper.find('#services-billing').exists()).toBe(true)
    const quantityGroups = wrapper.findAll('[data-testid="service-quantity-group"]')
    expect(quantityGroups[0]?.text()).toContain('8')
    expect(quantityGroups[0]?.text()).not.toContain('8.00')
    expect(quantityGroups[1]?.text()).toContain('1.25')
    expect(wrapper.get('[data-testid="service-quantity-total"]').classes()).toContain('text-xs')

    await selectSection(wrapper, 'Administration')
    await vi.waitFor(() =>
      expect(router.currentRoute.value.hash).toBe('#administration-safeguards'),
    )
    await vi.waitFor(() =>
      expect(
        navigation
          .findAll('button')
          .find((button) => button.text() === 'Administration')
          ?.attributes('aria-current'),
      ).toBe('page'),
    )
    expect(wrapper.find('#administration-safeguards').exists()).toBe(true)
    expect(wrapper.find('[data-testid="reseller-mutation-preflight"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Switch service synchronization')
    expect(wrapper.text()).not.toContain('Kazoo')
  })

  it('opens a directly linked reseller section', async () => {
    const { wrapper } = await mountPage('#account-hierarchy')

    expect(wrapper.find('#reseller-overview').exists()).toBe(false)
    expect(wrapper.find('#account-hierarchy').exists()).toBe(true)
  })
})
