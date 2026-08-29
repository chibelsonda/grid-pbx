import { reactive, ref, watch, type MaybeRefOrGetter } from 'vue'
import { toValue } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { createCallflowFormSchema } from '../schemas/callflowFormSchema'
import type {
  Callflow,
  CallflowDestinationType,
  CallflowEditor,
  CallflowMenuBranchInput,
  CallflowUpdate,
} from '../types/callRouting'

type CallflowFormState = {
  name: string
  destination_type: CallflowDestinationType
  destination_id: string
  phone_number_ids: string[]
  manage_fallback: boolean
  fallback_enabled: boolean
  fallback_destination_type: CallflowDestinationType
  fallback_destination_id: string
  manage_menu_branches: boolean
  menu_branches: CallflowMenuBranchInput[]
  manage_temporal_match: boolean
  temporal_match_enabled: boolean
  temporal_match_destination_type: CallflowDestinationType
  temporal_match_destination_id: string
}

export function useCallflowForm(
  recordSource: MaybeRefOrGetter<Callflow | null>,
  editorSource: MaybeRefOrGetter<CallflowEditor | null>,
) {
  const form = reactive<CallflowFormState>({
    name: '',
    destination_type: 'extension',
    destination_id: '',
    phone_number_ids: [],
    manage_fallback: true,
    fallback_enabled: false,
    fallback_destination_type: 'voicemail',
    fallback_destination_id: '',
    manage_menu_branches: true,
    menu_branches: [],
    manage_temporal_match: true,
    temporal_match_enabled: true,
    temporal_match_destination_type: 'extension',
    temporal_match_destination_id: '',
  })
  const validationErrors = ref<FormErrors>({})

  function initialize(): void {
    const record = toValue(recordSource)
    const editor = toValue(editorSource)

    if (!editor) return

    form.name = record?.name ?? record?.numbers[0] ?? ''
    const currentType = record?.flow?.target?.type
    const firstAvailable = editor.destination_types.find(
      ({ value }) => editor.destinations[value].length > 0,
    )?.value
    form.destination_type = currentType ?? firstAvailable ?? 'extension'
    form.destination_id =
      currentType &&
      editor.destinations[currentType].some(({ id }) => id === record?.flow?.target?.id)
        ? (record?.flow?.target?.id ?? '')
        : (editor.destinations[form.destination_type][0]?.id ?? '')
    form.phone_number_ids = editor.phone_numbers
      .filter(({ selected }) => selected)
      .map(({ id }) => id)
    const fallbackType = editor.fallback.target?.type
    const firstFallbackType = editor.destination_types.find(
      ({ value }) => editor.destinations[value].length > 0,
    )?.value
    form.manage_fallback = editor.fallback.editable
    form.fallback_enabled = editor.fallback.target !== null
    form.fallback_destination_type = fallbackType ?? firstFallbackType ?? 'voicemail'
    form.fallback_destination_id =
      fallbackType &&
      editor.destinations[fallbackType].some(({ id }) => id === editor.fallback.target?.id)
        ? (editor.fallback.target?.id ?? '')
        : (editor.destinations[form.fallback_destination_type][0]?.id ?? '')
    form.manage_menu_branches = editor.menu_branches.editable
    form.menu_branches = editor.menu_branches.branches.flatMap((branch) =>
      branch.editable && branch.target
        ? [
            {
              key: branch.key,
              destination_type: branch.target.type,
              destination_id: branch.target.id,
            },
          ]
        : [],
    )
    const temporalMatchType = editor.temporal_match.target?.type
    const firstTemporalMatchType = editor.destination_types.find(
      ({ value }) => editor.destinations[value].length > 0,
    )?.value
    form.manage_temporal_match = editor.temporal_match.editable
    form.temporal_match_enabled = record === null || editor.temporal_match.target !== null
    form.temporal_match_destination_type =
      temporalMatchType ?? firstTemporalMatchType ?? 'extension'
    form.temporal_match_destination_id =
      temporalMatchType &&
      editor.destinations[temporalMatchType].some(
        ({ id }) => id === editor.temporal_match.target?.id,
      )
        ? (editor.temporal_match.target?.id ?? '')
        : (editor.destinations[form.temporal_match_destination_type][0]?.id ?? '')
    validationErrors.value = {}
  }

  watch([() => toValue(recordSource), () => toValue(editorSource)], initialize, {
    immediate: true,
  })
  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<CallflowUpdate> {
    const editor = toValue(editorSource)

    if (!editor) {
      const result: FormValidationResult<CallflowUpdate> = {
        success: false,
        data: null,
        errors: { _form: ['Routing options are not available yet.'] },
      }
      validationErrors.value = result.errors

      return result
    }

    const result = validateForm(createCallflowFormSchema(editor), {
      ...form,
      name: form.name.trim(),
      destination_type: form.destination_type as CallflowDestinationType,
      phone_number_ids: [...form.phone_number_ids],
    })
    validationErrors.value = result.errors

    return result
  }

  return { form, validate, validationErrors }
}
