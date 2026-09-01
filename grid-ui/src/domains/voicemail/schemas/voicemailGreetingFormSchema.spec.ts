import { describe, expect, it } from 'vitest'
import { voicemailGreetingFormSchema } from './voicemailGreetingFormSchema'

describe('voicemailGreetingFormSchema', () => {
  it('trims a valid greeting payload', () => {
    const audio = new File(['WAVE'], 'greeting.wav', { type: 'audio/wav' })

    expect(voicemailGreetingFormSchema.parse({ name: '  Reception  ', audio })).toEqual({
      name: 'Reception',
      audio,
    })
  })

  it('rejects missing, unsupported, empty, and oversized audio', () => {
    expect(voicemailGreetingFormSchema.safeParse({ name: '', audio: null }).success).toBe(false)
    expect(
      voicemailGreetingFormSchema.safeParse({
        name: '',
        audio: new File(['svg'], 'audio.svg', { type: 'image/svg+xml' }),
      }).success,
    ).toBe(false)
    expect(
      voicemailGreetingFormSchema.safeParse({
        name: '',
        audio: new File([], 'empty.wav', { type: 'audio/wav' }),
      }).success,
    ).toBe(false)
    expect(
      voicemailGreetingFormSchema.safeParse({
        name: '',
        audio: new File([new Uint8Array(10 * 1024 * 1024 + 1)], 'large.wav', {
          type: 'audio/wav',
        }),
      }).success,
    ).toBe(false)
  })
})
