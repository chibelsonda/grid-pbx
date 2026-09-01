import { z } from 'zod'

export const operationalStatusSchema = z
  .object({
    observed_at: z.iso.datetime({ offset: true }),
    presence: z
      .object({
        subscription_diagnostics_available: z.boolean(),
        live_status_available: z.literal(false),
        commands_available: z.literal(false),
      })
      .strict(),
    parking: z
      .object({
        summary_available: z.boolean(),
        active_call_count: z.number().int().nonnegative().nullable(),
        actions_available: z.literal(false),
      })
      .strict(),
    webhooks: z
      .object({
        event_catalog_available: z.boolean(),
        available_event_count: z.number().int().nonnegative().nullable(),
        configuration_summary_available: z.boolean(),
        configured_count: z.number().int().nonnegative().nullable(),
        enabled_count: z.number().int().nonnegative().nullable(),
        configuration_mutations_available: z.literal(false),
        delivery_history_available: z.literal(false),
      })
      .strict(),
    messaging: z
      .object({
        sms_inventory_available: z.boolean(),
        mms_inventory_available: z.boolean(),
        message_content_available: z.literal(false),
        sending_available: z.literal(false),
      })
      .strict(),
    number_porting: z
      .object({
        inventory_available: z.boolean(),
        request_details_available: z.literal(false),
        documents_available: z.literal(false),
        workflow_mutations_available: z.literal(false),
      })
      .strict(),
    number_management: z
      .object({
        carrier_configuration_available: z.boolean(),
        search_available: z.literal(false),
        purchase_available: z.literal(false),
        reservation_available: z.literal(false),
        release_available: z.literal(false),
      })
      .strict(),
    connectivity: z
      .object({
        summary_available: z.boolean(),
        configured_pbx_count: z.number().int().nonnegative().nullable(),
        local_resource_summary_available: z.boolean(),
        local_resource_count: z.number().int().nonnegative().nullable(),
        configuration_mutations_available: z.literal(false),
        resource_mutations_available: z.literal(false),
        selector_mutations_available: z.literal(false),
        limit_mutations_available: z.literal(false),
        failover_mutations_available: z.literal(false),
      })
      .strict(),
  })
  .strict()

export type OperationalStatus = z.infer<typeof operationalStatusSchema>
