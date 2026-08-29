import { describe, expect, it } from 'vitest'
import { defaultExtensionFormOptions } from '../extensionForm'
import { useExtensionFormOptions } from './useExtensionFormOptions'

describe('useExtensionFormOptions', () => {
  it('retains projected custom values and filters starter devices by API capability', () => {
    const options = defaultExtensionFormOptions()
    options.account_defaults.timezone = 'Asia/Manila'
    options.timezones = ['Asia/Manila', 'Europe/London']
    options.presence_ids = [{ value: '1001', label: '1001 — Alice' }]
    const result = useExtensionFormOptions(
      () => options,
      () => ({ timezone: 'Custom/Legacy', language: 'pt-BR', presenceId: 'alice@example.test' }),
      () => '1001',
    )

    expect(result.timezoneOptions.value[0]?.value).toBe('Custom/Legacy')
    expect(result.languageOptions.value[0]?.value).toBe('pt-BR')
    expect(result.presenceOptions.value[0]?.value).toBe('alice@example.test')
    expect(result.starterDeviceTypes.value.map(({ value }) => value)).toEqual([
      'sip_device',
      'smartphone',
      'softphone',
      'fax',
      'ata',
    ])
  })
})
