import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { menuFormSchema } from '../schemas/menuFormSchema'
import type { Menu, MenuInput } from '../types/menu'

const nullable = (value: string | null): string | null => {
  const normalized = value?.trim() ?? ''

  return normalized === '' ? null : normalized
}

export function useMenuForm(record: Menu | null) {
  const form = reactive<MenuInput>({
    name: record?.name ?? '',
    timeout: record?.timeout ?? 10_000,
    interdigit_timeout: record?.interdigit_timeout ?? 2_000,
    max_extension_length: record?.max_extension_length ?? 4,
    retries: record?.retries ?? 3,
    hunt: record?.hunt ?? true,
    allow_record_from_offnet: record?.allow_record_from_offnet ?? false,
    suppress_media: record?.suppress_media ?? false,
    record_pin: null,
    hunt_allow: record?.hunt_allow ?? null,
    hunt_deny: record?.hunt_deny ?? null,
    greeting_media_id: record?.greeting_media?.id ?? null,
    invalid_media_enabled: record?.invalid_media_enabled ?? true,
    invalid_media_id: record?.invalid_media?.id ?? null,
    transfer_media_enabled: record?.transfer_media_enabled ?? true,
    transfer_media_id: record?.transfer_media?.id ?? null,
    exit_media_enabled: record?.exit_media_enabled ?? true,
    exit_media_id: record?.exit_media?.id ?? null,
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<MenuInput> {
    const result = validateForm(menuFormSchema, {
      ...form,
      name: form.name.trim(),
      record_pin: nullable(form.record_pin),
      hunt_allow: nullable(form.hunt_allow),
      hunt_deny: nullable(form.hunt_deny),
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}

