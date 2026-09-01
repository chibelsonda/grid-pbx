import { z } from 'zod'
import { audioUploadFileSchema } from '@/shared/forms/fileSchemas'

function nullableString(maximum: number) {
  return z.string().trim().max(maximum).nullable()
}

export const mediaMetadataSchema = z.object({
  name: z.string().trim().min(1, 'Enter a media name.').max(128),
  description: nullableString(128),
  language: nullableString(35),
  streamable: z.boolean(),
})

export const mediaAudioSchema = z.object({
  audio: audioUploadFileSchema(5 * 1024 * 1024, 'The audio file may not exceed 5 MB.'),
})

export const mediaCreateSchema = mediaMetadataSchema.extend(mediaAudioSchema.shape)

export const musicOnHoldFormSchema = z.object({
  media_id: z.uuid('Select valid account media.').nullable(),
})
