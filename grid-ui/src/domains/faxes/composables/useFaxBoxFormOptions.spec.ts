import { describe, expect, it } from 'vitest'
import { useFaxBoxFormOptions } from './useFaxBoxFormOptions'

describe('useFaxBoxFormOptions', () => {
  it('offers inherited timezone, projected numbers, and retained legacy values', () => {
    const result = useFaxBoxFormOptions(
      () => ({
        owners: [],
        caller_id_numbers: ['+12025550100'],
        timezones: ['UTC', 'Asia/Manila'],
        account_defaults: { timezone: 'Asia/Manila' },
      }),
      () => null,
      () => '+12025550999',
      () => 'Custom/Legacy',
    )

    expect(result.timezoneOptions.value[0]?.value).toBe('Custom/Legacy')
    expect(result.timezoneOptions.value).toContainEqual({
      value: null,
      label: 'Account default (Asia/Manila)',
    })
    expect(result.callerIdOptions.value[0]?.label).toContain('Current projected value')
  })
})
