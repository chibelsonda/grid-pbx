import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { callerIdListFormSchema } from '../schemas/callerIdListFormSchema'
import type { CallerIdList, CallerIdListEntryInput, CallerIdListInput } from '../types/callerIdList'

export type CallerIdEntryMode = 'number' | 'pattern'

export type CallerIdListEntryForm = CallerIdListEntryInput & { mode: CallerIdEntryMode }

function newEntry(mode: CallerIdEntryMode = 'number'): CallerIdListEntryForm {
  return {
    id: null,
    display_name: null,
    number: mode === 'number' ? '' : null,
    pattern: mode === 'pattern' ? '' : null,
    mode,
  }
}

export function useCallerIdListForm(record: CallerIdList | null) {
  const form = reactive({
    name: record?.name ?? '',
    description: record?.description ?? '',
    organization: record?.organization ?? '',
    entries: (record?.entries ?? []).map<CallerIdListEntryForm>((entry) => ({
      ...entry,
      mode: entry.pattern !== null ? 'pattern' : 'number',
    })),
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function addEntry(mode: CallerIdEntryMode): void {
    form.entries.push(newEntry(mode))
  }

  function removeEntry(index: number): void {
    form.entries.splice(index, 1)
  }

  function setMode(index: number, mode: CallerIdEntryMode): void {
    const entry = form.entries[index]
    if (!entry) return
    entry.mode = mode
    entry.number = mode === 'number' ? '' : null
    entry.pattern = mode === 'pattern' ? '' : null
  }

  function validate(): FormValidationResult<CallerIdListInput> {
    const result = validateForm(callerIdListFormSchema, {
      name: form.name,
      description: form.description,
      organization: form.organization,
      entries: form.entries.map(({ mode: _mode, ...entry }) => entry),
    })
    validationErrors.value = result.errors

    return result
  }

  return { addEntry, form, removeEntry, setMode, validate, validationErrors }
}
