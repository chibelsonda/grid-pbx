import { describe, expect, it } from 'vitest'
import { hydrateExtensionUserConfiguration } from './extensionForm'

describe('hydrateExtensionUserConfiguration', () => {
  it('copies writable user options without leaking read-only Switch metadata', () => {
    const source = {
      language: 'en-US',
      presence_id: '2001',
      call_waiting: { enabled: false },
      do_not_disturb: { enabled: true },
      contact_list: { exclude: true },
      caller_id_options: { outbound_privacy: 'full' as const },
      credentials: { password_configured: true },
      policy: { verified: true },
    }

    expect(hydrateExtensionUserConfiguration(source)).toEqual({
      language: 'en-US',
      presence_id: '2001',
      call_waiting: { enabled: false },
      do_not_disturb: { enabled: true },
      contact_list: { exclude: true },
      caller_id_options: { outbound_privacy: 'full' },
    })
  })
})
