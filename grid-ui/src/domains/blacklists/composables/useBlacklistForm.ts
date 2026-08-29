import { computed, reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { blacklistFormSchema } from '../schemas/blacklistFormSchema'
import type { Blacklist, BlacklistInput } from '../types/blacklist'

type BlacklistFormState = {
  name: string
  should_block_anonymous: boolean
  is_active: boolean
  numbersText: string
}

function parseNumbers(value: string): string[] {
  return value
    .split(/[\s,]+/)
    .map((number) => number.trim())
    .filter(Boolean)
}

export function useBlacklistForm(record: Blacklist | null) {
  const form = reactive<BlacklistFormState>({
    name: record?.name ?? '',
    should_block_anonymous: record?.should_block_anonymous ?? false,
    is_active: record?.is_active ?? false,
    numbersText: (record?.numbers ?? []).map((entry) => entry.number).join('\n'),
  })
  const validationErrors = ref<FormErrors>({})
  const enteredNumbers = computed(() => parseNumbers(form.numbersText))
  const uniqueNumbers = computed(() => [...new Set(enteredNumbers.value)])
  const invalidNumbers = computed(() =>
    uniqueNumbers.value.filter((number) => !/^\+[1-9]\d{6,14}$/.test(number)),
  )

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<BlacklistInput> {
    const result = validateForm(blacklistFormSchema, {
      name: form.name.trim(),
      should_block_anonymous: form.should_block_anonymous,
      is_active: form.is_active,
      numbers: uniqueNumbers.value,
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, invalidNumbers, uniqueNumbers, validate, validationErrors }
}
