import { describe, expect, it } from 'vitest'
import { extensionAdvancedSectionForField } from './extensionAdvancedSections'

describe('extensionAdvancedSectionForField', () => {
  it.each([
    ['caller_id.external.number', 'caller-id'],
    ['presence_id', 'options'],
    ['call_forward.number', 'call-forward'],
    ['password_confirmation', 'password'],
    ['call_recording.outbound.offnet.enabled', 'recording'],
    ['hotdesk.pin', 'hot-desking'],
    ['call_restriction.international.action', 'restrictions'],
    ['music_on_hold.media_id', 'media'],
    ['pronounced_name.media_id', 'routing-profile'],
    ['metaflows.binding_digit', 'metaflows'],
  ])('maps %s to %s', (field, section) => {
    expect(extensionAdvancedSectionForField(field)).toBe(section)
  })

  it('keeps Basic fields outside the Advanced tabs', () => {
    expect(extensionAdvancedSectionForField('first_name')).toBeNull()
  })
})
