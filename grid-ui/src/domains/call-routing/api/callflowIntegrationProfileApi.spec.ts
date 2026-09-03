import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/shared/api/http'
import type { CallflowIntegrationProfile } from '../types/callflowIntegrationProfile'
import { callflowIntegrationProfileApi } from './callflowIntegrationProfileApi'

const profile: CallflowIntegrationProfile = {
  id: '00000000-0000-4000-8000-000000000001',
  integration_type: 'pivot',
  name: 'Customer IVR',
  is_active: true,
  configuration: {
    methods: ['post'],
    formats: ['switch'],
    has_cdr_callback: true,
    has_custom_headers: true,
  },
  created_at: null,
  updated_at: null,
}

describe('callflow integration profile API', () => {
  afterEach(() => vi.restoreAllMocks())

  it('loads only the safe account-scoped profile summary', async () => {
    const get = vi.spyOn(http, 'get').mockResolvedValue({ data: { data: [profile] } })

    const result = await callflowIntegrationProfileApi.list('account-id')

    expect(get).toHaveBeenCalledWith('/api/v1/accounts/account-id/callflow-integration-profiles')
    expect(result).toEqual([profile])
    expect(JSON.stringify(result)).not.toMatch(/voice_url|cdr_url|custom_request_headers|secret/i)
  })

  it('disables a profile without resending its write-only configuration', async () => {
    const put = vi.spyOn(http, 'put').mockResolvedValue({
      data: { data: { ...profile, is_active: false } },
    })

    await callflowIntegrationProfileApi.update('account-id', profile.id, { is_active: false })

    expect(put).toHaveBeenCalledWith(
      `/api/v1/accounts/account-id/callflow-integration-profiles/${profile.id}`,
      { is_active: false },
    )
    expect(JSON.stringify(put.mock.calls)).not.toMatch(/voice_url|custom_request_headers|secret/i)
  })
})
