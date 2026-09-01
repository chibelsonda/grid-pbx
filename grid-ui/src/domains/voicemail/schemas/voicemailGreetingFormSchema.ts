import { z } from 'zod'
import { audioUploadFileSchema } from '@/shared/forms/fileSchemas'

export const voicemailGreetingFormSchema = z.object({
  name: z.string().trim().max(128, 'The display name may not exceed 128 characters.'),
  audio: audioUploadFileSchema(10 * 1024 * 1024, 'Greeting audio must be 10 MB or smaller.', false),
})
