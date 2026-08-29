import { ref, watch } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { callDetailRecordFilterSchema } from '../schemas/callDetailRecordFilterSchema'
import type { CallDetailRecordFilters } from '../types/callDetailRecord'

export function useCallDetailRecordFilters(filters: () => CallDetailRecordFilters) {
  const validationErrors = ref<FormErrors>({})

  watch(filters, () => (validationErrors.value = {}), { deep: true })

  function validate(): boolean {
    const result = validateForm(callDetailRecordFilterSchema, filters())
    validationErrors.value = result.errors
    return result.success
  }

  return { validate, validationErrors }
}
