import { computed, reactive, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { availableCallflowBranches } from '../services/callflowTreeBranches'
import {
  callflowDtmfDigits,
  createCallflowInlineNodeFormSchema,
} from '../schemas/callflowInlineNodeFormSchema'
import type {
  CallflowInlineModule,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeData,
  CallflowInlineNodeUpdateInput,
  CallflowNodeEditorContext,
  CallflowTreeBranchKey,
} from '../types/callRouting'

type InlineFormState = {
  branch: CallflowTreeBranchKey | null
  data: CallflowInlineNodeData
}

function defaults(module: CallflowInlineModule): CallflowInlineNodeData {
  switch (module) {
    case 'sleep':
      return { duration: 0, unit: 's', skip_module: false }
    case 'tts':
      return {
        text: '',
        voice: 'female',
        language: null,
        engine: null,
        endless_playback: false,
        terminators: [...callflowDtmfDigits],
        skip_module: false,
      }
    case 'collect_dtmf':
      return {
        collection_name: null,
        interdigit_timeout: 2000,
        max_digits: 1,
        terminators: ['#'],
        timeout: 5000,
        skip_module: false,
      }
    case 'record_call':
      return {
        action: 'start',
        format: null,
        label: null,
        record_min_sec: null,
        record_on_answer: false,
        record_on_bridge: false,
        record_sample_rate: null,
        should_follow_transfer: true,
        time_limit: 3600,
        skip_module: false,
      }
    case 'record_caller':
      return { format: null, time_limit: 3600, skip_module: false }
    case 'send_dtmf':
      return { digits: '', duration_ms: 2000, skip_module: false }
    case 'flush_dtmf':
      return { collection_name: 'default', skip_module: false }
    case 'dead_air':
      return { skip_module: false }
    case 'language':
      return { language: 'en', skip_module: false }
    case 'missed_call_alert':
      return { recipients: [], skip_module: false }
  }
}

function settingsForEdit(
  module: CallflowInlineModule,
  settings: Record<string, unknown> | null | undefined,
): CallflowInlineNodeData {
  const data = defaults(module)
  if (!settings) return data

  const writable = data as Record<string, unknown>
  for (const key of Object.keys(writable)) {
    if (Object.hasOwn(settings, key)) writable[key] = settings[key]
  }

  if (
    module === 'collect_dtmf' &&
    !Object.hasOwn(settings, 'terminators') &&
    typeof settings.terminator === 'string'
  ) {
    data.terminators = [settings.terminator]
  }

  return data
}

export function useCallflowInlineNodeForm(
  contextSource: MaybeRefOrGetter<CallflowNodeEditorContext>,
) {
  const context = computed(() => toValue(contextSource))
  const module = computed(() => context.value.module as CallflowInlineModule)
  const branches = computed(() =>
    context.value.operation === 'create' ? availableCallflowBranches(context.value.node) : [],
  )
  const form = reactive<InlineFormState>({ branch: null, data: defaults(module.value) })
  const validationErrors = ref<FormErrors>({})

  function initialize(): void {
    form.branch = branches.value[0]?.value ?? null
    form.data = settingsForEdit(
      module.value,
      context.value.operation === 'update' ? context.value.node.settings : null,
    )
    validationErrors.value = {}
  }

  watch(context, initialize, { immediate: true })
  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): CallflowInlineNodeCreateInput | CallflowInlineNodeUpdateInput | null {
    const result = validateForm(
      createCallflowInlineNodeFormSchema(
        module.value,
        branches.value.map(({ value }) => value),
        context.value.operation === 'create',
      ),
      { branch: form.branch, data: { ...form.data } },
    )
    validationErrors.value = result.errors

    if (!result.success) return null

    const data = result.data.data as CallflowInlineNodeData
    if (context.value.operation === 'create') {
      return {
        parent_path: [...context.value.path],
        branch: result.data.branch as CallflowTreeBranchKey,
        module: module.value,
        data,
      }
    }

    return {
      node_path: [...context.value.path],
      module: module.value,
      data,
    }
  }

  return { form, module, branches, validationErrors, validate }
}
