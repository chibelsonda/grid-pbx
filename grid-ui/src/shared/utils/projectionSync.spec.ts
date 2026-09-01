import { describe, expect, it } from 'vitest'
import { latestSynchronizedAt } from './projectionSync'

describe('latestSynchronizedAt', () => {
  it('returns the newest valid persisted projection timestamp', () => {
    expect(
      latestSynchronizedAt([
        { last_synced_at: null },
        { last_synced_at: '2026-08-31T23:00:00Z' },
        { last_synced_at: 'invalid' },
        { last_synced_at: '2026-09-01T01:00:00Z' },
      ]),
    ).toBe('2026-09-01T01:00:00Z')
  })
})
