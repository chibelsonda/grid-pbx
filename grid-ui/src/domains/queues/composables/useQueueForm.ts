import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { queueFormSchema } from '../schemas/queueFormSchema'
import type { Queue, QueueInput } from '../types/queue'

export function useQueueForm(record: Queue | null) {
  const form = reactive<QueueInput>({
    name: record?.name ?? '',
    strategy: record?.strategy ?? 'round_robin',
    agent_ring_timeout: record?.agent_ring_timeout ?? 15,
    agent_wrapup_time: record?.agent_wrapup_time ?? 0,
    connection_timeout: record?.connection_timeout ?? 3600,
    max_queue_size: record?.max_queue_size ?? 0,
    ring_simultaneously: record?.ring_simultaneously ?? 1,
    enter_when_empty: record?.enter_when_empty ?? true,
    record_caller: record?.record_caller ?? false,
    caller_exit_key: record?.caller_exit_key ?? '#',
    music_on_hold_media_id: record?.music_on_hold_media?.id ?? null,
    announce_media_id: record?.announce_media?.id ?? null,
    max_priority: record?.max_priority ?? null,
    announcements_enabled: record?.announcements.enabled ?? false,
    announcement_interval: record?.announcements.interval ?? 30,
    position_announcements_enabled: record?.announcements.position_announcements_enabled ?? false,
    wait_time_announcements_enabled: record?.announcements.wait_time_announcements_enabled ?? false,
    announcement_in_the_queue_media_id: record?.announcements.media.in_the_queue?.id ?? null,
    announcement_increase_in_call_volume_media_id:
      record?.announcements.media.increase_in_call_volume?.id ?? null,
    announcement_estimated_wait_time_media_id:
      record?.announcements.media.the_estimated_wait_time_is?.id ?? null,
    announcement_position_media_id: record?.announcements.media.you_are_at_position?.id ?? null,
    agent_ids:
      record?.agents?.flatMap((membership) => (membership.agent ? [membership.agent.id] : [])) ??
      [],
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<QueueInput> {
    const result = validateForm(queueFormSchema, {
      ...form,
      name: form.name.trim(),
      max_priority: typeof form.max_priority === 'number' ? form.max_priority : null,
      agent_ids: [...form.agent_ids],
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
