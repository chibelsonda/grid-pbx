import { describe, expect, it } from 'vitest'
import { conferencePlaybackSchema } from './conferencePlaybackSchema'

const mediaId = '11111111-1111-4111-8111-111111111111'

describe('conferencePlaybackSchema', () => {
  it('accepts confirmed room or opaque-participant playback using a public media UUID', () => {
    expect(
      conferencePlaybackSchema.safeParse({
        media_id: mediaId,
        participant_id: null,
        confirmation: true,
      }).success,
    ).toBe(true)
    expect(
      conferencePlaybackSchema.safeParse({
        media_id: mediaId,
        participant_id: 'opaque-participant-handle',
        confirmation: true,
      }).success,
    ).toBe(true)
  })

  it('rejects raw media references, missing confirmation, and unknown fields', () => {
    expect(
      conferencePlaybackSchema.safeParse({
        media_id: 'https://example.invalid/audio.mp3',
        participant_id: null,
        confirmation: true,
      }).success,
    ).toBe(false)
    expect(
      conferencePlaybackSchema.safeParse({ media_id: mediaId, participant_id: null }).success,
    ).toBe(false)
    expect(
      conferencePlaybackSchema.safeParse({
        media_id: mediaId,
        participant_id: null,
        confirmation: true,
        media_url: 'https://example.invalid/audio.mp3',
      }).success,
    ).toBe(false)
  })
})
