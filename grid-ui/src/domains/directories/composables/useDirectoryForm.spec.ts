import { describe, expect, it } from 'vitest'
import type { Directory } from '../types/directory'
import { useDirectoryForm } from './useDirectoryForm'

const record: Directory = {
  id: '6dd4ec45-b29c-4f8b-a142-e886978d1757',
  name: 'People',
  confirm_match: true,
  min_dtmf: 3,
  max_dtmf: 0,
  sort_by: 'last_name',
  flags: ['public-directory', 'voice'],
  members: [
    {
      id: '28e43688-a7fb-4d28-a37d-ec6992d78303',
      extension: {
        id: 'fa28d177-5378-43bf-9a66-b3e636b04fd7',
        label: 'Ada Lovelace',
        number: '1001',
      },
      callflow: null,
      resolved: false,
    },
  ],
  sync_status: 'healthy',
  last_synced_at: null,
}

describe('useDirectoryForm', () => {
  it('hydrates and validates the editable Switch contract', () => {
    const { form, validate } = useDirectoryForm(record)

    expect(validate()).toEqual({
      success: true,
      data: {
        name: 'People',
        confirm_match: true,
        min_dtmf: 3,
        max_dtmf: 0,
        sort_by: 'last_name',
        member_ids: ['fa28d177-5378-43bf-9a66-b3e636b04fd7'],
      },
      errors: {},
    })
  })

  it('reports invalid DTMF and invalid public member ids', () => {
    const { form, validate } = useDirectoryForm(null)
    form.name = 'People'
    form.min_dtmf = 0
    form.max_dtmf = 21
    form.member_ids = ['switch-user-1']

    const result = validate()

    expect(result.success).toBe(false)
    expect(Object.keys(result.errors)).toEqual(
      expect.arrayContaining(['min_dtmf', 'max_dtmf', 'member_ids.0']),
    )
  })
})
