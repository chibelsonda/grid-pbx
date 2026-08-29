import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { agentStatusFormSchema } from '../schemas/queueFormSchema'
import type { AgentStatusInput } from '../types/queue'

export function useAgentStatusForm() {
  const form = reactive<AgentStatusInput>({ status: 'login', pause_timeout: 300 })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<AgentStatusInput> {
    const result = validateForm(agentStatusFormSchema, {
      status: form.status,
      pause_timeout: form.status === 'pause' ? (form.pause_timeout ?? null) : null,
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
