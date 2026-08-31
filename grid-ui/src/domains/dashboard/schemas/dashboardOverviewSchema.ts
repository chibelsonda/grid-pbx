import { z } from 'zod'

const nonnegativeInteger = z.number().int().nonnegative()
const timestampSchema = z.iso.datetime({ offset: true }).nullable()

const inventorySummarySchema = z
  .object({
    extensions: z
      .object({
        total: nonnegativeInteger,
        enabled: nonnegativeInteger,
        disabled: nonnegativeInteger,
      })
      .strict(),
    devices: z
      .object({
        total: nonnegativeInteger,
        enabled: nonnegativeInteger,
        disabled: nonnegativeInteger,
        registered: nonnegativeInteger,
        unregistered: nonnegativeInteger,
        enabled_unregistered: nonnegativeInteger,
        unknown_registration: nonnegativeInteger,
      })
      .strict(),
    phone_numbers: z
      .object({
        total: nonnegativeInteger,
        assigned: nonnegativeInteger,
        unassigned: nonnegativeInteger,
      })
      .strict(),
    callflows: z
      .object({
        total: nonnegativeInteger,
        healthy: nonnegativeInteger,
        attention: nonnegativeInteger,
      })
      .strict(),
    voicemail: z.object({ boxes: nonnegativeInteger, new_messages: nonnegativeInteger }).strict(),
    queues: z.object({ total: nonnegativeInteger }).strict(),
  })
  .strict()

export const dashboardOverviewSchema = z
  .object({
    generated_at: z.iso.datetime({ offset: true }),
    data_as_of: timestampSchema,
    is_stale: z.boolean(),
    account: z
      .object({
        id: z.uuid(),
        name: z.string(),
        timezone: z.string(),
        sync_status: z.string().nullable(),
        last_synced_at: timestampSchema,
      })
      .strict(),
    synchronization: z
      .object({
        status: z.enum(['healthy', 'syncing', 'attention', 'error', 'not_started']),
        last_successful_at: timestampSchema,
        active_runs: nonnegativeInteger,
        checkpoints: z
          .object({
            total: nonnegativeInteger,
            healthy: nonnegativeInteger,
            syncing: nonnegativeInteger,
            stale: nonnegativeInteger,
            error: nonnegativeInteger,
          })
          .strict(),
        resources_requiring_attention: z.array(
          z
            .object({
              resource: z.string(),
              status: z.enum(['stale', 'error']),
              last_successful_at: timestampSchema,
            })
            .strict(),
        ),
        recent_runs: z.array(
          z
            .object({
              id: z.uuid(),
              resource: z.string(),
              status: z.enum(['queued', 'running', 'succeeded', 'failed']),
              processed_count: nonnegativeInteger,
              started_at: timestampSchema,
              finished_at: timestampSchema,
            })
            .strict(),
        ),
      })
      .strict(),
    inventory: inventorySummarySchema,
    calls_today: z
      .object({
        total: nonnegativeInteger,
        inbound: nonnegativeInteger,
        outbound: nonnegativeInteger,
        answered: nonnegativeInteger,
        missed: nonnegativeInteger,
        answer_rate: z.number().min(0).max(100),
        average_duration_seconds: nonnegativeInteger,
      })
      .strict(),
    attention: z
      .object({
        total: nonnegativeInteger,
        items: z.array(
          z
            .object({
              code: z.string(),
              severity: z.enum(['danger', 'warning', 'info']),
              label: z.string(),
              count: nonnegativeInteger,
              message: z.string(),
              guidance: z.string(),
              resource: z.string(),
            })
            .strict(),
        ),
      })
      .strict(),
  })
  .strict()

export type DashboardOverview = z.infer<typeof dashboardOverviewSchema>
export type DashboardAttentionItem = DashboardOverview['attention']['items'][number]
