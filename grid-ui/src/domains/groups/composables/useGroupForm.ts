import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { groupFormSchema } from '../schemas/groupFormSchema'
import type { Group, GroupInput } from '../types/group'

export function useGroupForm(record: Group | null) {
  const form = reactive<GroupInput>({
    name: record?.name ?? '',
    music_on_hold_media_id: record?.music_on_hold_media?.id ?? null,
    members:
      record?.members?.flatMap((member) =>
        member.target ? [{ type: member.type, id: member.target.id, weight: member.weight }] : [],
      ) ?? [],
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<GroupInput> {
    const result = validateForm(groupFormSchema, {
      ...form,
      name: form.name.trim(),
      members: form.members.map((member) => ({ ...member })),
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
