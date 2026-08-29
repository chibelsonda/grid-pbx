import { z } from 'zod'

const nullableUuid = z.uuid().nullable()
const announcementMediaFields = [
  'announcement_in_the_queue_media_id',
  'announcement_increase_in_call_volume_media_id',
  'announcement_estimated_wait_time_media_id',
  'announcement_position_media_id',
] as const

export const queueFormSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a queue name.').max(128),
    strategy: z.enum(['round_robin', 'most_idle']),
    agent_ring_timeout: z.number().int().min(1).max(300),
    agent_wrapup_time: z.number().int().min(0).max(3600),
    connection_timeout: z.number().int().min(0).max(86400),
    max_queue_size: z.number().int().min(0).max(10000),
    ring_simultaneously: z.number().int().min(1).max(100),
    enter_when_empty: z.boolean(),
    record_caller: z.boolean(),
    caller_exit_key: z.enum(['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#']),
    music_on_hold_media_id: nullableUuid,
    announce_media_id: nullableUuid,
    max_priority: z.number().int().min(0).max(255).nullable(),
    announcements_enabled: z.boolean(),
    announcement_interval: z.number().int().min(15).max(86400),
    position_announcements_enabled: z.boolean(),
    wait_time_announcements_enabled: z.boolean(),
    announcement_in_the_queue_media_id: nullableUuid,
    announcement_increase_in_call_volume_media_id: nullableUuid,
    announcement_estimated_wait_time_media_id: nullableUuid,
    announcement_position_media_id: nullableUuid,
    agent_ids: z.array(z.uuid()).refine((ids) => new Set(ids).size === ids.length, 'Select each agent once.'),
  })
  .strict()
  .superRefine((value, context) => {
    const selected = announcementMediaFields.filter((field) => value[field] !== null).length

    if (selected > 0 && selected < announcementMediaFields.length) {
      context.addIssue({
        code: 'custom',
        path: ['announcement_media'],
        message: 'Select all four custom announcement prompts or leave all four on the Switch defaults.',
      })
    }
  })

export const agentStatusFormSchema = z
  .object({
    status: z.enum(['login', 'logout', 'pause', 'resume', 'end_wrapup']),
    pause_timeout: z.number().int().min(0).max(86400).nullable(),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.status === 'pause' && value.pause_timeout === null) {
      context.addIssue({
        code: 'custom',
        path: ['pause_timeout'],
        message: 'Enter the pause timeout in seconds.',
      })
    }
  })

