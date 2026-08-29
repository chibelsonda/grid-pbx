import { describe, expect, it } from 'vitest'
import { callDetailRecordFilterSchema } from './callDetailRecordFilterSchema'

const filters = {
  search: '',
  direction: '' as const,
  outcome: '' as const,
  hangup_cause: '',
  started_from: '',
  started_to: '',
  duration_min: '',
  duration_max: '',
}

describe('callDetailRecordFilterSchema', () => {
  it('rejects reversed date and duration ranges on their ending controls', () => {
    const result = callDetailRecordFilterSchema.safeParse({
      ...filters,
      started_from: '2026-08-29',
      started_to: '2026-08-28',
      duration_min: '120',
      duration_max: '60',
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.flatten().fieldErrors.started_to).toEqual([
        'The end date must be on or after the start date.',
      ])
      expect(result.error.flatten().fieldErrors.duration_max).toEqual([
        'The maximum duration must be greater than or equal to the minimum duration.',
      ])
    }
  })

  it('normalizes values emitted by number inputs before comparing duration ranges', () => {
    const result = callDetailRecordFilterSchema.safeParse({
      ...filters,
      duration_min: 60,
      duration_max: 120,
    })

    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.duration_min).toBe('60')
      expect(result.data.duration_max).toBe('120')
    }
  })
})
