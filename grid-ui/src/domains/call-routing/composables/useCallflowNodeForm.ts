import { computed, reactive, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { callflowActionDestinationType } from '../catalog/callflowActionCatalog'
import { createCallflowNodeFormSchema } from '../schemas/callflowNodeFormSchema'
import { availableCallflowBranches } from '../services/callflowTreeBranches'
import type {
  CallflowEditor,
  CallflowNodeEditorContext,
  CallflowTreeBranchKey,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeUpdateInput,
} from '../types/callRouting'

export function useCallflowNodeForm(
  contextSource: MaybeRefOrGetter<CallflowNodeEditorContext>,
  editorSource: MaybeRefOrGetter<CallflowEditor | null>,
) {
  const form = reactive<{ branch: CallflowTreeBranchKey | null; destination_id: string }>({
    branch: null,
    destination_id: '',
  })
  const validationErrors = ref<FormErrors>({})
  const context = computed(() => toValue(contextSource))
  const editor = computed(() => toValue(editorSource))
  const destinationType = computed(() => callflowActionDestinationType(context.value.module))
  const destinations = computed(() => {
    const type = destinationType.value
    return type === null ? [] : (editor.value?.destinations[type] ?? [])
  })
  const branches = computed(() =>
    context.value.operation === 'create' ? availableCallflowBranches(context.value.node) : [],
  )

  function initialize(): void {
    const currentTarget = context.value.operation === 'update' ? context.value.node.target : null
    form.branch = branches.value[0]?.value ?? null
    form.destination_id =
      currentTarget && destinations.value.some(({ id }) => id === currentTarget.id)
        ? currentTarget.id
        : (destinations.value[0]?.id ?? '')
    validationErrors.value = {}
  }

  watch([context, editor], initialize, { immediate: true })
  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): CallflowTreeNodeCreateInput | CallflowTreeNodeUpdateInput | null {
    const type = destinationType.value

    if (type === null) {
      validationErrors.value = {
        destination_id: ['This callflow action is not available in the guided editor.'],
      }
      return null
    }

    const result = validateForm(
      createCallflowNodeFormSchema(
        destinations.value.map(({ id }) => id),
        branches.value.map(({ value }) => value),
        context.value.operation === 'create',
      ),
      { ...form },
    )
    validationErrors.value = result.errors

    if (!result.success) return null

    if (context.value.operation === 'create') {
      return {
        parent_path: [...context.value.path],
        branch: result.data.branch as CallflowTreeBranchKey,
        destination_type: type,
        destination_id: result.data.destination_id,
      }
    }

    return {
      node_path: [...context.value.path],
      destination_type: type,
      destination_id: result.data.destination_id,
    }
  }

  return { form, validationErrors, destinationType, destinations, branches, validate }
}
