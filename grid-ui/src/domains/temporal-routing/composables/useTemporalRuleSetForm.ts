import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { temporalRuleSetFormSchema } from '../schemas/temporalRuleFormSchema'
import type { TemporalRuleSet, TemporalRuleSetInput } from '../types/temporalRouting'

export function useTemporalRuleSetForm(record: TemporalRuleSet | null) {
  const form = reactive<TemporalRuleSetInput>({
    name: record?.name ?? '',
    rule_ids:
      record?.rules?.flatMap((membership) => (membership.rule ? [membership.rule.id] : [])) ?? [],
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<TemporalRuleSetInput> {
    const result = validateForm(temporalRuleSetFormSchema, {
      name: form.name.trim(),
      rule_ids: [...form.rule_ids],
    })
    validationErrors.value = result.errors

    return result
  }

  function moveRule(ruleId: string, offset: -1 | 1): void {
    const from = form.rule_ids.indexOf(ruleId)
    const to = from + offset

    if (from < 0 || to < 0 || to >= form.rule_ids.length) return

    const selected = form.rule_ids[from]
    const adjacent = form.rule_ids[to]
    if (selected === undefined || adjacent === undefined) return

    form.rule_ids[from] = adjacent
    form.rule_ids[to] = selected
  }

  return { form, moveRule, validate, validationErrors }
}
