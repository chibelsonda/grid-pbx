import { describe, expect, it } from 'vitest'
import { useMediaAudioForm } from './useMediaAudioForm'
import { useMediaForm } from './useMediaForm'

describe('Media form composables', () => {
  it('validates metadata and requires create audio inline', () => {
    const create = useMediaForm('create', null)

    expect(create.validate().success).toBe(false)
    expect(create.validationErrors.value.name).toEqual(['Enter a media name.'])
    expect(create.validationErrors.value.audio).toEqual(['Select an MP3, WAV, or OGG audio file.'])

    create.form.name = 'Lobby loop'
    create.form.audio = new File(['MP3!'], 'lobby.mp3', { type: 'audio/mpeg' })
    expect(create.validate().success).toBe(true)
  })

  it('rejects oversized or mismatched replacement audio', () => {
    const form = useMediaAudioForm()
    form.audio.value = new File(['not audio'], 'payload.svg', { type: 'image/svg+xml' })

    expect(form.validate().success).toBe(false)
    expect(form.validationErrors.value.audio).toEqual(['Select an MP3, WAV, or OGG audio file.'])
  })
})
