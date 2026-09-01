import { z } from 'zod'

export const accountAdministrationCapabilitiesSchema = z
  .object({
    account_creation_available: z.literal(false),
    account_move_available: z.literal(false),
    account_deletion_available: z.literal(false),
    limit_mutations_available: z.literal(false),
    service_plan_mutations_available: z.literal(false),
    service_override_mutations_available: z.literal(false),
    top_up_available: z.literal(false),
    switch_service_synchronization_available: z.literal(false),
    switch_service_reconciliation_available: z.literal(false),
  })
  .strict()

export type AccountAdministrationCapabilities = z.infer<
  typeof accountAdministrationCapabilitiesSchema
>
