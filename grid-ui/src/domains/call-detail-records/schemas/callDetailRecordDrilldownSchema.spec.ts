import { describe, expect, it } from 'vitest'
import { callDetailRecordDrilldownSchema } from './callDetailRecordDrilldownSchema'

describe('call detail record dashboard drill-down schema', () => {
  it('accepts a bounded period with supported dashboard filters', () => {
    expect(
      callDetailRecordDrilldownSchema.parse({
        started_after: '2026-08-28T00:00:00-07:00',
        started_before: '2026-08-28T01:00:00-07:00',
        direction: 'inbound',
        outcome: 'unanswered',
        search: '1001',
        duration_min: '0',
        duration_max: '15',
      }),
    ).toEqual({
      started_after: '2026-08-28T00:00:00-07:00',
      started_before: '2026-08-28T01:00:00-07:00',
      direction: 'inbound',
      outcome: 'unanswered',
      search: '1001',
      duration_min: '0',
      duration_max: '15',
    })
  })

  it('rejects reversed, offset-free, and unexpected parameters', () => {
    expect(
      callDetailRecordDrilldownSchema.safeParse({
        started_after: '2026-08-28T02:00:00+00:00',
        started_before: '2026-08-28T01:00:00+00:00',
      }).success,
    ).toBe(false)
    expect(
      callDetailRecordDrilldownSchema.safeParse({
        started_after: '2026-08-28T00:00:00+00:00',
        started_before: '2026-08-28T01:00:00+00:00',
        duration_min: '60',
        duration_max: '30',
      }).success,
    ).toBe(false)
    expect(
      callDetailRecordDrilldownSchema.safeParse({
        started_after: '2026-08-28T00:00:00+00:00',
        started_before: '2026-08-28T01:00:00+00:00',
        search: ' '.repeat(2),
      }).success,
    ).toBe(false)
    expect(
      callDetailRecordDrilldownSchema.safeParse({
        started_after: '2026-08-28T00:00:00',
        started_before: '2026-08-28T01:00:00',
      }).success,
    ).toBe(false)
    expect(
      callDetailRecordDrilldownSchema.safeParse({
        started_after: '2026-08-28T00:00:00+00:00',
        started_before: '2026-08-28T01:00:00+00:00',
        private_id: 'not-allowed',
      }).success,
    ).toBe(false)
  })
})
