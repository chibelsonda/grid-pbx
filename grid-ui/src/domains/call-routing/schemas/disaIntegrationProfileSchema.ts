import { z } from 'zod'

export const disaIntegrationProfileSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a policy name.').max(100),
    is_active: z.boolean(),
    pin: z.string().regex(/^\d{8,12}$/, 'Use an 8–12 digit numeric PIN.'),
    pin_confirmation: z.string(),
    retries: z.coerce.number().int().min(1).max(3),
    interdigit_ms: z.coerce.number().int().min(1000).max(5000),
    max_digits: z.coerce.number().int().min(3).max(15),
    preconnect_audio: z.enum(['dialtone', 'ringing']),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.pin !== value.pin_confirmation) {
      context.addIssue({
        code: 'custom',
        path: ['pin_confirmation'],
        message: 'PIN confirmation does not match.',
      })
    }
  })

export type DisaIntegrationProfileForm = z.input<typeof disaIntegrationProfileSchema>
export type ValidDisaIntegrationProfileForm = z.output<typeof disaIntegrationProfileSchema>
