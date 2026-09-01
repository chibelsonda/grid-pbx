import { describe, expect, it } from 'vitest'
import { queueStatisticsSchema } from './queueStatisticsSchema'

describe('queueStatisticsSchema', () => {
  it('accepts aggregated metrics and strips unexpected private fields', () => {
    const result = queueStatisticsSchema.parse({
      observed_at: '2026-09-01T04:05:06+00:00',
      totals: {
        waiting: 1,
        handled: 2,
        abandoned: 3,
        processed: 4,
        average_wait_seconds: 12,
        average_talk_seconds: 90,
        longest_current_wait_seconds: 25,
        caller_id_number: '+15551234567',
      },
      queues: [],
      unresolved_records: 0,
      switch_account_id: 'private-account-id',
    })

    expect(result.totals).not.toHaveProperty('caller_id_number')
    expect(result).not.toHaveProperty('switch_account_id')
  })
})
