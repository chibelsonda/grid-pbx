import { ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { mediaAudioSchema } from '../schemas/mediaFormSchema'

export function useMediaAudioForm() {
  const audio = ref<File | null>(null)
  const validationErrors = ref<FormErrors>({})

  watch(audio, () => (validationErrors.value = {}))

  function validate(): FormValidationResult<{ audio: File }> {
    const result = validateForm(mediaAudioSchema, { audio: audio.value })
    validationErrors.value = result.errors

    return result
  }

  return { audio, validate, validationErrors }
}
