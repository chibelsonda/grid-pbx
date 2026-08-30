import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
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

const account = {
  id: 'account-public-id',
  name: 'GridPBX',
  realm: 'gridpbx.example.test',
  enabled: true,
  is_reseller: true,
  is_superduper_admin: true,
  billing_mode: 'limits_only',
  descendants_count: 2,
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
    expect(store.status).toEqual(status)
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
  })
})
