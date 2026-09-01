import { z } from 'zod'

const queueReferenceSchema = z.object({
  id: z.string().uuid(),
  name: z.string().trim().min(1),
})

export const agentQueueMembershipSchema = z.object({
  agent: z.object({
    id: z.string().uuid(),
    name: z.string().trim().min(1),
    extension: z.string().nullable(),
  }),
  assigned_queues: z.array(queueReferenceSchema),
  available_queues: z.array(queueReferenceSchema),
  unresolved_queues: z.number().int().nonnegative(),
  agent_active: z.boolean(),
  observed_at: z.string().datetime({ offset: true }),
})

export const agentQueueMembershipInputSchema = z
  .object({
    action: z.enum(['login', 'logout']),
    queue_id: z.string().uuid('Select a projected Queue.'),
    confirm_last_queue: z.boolean().optional(),
  })
  .strict()

export type AgentQueueMembership = z.infer<typeof agentQueueMembershipSchema>
export type AgentQueueMembershipInput = z.infer<typeof agentQueueMembershipInputSchema>
