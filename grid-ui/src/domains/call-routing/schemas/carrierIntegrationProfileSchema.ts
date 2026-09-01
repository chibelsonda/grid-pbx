import { z } from 'zod'

export const carrierIntegrationProfileSchema = z
  .object({
    integration_type: z.enum(['global_carrier', 'account_carrier']),
    name: z.string().trim().min(1, 'Enter a profile name.').max(100),
    is_active: z.boolean(),
    route_scope: z.enum(['global', 'account', 'reseller']),
  })
  .strict()
  .superRefine((value, context) => {
    const validScope =
      (value.integration_type === 'global_carrier' && value.route_scope === 'global') ||
      (value.integration_type === 'account_carrier' && value.route_scope !== 'global')

    if (!validScope) {
      context.addIssue({
        code: 'custom',
        path: ['route_scope'],
        message: 'Select a routing scope supported by this carrier profile.',
      })
    }
  })

export type ValidCarrierIntegrationProfileForm = z.infer<
  typeof carrierIntegrationProfileSchema
>
