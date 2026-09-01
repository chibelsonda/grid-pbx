import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { serviceApi } from '@/domains/services/api/serviceApi'
import { resellerApi } from '../api/resellerApi'
import type { AccountHierarchy, ResellerStatus } from '../types/reseller'
import { useResellerStore } from './resellerStore'

vi.mock('../api/resellerApi', () => ({
  resellerApi: {
    hierarchy: vi.fn(),
    status: vi.fn(),
    onboardingCandidates: vi.fn(),
    onboardDescendant: vi.fn(),
  },
}))

vi.mock('@/domains/services/api/serviceApi', () => ({
  serviceApi: { synchronize: vi.fn() },
}))

const serviceProjection = {
  status: 'healthy' as const,
  last_successful_at: '2026-08-30T10:01:00Z',
  billing_reseller: null,
  billing_reseller_projected: true,
}

const account = {
  id: 'account-public-id',
  name: 'GridPBX',
  realm: 'gridpbx.example.test',
  enabled: true,
  is_reseller: true,
  is_superduper_admin: true,
  billing_mode: 'limits_only',
  descendants_count: 2,
  service_projection: serviceProjection,
}

const hierarchy: AccountHierarchy = {
  account,
  parent: null,
  ancestors: [],
  children: [],
  descendants: [],
  coverage: {
    switch_descendants_count: 2,
    projected_descendants_count: 0,
    unresolved_descendants_count: 2,
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
        category: 'devices',
        item: 'sip_device',
        quantity: 2,
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
  account,
  billing_reseller: account,
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

describe('reseller store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads hierarchy and reseller status together', async () => {
    vi.mocked(resellerApi.hierarchy).mockResolvedValue(hierarchy)
    vi.mocked(resellerApi.status).mockResolvedValue(status)

    const store = useResellerStore()
    await store.load('account-public-id')

    expect(store.hierarchy).toEqual(hierarchy)
    expect(store.hierarchy?.portfolio.billing.recurring_amount).toBe(25)
    expect(store.hierarchy?.mutation_preflight.mutation_available).toBe(false)
    expect(store.status).toEqual(status)
    expect(Object.values(store.status?.administration ?? {})).toEqual(Array(9).fill(false))
    expect(store.error).toBeNull()
  })

  it('clears partial data when either read fails', async () => {
    vi.mocked(resellerApi.hierarchy).mockResolvedValue(hierarchy)
    vi.mocked(resellerApi.status).mockRejectedValue(new Error('unavailable'))

    const store = useResellerStore()
    await store.load('account-public-id')

    expect(store.hierarchy).toBeNull()
    expect(store.status).toBeNull()
    expect(store.error).toBe('Unable to load reseller administration information.')
  })

  it('loads opaque onboarding candidates and applies a successful hierarchy result', async () => {
    vi.mocked(resellerApi.onboardingCandidates).mockResolvedValue({
      candidates: [
        {
          reference: 'opaque-reference',
          name: 'Acme Child',
          realm: 'acme.example.test',
          descendants_count: 0,
          eligible: true,
          blocked_reason: null,
        },
      ],
      target_organization: { id: 'organization-public-id', name: 'GridPBX' },
      access_inheritance: { member_count: 2, acknowledgement_required: true },
      reference_expires_at: '2026-08-30T10:10:00Z',
    })
    vi.mocked(resellerApi.onboardDescendant).mockResolvedValue({
      onboarded_account: {
        id: 'child-public-id',
        name: 'Acme Child',
        realm: 'acme.example.test',
        enabled: true,
      },
      target_organization: { id: 'organization-public-id', name: 'GridPBX' },
      access_inheritance: { member_count: 2, acknowledged: true },
      service_projection: { status: 'queued', sync_run_id: 'sync-run-public-id' },
      hierarchy: {
        ...hierarchy,
        children: [{ ...account, id: 'child-public-id', name: 'Acme Child' }],
        descendants: [{ ...account, id: 'child-public-id', name: 'Acme Child' }],
        coverage: {
          switch_descendants_count: 2,
          projected_descendants_count: 1,
          unresolved_descendants_count: 1,
          parent_projected: true,
        },
      },
    })

    const store = useResellerStore()
    await store.loadOnboardingCandidates('account-public-id')
    const succeeded = await store.onboardDescendant('account-public-id', {
      reference: 'opaque-reference',
      confirmation: 'Acme Child',
      acknowledge_existing_access: true,
    })

    expect(succeeded).toBe(true)
    expect(store.hierarchy?.children[0]?.id).toBe('child-public-id')
    expect(store.onboardingCandidates).toBeNull()
    expect(store.onboardingNotice).toBe(
      'Descendant onboarded. Service ownership synchronization has started.',
    )
    expect(store.onboardingNoticeTone).toBe('success')
  })

  it('warns when onboarding succeeds but service projection cannot start', async () => {
    vi.mocked(resellerApi.onboardDescendant).mockResolvedValue({
      onboarded_account: {
        id: 'child-public-id',
        name: 'Acme Child',
        realm: 'acme.example.test',
        enabled: true,
      },
      target_organization: { id: 'organization-public-id', name: 'GridPBX' },
      access_inheritance: { member_count: 2, acknowledged: true },
      service_projection: { status: 'not_started', sync_run_id: null },
      hierarchy,
    })

    const store = useResellerStore()
    const succeeded = await store.onboardDescendant('account-public-id', {
      reference: 'opaque-reference',
      confirmation: 'Acme Child',
      acknowledge_existing_access: true,
    })

    expect(succeeded).toBe(true)
    expect(store.onboardingNotice).toContain('could not start')
    expect(store.onboardingNoticeTone).toBe('warning')
  })

  it('synchronizes a descendant service projection and reloads reseller data', async () => {
    vi.mocked(serviceApi.synchronize).mockResolvedValue({
      id: 'sync-run-public-id',
      status: 'succeeded',
      error_message: null,
    })
    vi.mocked(resellerApi.hierarchy).mockResolvedValue(hierarchy)
    vi.mocked(resellerApi.status).mockResolvedValue(status)

    const store = useResellerStore()
    const succeeded = await store.synchronizeDescendant('account-public-id', 'child-public-id')

    expect(succeeded).toBe(true)
    expect(serviceApi.synchronize).toHaveBeenCalledWith('child-public-id')
    expect(resellerApi.hierarchy).toHaveBeenCalledWith('account-public-id')
    expect(store.syncingDescendantId).toBeNull()
    expect(store.descendantSyncError).toBeNull()
  })
})
