import { z } from 'zod'

export const webhookIntegrationProfileSchema = z.object({
  name: z.string().trim().min(1, 'Enter a profile name.').max(100),
  is_active: z.boolean(),
  uri: z
    .string()
    .trim()
    .max(2048, 'Use a URL with 2,048 characters or fewer.')
    .url('Enter a valid URL.')
    .refine((value) => value.startsWith('https://'), 'Use an HTTPS URL.'),
  methods: z
    .array(z.enum(['get', 'post']))
    .min(1, 'Select at least one request method.')
    .max(2)
    .refine((methods) => new Set(methods).size === methods.length, 'Select each method once.'),
  max_retries: z.coerce
    .number()
    .int('Use a whole number of attempts.')
    .min(1, 'Allow at least one attempt.')
    .max(5, 'Allow no more than five attempts.'),
})

export type ValidWebhookIntegrationProfileForm = z.output<typeof webhookIntegrationProfileSchema>
