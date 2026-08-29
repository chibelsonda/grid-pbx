import { describe, expect, it } from 'vitest'
import { recordingFilterSchema } from './recordingFilterSchema'

describe('recordingFilterSchema', () => {
  it('matches the API range constraints', () => {
    const result = recordingFilterSchema.safeParse({
      search: '',
      direction: '',
      started_from: '2026-08-29',
      started_to: '2026-08-28',
      duration_min: '86401',
      duration_max: '60',
      has_audio: '1',
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.flatten().fieldErrors.started_to).toEqual([
        'The end date must be on or after the start date.',
      ])
      expect(result.error.flatten().fieldErrors.duration_min).toEqual([
        'Enter a duration from 0 to 86400 seconds.',
      ])
      expect(result.error.flatten().fieldErrors.duration_max).toEqual([
        'The maximum duration must be greater than or equal to the minimum duration.',
      ])
    }
  })

  it('normalizes values emitted by number inputs before comparing duration ranges', () => {
    const result = recordingFilterSchema.safeParse({
      search: '',
      direction: '',
      started_from: '',
      started_to: '',
      duration_min: 60,
      duration_max: 120,
      has_audio: '1',
    })

    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.duration_min).toBe('60')
      expect(result.data.duration_max).toBe('120')
    }
  })
})
