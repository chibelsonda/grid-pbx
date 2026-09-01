import { z } from 'zod'

export const agentAvailabilityStatusSchema = z.enum([
  'ready',
  'logged_in',
  'logged_out',
  'connecting',
  'connected',
  'wrapup',
  'paused',
  'outbound',
  'unknown',
])

export const agentAvailabilitySchema = z.object({
  observed_at: z.string().datetime({ offset: true }),
  agents: z.array(
    z.object({
      id: z.string().uuid(),
      status: agentAvailabilityStatusSchema,
      changed_at: z.number().int().nonnegative(),
    }),
  ),
  unresolved_agents: z.number().int().nonnegative(),
})

export type AgentAvailability = z.infer<typeof agentAvailabilitySchema>
export type AgentAvailabilityStatus = z.infer<typeof agentAvailabilityStatusSchema>
