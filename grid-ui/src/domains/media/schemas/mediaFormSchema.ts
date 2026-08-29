import { z } from 'zod'

const acceptedAudioTypes = new Set([
  'audio/mpeg',
  'audio/mp3',
  'audio/wav',
  'audio/x-wav',
  'audio/ogg',
])
const acceptedAudioExtensions = new Set(['mp3', 'wav', 'ogg'])

function nullableString(maximum: number) {
  return z.string().trim().max(maximum).nullable()
}

function isFile(value: unknown): value is File {
  return typeof File !== 'undefined' && value instanceof File
}

export const mediaMetadataSchema = z.object({
  name: z.string().trim().min(1, 'Enter a media name.').max(128),
  description: nullableString(128),
  language: nullableString(35),
  streamable: z.boolean(),
})

export const mediaAudioSchema = z.object({
  audio: z
    .custom<File>(isFile, 'Select an MP3, WAV, or OGG audio file.')
    .refine((file) => file.size > 0, 'The audio file cannot be empty.')
    .refine((file) => file.size <= 5 * 1024 * 1024, 'The audio file may not exceed 5 MB.')
    .refine((file) => {
      const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
      return (
        acceptedAudioTypes.has(file.type.toLowerCase()) && acceptedAudioExtensions.has(extension)
      )
    }, 'Select an MP3, WAV, or OGG audio file.'),
})

export const mediaCreateSchema = mediaMetadataSchema.extend(mediaAudioSchema.shape)
