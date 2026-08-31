import { describe, expect, it } from 'vitest'
import type { FeatureCodeRoute } from '../types/featureCode'
import { presentFeatureCode } from './featureCodePresentation'

function route(overrides: Partial<FeatureCodeRoute>): FeatureCodeRoute {
  return {
    id: '9b27808d-7f2b-40d0-b48e-cce5798548d7',
    numbers: [],
    patterns: [],
    root_module: 'hotdesk',
    feature_code: { name: 'hotdesk[action=login]', number: '11' },
    sync_status: 'healthy',
    last_synced_at: null,
    ...overrides,
  }
}

describe('feature code presentation', () => {
  it('uses the projected route shape to show the actual star prefix', () => {
    expect(presentFeatureCode(route({ numbers: ['*11'] }))).toMatchObject({
      label: 'Hotdesk Login',
      dialCode: '*11',
      category: 'Account access',
      action: 'Login',
    })
  })

  it('shows the double-star voicemail direct pattern and non-star call move', () => {
    const direct = presentFeatureCode(
      route({
        patterns: ['^\\**([0-9]*)$'],
        root_module: 'voicemail',
        feature_code: { name: 'voicemail[action="direct"]', number: '*' },
      }),
    )
    const move = presentFeatureCode(
      route({
        numbers: ['6683'],
        root_module: 'move',
        feature_code: { name: 'move', number: '6683' },
      }),
    )

    expect(direct.dialCode).toBe('**')
    expect(move.dialCode).toBe('6683')
  })
})
