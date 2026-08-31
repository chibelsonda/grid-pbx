import { z } from 'zod'

const disabledFaxOperationSchema = z
  .object({
    switch_supported: z.literal(true),
    enabled: z.literal(false),
    reason: z.string().trim().min(1),
  })
  .strict()

export const faxOperationCapabilitiesSchema = z
  .object({
    send: disabledFaxOperationSchema,
    forward: disabledFaxOperationSchema,
    resubmit: disabledFaxOperationSchema,
    delete_message: disabledFaxOperationSchema,
    delete_document: disabledFaxOperationSchema,
  })
  .strict()
