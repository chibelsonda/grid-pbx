import { describe, expect, it } from 'vitest'
import { useConferenceForm } from './useConferenceForm'

describe('useConferenceForm', () => {
  it('normalizes access numbers and nullable profile fields', () => {
    const { form, numbers, pins, validate } = useConferenceForm(null)
    form.name = '  Daily standup  '
    form.profile_name = '  '
    numbers.conference = '7000, 7000 7002'
    numbers.member = '7001'
    pins.member = '1234, 5678 1234'

    expect(validate()).toEqual({
      success: true,
      data: expect.objectContaining({
        name: 'Daily standup',
        conference_numbers: ['7000', '7002'],
        member_numbers: ['7001'],
        moderator_numbers: [],
        member_pins: ['1234', '5678'],
        profile_name: null,
      }),
      errors: {},
    })
  })

  it('reports invalid names, numbers, pins, and participant limits', () => {
    const { form, numbers, pins, validate } = useConferenceForm(null)
    numbers.member = 'member-1'
    pins.member = '12ab'
    form.max_participants = 0

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(
      expect.arrayContaining(['name', 'member_numbers.0', 'member_pins.0', 'max_participants']),
    )
  })
})
