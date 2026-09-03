import { beforeEach, describe, expect, it, vi } from 'vitest'
import { callflowIntegrationProfileApi } from '../api/callflowIntegrationProfileApi'
import { announceCallflowCapabilitiesChanged } from '../services/callflowCapabilityRefresh'
import type { PivotIntegrationProfile } from '../types/callflowIntegrationProfile'
import { useCallflowIntegrationProfiles } from './useCallflowIntegrationProfiles'

vi.mock('../api/callflowIntegrationProfileApi', () => ({
  callflowIntegrationProfileApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  },
}))
vi.mock('../services/callflowCapabilityRefresh', () => ({
  announceCallflowCapabilitiesChanged: vi.fn(),
}))

const profile: PivotIntegrationProfile = {
  id: 'profile-public-id',
  integration_type: 'pivot',
  name: 'Customer voice application',
  is_active: true,
  configuration: {
    methods: ['post'],
    formats: ['switch'],
    has_cdr_callback: false,
    has_custom_headers: false,
  },
  created_at: '2026-09-01T10:00:00.000Z',
  updated_at: '2026-09-01T10:00:00.000Z',
}

describe('useCallflowIntegrationProfiles', () => {
  beforeEach(() => vi.clearAllMocks())

  it('invalidates Callflow capabilities after successful profile mutations', async () => {
    vi.mocked(callflowIntegrationProfileApi.create).mockResolvedValue(profile)
    vi.mocked(callflowIntegrationProfileApi.update).mockResolvedValue({
      ...profile,
      is_active: false,
    })
    vi.mocked(callflowIntegrationProfileApi.remove).mockResolvedValue()
    const integrations = useCallflowIntegrationProfiles()

    expect(
      await integrations.create('account-public-id', {
        integration_type: 'pivot',
        name: profile.name,
        is_active: true,
        settings: {
          voice_url: 'https://voice.example.test/pivot',
          cdr_url: null,
          methods: ['post'],
          formats: ['switch'],
          req_body_format: 'json',
          req_timeout_ms: 3000,
          custom_request_headers: {},
        },
      }),
    ).toBe(true)
    expect(await integrations.setActive('account-public-id', profile, false)).toBe(true)
    expect(await integrations.remove('account-public-id', profile.id)).toBe(true)

    expect(announceCallflowCapabilitiesChanged).toHaveBeenCalledTimes(3)
    expect(announceCallflowCapabilitiesChanged).toHaveBeenCalledWith('account-public-id')
  })

  it('does not invalidate capabilities when the provider rejects a mutation', async () => {
    vi.mocked(callflowIntegrationProfileApi.create).mockRejectedValue(new Error('rejected'))
    const integrations = useCallflowIntegrationProfiles()

    expect(
      await integrations.create('account-public-id', {
        integration_type: 'pivot',
        name: profile.name,
        is_active: true,
        settings: {
          voice_url: 'https://voice.example.test/pivot',
          cdr_url: null,
          methods: ['post'],
          formats: ['switch'],
          req_body_format: 'json',
          req_timeout_ms: 3000,
          custom_request_headers: {},
        },
      }),
    ).toBe(false)
    expect(announceCallflowCapabilitiesChanged).not.toHaveBeenCalled()
  })
})
