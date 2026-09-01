import { z } from 'zod'

export const queueOptionsSchema = z.object({
  agents: z.array(z.object({ id: z.uuid(), label: z.string(), detail: z.string().nullable() })),
  media: z.array(z.object({ id: z.uuid(), label: z.string(), detail: z.string().nullable() })),
  capabilities: z.object({
    configuration_available: z.boolean(),
    live_agent_controls_available: z.boolean(),
    agent_statistics_available: z.boolean(),
    statistics_available: z.boolean(),
  }),
})

export type QueueOption = z.infer<typeof queueOptionsSchema>['agents'][number]
export type QueueOptions = z.infer<typeof queueOptionsSchema>
