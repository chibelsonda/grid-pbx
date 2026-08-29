import { describe, expect, it } from 'vitest'
import type { VoicemailFormOptions } from '../types/voicemail'
import { useVoicemailFormOptions } from './useVoicemailFormOptions'

describe('useVoicemailFormOptions', () => {
  it('uses account choices and safely retains projected legacy values', () => {
    const options: VoicemailFormOptions = {
      account_defaults: { timezone: 'Asia/Manila' },
      timezones: ['Asia/Manila'],
      extensions: [{ id: 'extension-1', display_name: 'Alice', extension: '1001' }],
      capabilities: {
        voicemail_transcription: {
          schema_supported: true,
          runtime_available: null,
          default_enabled: null,
        },
      },
    }
    const result = useVoicemailFormOptions(
      () => options,
      () => 'Custom/Legacy',
      () => 'missing-extension',
    )

    expect(result.timezoneOptions.value[0]?.value).toBe('Custom/Legacy')
    expect(result.extensionOptions.value[0]?.value).toBe('missing-extension')
    expect(result.extensionOptions.value).toContainEqual({
      value: 'extension-1',
      label: 'Alice · 1001',
    })
  })
})
