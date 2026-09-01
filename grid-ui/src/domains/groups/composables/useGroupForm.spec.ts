import { describe, expect, it } from 'vitest'
import { useGroupForm } from './useGroupForm'

describe('useGroupForm', () => {
  it('normalizes and validates the editable Switch contract', () => {
    const { form, validate } = useGroupForm(null)
    form.name = '  Support  '
    form.members = [
      {
        type: 'user',
        id: 'a74d29e4-df5f-4a8c-9079-774d9bb0a605',
        weight: 1,
      },
    ]

    expect(validate()).toEqual({
      success: true,
      data: {
        name: 'Support',
        music_on_hold_media_id: null,
        members: [
          {
            type: 'user',
            id: 'a74d29e4-df5f-4a8c-9079-774d9bb0a605',
            weight: 1,
          },
        ],
      },
      errors: {},
    })
  })

  it('reports an empty name, invalid references, and repeated members', () => {
    const { form, validate } = useGroupForm(null)
    form.members = [
      { type: 'user', id: 'switch-user-1', weight: 0 },
      { type: 'device', id: 'switch-user-1', weight: 2 },
    ]

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(
      expect.arrayContaining([
        'name',
        'members.0.id',
        'members.0.weight',
        'members.1.id',
        'members',
      ]),
    )
  })
})
