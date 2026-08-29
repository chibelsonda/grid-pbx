import { ref, watch } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { recordingFilterSchema } from '../schemas/recordingFilterSchema'
import type { RecordingFilters } from '../types/recording'

export function useRecordingFilters(filters: () => RecordingFilters) {
  const validationErrors = ref<FormErrors>({})

  watch(filters, () => (validationErrors.value = {}), { deep: true })

  function validate(): boolean {
    const result = validateForm(recordingFilterSchema, filters())
    validationErrors.value = result.errors
    return result.success
  }

  return { validate, validationErrors }
}
