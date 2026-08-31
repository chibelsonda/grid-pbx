import { describe, expect, it } from 'vitest'
import { featureCodePageSchema, featureCodeRouteSchema } from './featureCodeSchema'

describe('feature code response schema', () => {
  it('keeps only the public feature-code projection', () => {
    const result = featureCodePageSchema.parse({
      data: [
        {
          id: '9b27808d-7f2b-40d0-b48e-cce5798548d7',
          numbers: ['*11'],
          patterns: [],
          root_module: 'hotdesk',
          feature_code: { name: 'hotdesk[action=login]', number: '11' },
          sync_status: 'healthy',
          last_synced_at: null,
          switch_resource_id: 'raw-callflow-id',
          switch_json: { private: true },
          flow: { data: { id: 'raw-user-id' } },
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 100,
        total: 1,
        sync: {
          status: 'healthy',
          last_successful_at: null,
          error_message: null,
          scope: 'pbx_projection',
        },
      },
    })

    expect(result.data[0]).toEqual({
      id: '9b27808d-7f2b-40d0-b48e-cce5798548d7',
      numbers: ['*11'],
      patterns: [],
      root_module: 'hotdesk',
      feature_code: { name: 'hotdesk[action=login]', number: '11' },
      sync_status: 'healthy',
      last_synced_at: null,
    })
  })

  it('rejects records without a public UUID or feature-code metadata', () => {
    const invalidRecord = {
      numbers: [],
      patterns: [],
      root_module: 'hotdesk',
      feature_code: null,
      sync_status: 'healthy',
      last_synced_at: null,
    }

    expect(() => featureCodeRouteSchema.parse(invalidRecord)).toThrow()
  })
})
