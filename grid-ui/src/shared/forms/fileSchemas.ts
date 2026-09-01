import { z } from 'zod'

const acceptedAudioTypes = new Set([
  'audio/mpeg',
  'audio/mp3',
  'audio/wav',
  'audio/x-wav',
  'audio/ogg',
])
const acceptedAudioExtensions = new Set(['mp3', 'wav', 'ogg'])

function isFile(value: unknown): value is File {
  return typeof File !== 'undefined' && value instanceof File
}

export function audioUploadFileSchema(
  maximumBytes: number,
  maximumSizeMessage: string,
  requireKnownExtension = true,
) {
  return z
    .custom<File>(isFile, 'Select an MP3, WAV, or OGG audio file.')
    .refine((file) => file.size > 0, 'The audio file cannot be empty.')
    .refine((file) => file.size <= maximumBytes, maximumSizeMessage)
    .refine((file) => {
      if (!acceptedAudioTypes.has(file.type.toLowerCase())) return false
      if (!requireKnownExtension) return true

      return acceptedAudioExtensions.has(file.name.split('.').pop()?.toLowerCase() ?? '')
    }, 'Select an MP3, WAV, or OGG audio file.')
}
