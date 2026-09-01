import { describe, expect, it } from 'vitest'
import { conferenceBulkControlSchema } from './conferenceBulkControlSchema'

describe('conferenceBulkControlSchema', () => {
  it('accepts only explicitly confirmed bounded room-wide media controls', () => {
    expect(
      conferenceBulkControlSchema.safeParse({
        action: 'mute',
        expected_participant_count: 3,
        expected_target_count: 2,
        confirmation: true,
      }).success,
    ).toBe(true)
    expect(
      conferenceBulkControlSchema.safeParse({
        action: 'kick',
        expected_participant_count: 3,
        expected_target_count: 3,
        confirmation: true,
      }).success,
    ).toBe(false)
    expect(
      conferenceBulkControlSchema.safeParse({
        action: 'deaf',
        expected_participant_count: 3,
        expected_target_count: 2,
        confirmation: false,
      }).success,
    ).toBe(false)
  })
})
