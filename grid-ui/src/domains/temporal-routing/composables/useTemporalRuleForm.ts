import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors, type FormValidationResult } from '@/shared/forms/zod'
import { temporalRuleFormSchema } from '../schemas/temporalRuleFormSchema'
import type { TemporalRule, TemporalRuleInput } from '../types/temporalRouting'

const nullableNumber = (value: number | null): number | null =>
  typeof value === 'number' && Number.isFinite(value) ? value : null

const parseDays = (value: string): number[] =>
  value.trim() === '' ? [] : value.split(/[\s,]+/).map((day) => Number(day))

export function useTemporalRuleForm(record: TemporalRule | null) {
  const form = reactive<TemporalRuleInput>({
    name: record?.name ?? '',
    cycle: record?.cycle ?? 'weekly',
    interval: record?.interval ?? 1,
    start_date: record?.start_date ?? null,
    time_window_start: record?.time_window_start ?? 32_400,
    time_window_stop: record?.time_window_stop ?? 61_200,
    days: [...(record?.days ?? [])],
    weekdays: [...(record?.weekdays ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
    month: record?.month ?? null,
    ordinal: record?.ordinal ?? null,
  })
  const daysText = ref(form.days.join(', '))
  const validationErrors = ref<FormErrors>({})

  watch([form, daysText], () => (validationErrors.value = {}), { deep: true })

  function validate(): FormValidationResult<TemporalRuleInput> {
    const cycle = form.cycle
    const result = validateForm(temporalRuleFormSchema, {
      ...form,
      name: form.name.trim(),
      interval: nullableNumber(form.interval) ?? 0,
      start_date: form.start_date || null,
      time_window_start: nullableNumber(form.time_window_start),
      time_window_stop: nullableNumber(form.time_window_stop),
      days: ['monthly', 'yearly'].includes(cycle) ? parseDays(daysText.value) : [],
      weekdays: ['weekly', 'monthly', 'yearly'].includes(cycle) ? [...form.weekdays] : [],
      month: cycle === 'yearly' ? nullableNumber(form.month) : null,
      ordinal: ['monthly', 'yearly'].includes(cycle) ? form.ordinal : null,
    })
    validationErrors.value = result.errors

    return result
  }

  return { daysText, form, validate, validationErrors }
}
