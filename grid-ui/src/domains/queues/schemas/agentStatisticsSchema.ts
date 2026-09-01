import { z } from 'zod'

const metricsSchema = z.object({
  total_calls: z.number().int().nonnegative(),
  answered_calls: z.number().int().nonnegative(),
  missed_calls: z.number().int().nonnegative(),
  answer_rate_percentage: z.number().min(0).max(100).nullable(),
})

export const agentStatisticsSchema = z.object({
  observed_at: z.string().datetime({ offset: true }),
  totals: metricsSchema,
  agents: z.array(
    metricsSchema.extend({
      id: z.string().uuid(),
      name: z.string(),
      extension: z.string().nullable(),
    }),
  ),
  unresolved_agents: z.number().int().nonnegative(),
})

export type AgentStatistics = z.infer<typeof agentStatisticsSchema>
export type AgentStatisticsMetrics = z.infer<typeof metricsSchema>
