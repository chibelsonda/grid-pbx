import { computed, reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { createLineKeyFormSchema } from '../schemas/lineKeyFormSchema'
import type { LineKeyInput, LineKeyPreview } from '../types/lineKey'

type LineKeyFormResult = { success: true; data: LineKeyInput[] } | { success: false; data: null }

function nullableText(value: string | null): string | null {
  const trimmed = value?.trim() ?? ''

  return trimmed === '' ? null : trimmed
}

function normalizedKey(key: LineKeyInput): LineKeyInput {
  return {
    ...key,
    label: nullableText(key.label),
    value: typeof key.value === 'string' ? nullableText(key.value) : key.value,
  }
}

function payload(key: LineKeyInput): Record<string, unknown> {
  const data: Record<string, unknown> = { type: key.type }

  if (key.value !== null) {
    const value = key.type === 'parking' ? Number(key.value) : key.value
    data.value = key.label === null ? value : { label: key.label, value }
  }

  return data
}

export function useLineKeyForm(preview: LineKeyPreview) {
  const form = reactive<LineKeyInput[]>(
    preview.device.line_keys.map((key) => ({
      category: key.category,
      position: key.position,
      type: key.type,
      label: key.label,
      value: key.value,
    })),
  )
  const validationErrors = ref<FormErrors>({})
  const maximumAssignments = computed(() =>
    Math.min(preview.capability.model.total_keys ?? 100, 1000),
  )
  const canAdd = computed(() => form.length < maximumAssignments.value)
  const normalizedKeys = computed(() => form.map(normalizedKey))
  const safePreview = computed(() => ({
    provision: {
      combo_keys: Object.fromEntries(
        normalizedKeys.value
          .filter((key) => key.category === 'combo')
          .map((key) => [key.position, payload(key)]),
      ),
      feature_keys: Object.fromEntries(
        normalizedKeys.value
          .filter((key) => key.category === 'feature')
          .map((key) => [key.position, payload(key)]),
      ),
    },
  }))

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function add(startPosition = 0, endPosition = 999): void {
    if (!canAdd.value) return

    const positions = new Set(form.map((key) => key.position))
    let position = startPosition

    while (position <= endPosition && positions.has(position)) position += 1

    if (position > endPosition) return

    form.push({
      category: 'feature',
      position,
      type: preview.capability.model.supported_key_types[0] ?? 'speed_dial',
      label: null,
      value: null,
    })
  }

  function remove(index: number): void {
    form.splice(index, 1)
  }

  function validate(): LineKeyFormResult {
    const result = validateForm(createLineKeyFormSchema(preview.capability), {
      line_keys: normalizedKeys.value,
    })
    validationErrors.value = result.errors

    return result.success
      ? { success: true, data: result.data.line_keys }
      : { success: false, data: null }
  }

  return { add, canAdd, form, remove, safePreview, validate, validationErrors }
}
