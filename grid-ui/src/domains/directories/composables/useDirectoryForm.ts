import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { directoryFormSchema } from '../schemas/directoryFormSchema'
import type { Directory, DirectoryInput } from '../types/directory'

type DirectoryFormState = Omit<DirectoryInput, 'flags'> & { flags: string }

function parseFlags(value: string): string[] {
  return value
    .split(/[\n,]/)
    .map((flag) => flag.trim())
    .filter(Boolean)
}

export function useDirectoryForm(record: Directory | null) {
  const form = reactive<DirectoryFormState>({
    name: record?.name ?? '',
    confirm_match: record?.confirm_match ?? true,
    min_dtmf: record?.min_dtmf ?? 3,
    max_dtmf: record?.max_dtmf ?? 0,
    sort_by: record?.sort_by ?? 'last_name',
    flags: record?.flags.join('\n') ?? '',
    member_ids:
      record?.members?.flatMap((member) => (member.extension ? [member.extension.id] : [])) ?? [],
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<DirectoryInput> {
    const result = validateForm(directoryFormSchema, {
      ...form,
      name: form.name.trim(),
      flags: parseFlags(form.flags),
      member_ids: [...form.member_ids],
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
