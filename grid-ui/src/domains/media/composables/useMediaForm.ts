import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { mediaCreateSchema, mediaMetadataSchema } from '../schemas/mediaFormSchema'
import type { Media, MediaCreate, MediaUpdate } from '../types/media'

type MediaFormState = {
  name: string
  description: string
  language: string
  streamable: boolean
  audio: File | null
}

function nullable(value: string): string | null {
  const normalized = value.trim()
  return normalized === '' ? null : normalized
}

export function useMediaForm(mode: 'create' | 'edit', record: Media | null) {
  const form = reactive<MediaFormState>({
    name: record?.name ?? '',
    description: record?.description ?? '',
    language: record?.language ?? 'en-us',
    streamable: record?.streamable ?? true,
    audio: null,
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<MediaCreate | MediaUpdate> {
    const input = {
      name: form.name.trim(),
      description: nullable(form.description),
      language: nullable(form.language),
      streamable: form.streamable,
      ...(mode === 'create' ? { audio: form.audio } : {}),
    }
    const result = validateForm(mode === 'create' ? mediaCreateSchema : mediaMetadataSchema, input)
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
