import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { conferenceFormSchema } from '../schemas/conferenceFormSchema'
import type { Conference, ConferenceInput } from '../types/conference'

const nullable = (value: string | null): string | null => {
  const normalized = value?.trim() ?? ''

  return normalized === '' ? null : normalized
}

const list = (value: string): string[] => [
  ...new Set(
    value
      .split(/[\s,]+/)
      .map((item) => item.trim())
      .filter(Boolean),
  ),
]

export function useConferenceForm(record: Conference | null) {
  const numbers = reactive({
    conference: record?.conference_numbers.join(', ') ?? '',
    member: record?.member_numbers.join(', ') ?? '',
    moderator: record?.moderator_numbers.join(', ') ?? '',
  })
  const pins = reactive({ member: '', moderator: '' })
  const form = reactive<ConferenceInput>({
    name: record?.name ?? '',
    owner_id: record?.owner?.id ?? null,
    conference_numbers: [],
    member_numbers: [],
    moderator_numbers: [],
    member_pins: [],
    clear_member_pin: false,
    moderator_pins: [],
    clear_moderator_pin: false,
    member_join_muted: record?.member_join_muted ?? true,
    member_join_deaf: record?.member_join_deaf ?? false,
    member_play_entry_prompt: record?.member_play_entry_prompt ?? false,
    moderator_join_muted: record?.moderator_join_muted ?? false,
    moderator_join_deaf: record?.moderator_join_deaf ?? false,
    max_participants: record?.max_participants ?? null,
    language: record?.language ?? null,
    profile_name: record?.profile_name ?? null,
    caller_controls: record?.caller_controls ?? null,
    moderator_controls: record?.moderator_controls ?? null,
    play_name: record?.play_name ?? false,
    play_welcome: record?.play_welcome ?? true,
    require_moderator: record?.require_moderator ?? false,
    wait_for_moderator: record?.wait_for_moderator ?? false,
    max_members_media_id: record?.max_members_media?.id ?? null,
    play_entry_tone_mode: record?.entry_tone.mode ?? 'enabled',
    play_entry_tone_media_id: record?.entry_tone.media?.id ?? null,
    play_exit_tone_mode: record?.exit_tone.mode ?? 'enabled',
    play_exit_tone_media_id: record?.exit_tone.media?.id ?? null,
  })
  const validationErrors = ref<FormErrors>({})

  watch([form, numbers, pins], () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<ConferenceInput> {
    const result = validateForm(conferenceFormSchema, {
      ...form,
      name: form.name.trim(),
      conference_numbers: list(numbers.conference),
      member_numbers: list(numbers.member),
      moderator_numbers: list(numbers.moderator),
      member_pins: list(pins.member),
      moderator_pins: list(pins.moderator),
      max_participants:
        typeof form.max_participants === 'number' && Number.isFinite(form.max_participants)
          ? form.max_participants
          : null,
      language: nullable(form.language),
      profile_name: nullable(form.profile_name),
      caller_controls: nullable(form.caller_controls),
      moderator_controls: nullable(form.moderator_controls),
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, numbers, pins, validate, validationErrors }
}
