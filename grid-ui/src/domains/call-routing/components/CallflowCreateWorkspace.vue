<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ShieldCheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { callflowActionAppearance } from '../catalog/callflowActionAppearance'
import {
  callflowActionDestinationType,
  callflowDestinationModule,
  findCallflowAction,
  findCallflowActionById,
  type CallflowAction,
} from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import { useCallflowForm } from '../composables/useCallflowForm'
import {
  callflowInlineRootModules,
  type CallflowInlineRootModule,
  type CallflowCreateInput,
  type CallflowDestinationType,
  type CallflowEditor,
  type CallflowInlineNodeCreateInput,
  type CallflowInlineNodeData,
  type CallflowInlineNodeUpdateInput,
  type CallflowNodeEditorContext,
} from '../types/callRouting'
import CallflowActionPalette from './CallflowActionPalette.vue'
import CallflowAddEntryNumberDialog, {
  type CallflowEntryNumberAddition,
} from './CallflowAddEntryNumberDialog.vue'
import CallflowBranchConnector from './CallflowBranchConnector.vue'
import CallflowCanvasHeader from './CallflowCanvasHeader.vue'
import CallflowConnectorArrow from './CallflowConnectorArrow.vue'
import CallflowEntryPointsField from './CallflowEntryPointsField.vue'
import CallflowEntryNode from './CallflowEntryNode.vue'
import CallflowInlineNodeEditorPanel from './CallflowInlineNodeEditorPanel.vue'
import CallflowMenuBranchesField from './CallflowMenuBranchesField.vue'
import CallflowNodeCard from './CallflowNodeCard.vue'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'
import CallflowRouteSummary from './CallflowRouteSummary.vue'
import CallflowWorkspaceLayout from './CallflowWorkspaceLayout.vue'

const props = defineProps<{
  editor: CallflowEditor
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; save: [input: CallflowCreateInput] }>()
const { form, validate, validationErrors } = useCallflowForm(
  () => null,
  () => props.editor,
)
const metadataOpen = ref(false)
const entryNumberOpen = ref(false)
const actionOpen = ref(false)
const selectedAction = ref<CallflowAction | null>(null)
const rootActionChosen = ref(false)
const rootActionError = ref<string | null>(null)
const draggedRootAction = ref<CallflowAction | null>(null)
const rootDropActive = ref(false)
const fallbackDropActive = ref(false)
const menuBranchDropActive = ref(false)
const rootActionData = ref<CallflowInlineNodeData | null>(null)
const pendingRootAction = ref<CallflowAction | null>(null)
const fallbackOpen = ref(false)
const fallbackDraftType = ref<CallflowDestinationType>('extension')
const fallbackDraftId = ref('')
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const rootActionFieldErrors = computed(() =>
  Object.fromEntries(
    Object.entries(errors.value).flatMap(([field, messages]) =>
      field.startsWith('root_action.') ? [[field.slice('root_action.'.length), messages]] : [],
    ),
  ),
)
const selectedPhoneNumbers = computed(() =>
  props.editor.phone_numbers.filter(({ id }) => form.phone_number_ids.includes(id)),
)
const selectedEntryPoints = computed(() => [
  ...form.extension_numbers.map((value) => ({ value, kind: 'Extension' })),
  ...selectedPhoneNumbers.value.map(({ number: value }) => ({ value, kind: 'Phone number' })),
])

function addEntryNumber(addition: CallflowEntryNumberAddition): void {
  if (addition.type === 'phone_number') {
    if (!form.phone_number_ids.includes(addition.id)) {
      form.phone_number_ids.push(addition.id)
    }
  } else if (!form.extension_numbers.includes(addition.value)) {
    form.extension_numbers.push(addition.value)
  }

  entryNumberOpen.value = false
}
const destinationOptions = computed<ListboxOptionValue[]>(() =>
  props.editor.destinations[form.destination_type].map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const branchDestinationTypeOptions = computed<ListboxOptionValue[]>(() =>
  props.editor.destination_types
    .filter(
      ({ value }) => value !== 'temporal_rules' && props.editor.destinations[value].length > 0,
    )
    .map(({ value, label }) => ({ value, label })),
)
const fallbackOptions = computed(() => props.editor.destinations[fallbackDraftType.value])
const fallbackDestinationOptions = computed<ListboxOptionValue[]>(() =>
  fallbackOptions.value.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const availableMenuBranchDefinition = computed(() =>
  props.editor.menu_branches.branches.find(
    ({ editable, key }) => editable && !form.menu_branches.some((branch) => branch.key === key),
  ),
)
const temporalMatchOptions = computed(
  () => props.editor.destinations[form.temporal_match_destination_type],
)
const temporalMatchDestinationOptions = computed<ListboxOptionValue[]>(() =>
  temporalMatchOptions.value.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const selectedDestination = computed(() =>
  props.editor.destinations[form.destination_type].find(({ id }) => id === form.destination_id),
)
const selectedTemporalRules = computed(
  () => props.editor.temporal_rule_sets[form.destination_id] ?? [],
)

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    const fields = Object.keys(fieldErrors)

    if (fields.some((field) => ['phone_number_ids', 'extension_numbers'].includes(field))) {
      entryNumberOpen.value = true
    } else if (fields.includes('name')) {
      metadataOpen.value = true
    } else if (
      fields.some((field) =>
        ['fallback_destination_type', 'fallback_destination_id'].includes(field),
      )
    ) {
      openFallbackConfiguration()
    } else if (fields.some((field) => field.startsWith('root_action.'))) {
      actionOpen.value = true
    }
  },
  { deep: true },
)
const rootActionAppearance = computed(() =>
  selectedAction.value
    ? callflowActionAppearance(selectedAction.value.module, false)
    : callflowActionAppearance('callflow', false),
)
const rootActionIcon = computed(() =>
  callflowActionIcon(selectedAction.value?.module ?? 'callflow'),
)
const rootActionDetail = computed(() => {
  if (selectedAction.value?.module === 'ring_group') {
    const count = rootActionData.value?.endpoints?.length ?? 0
    return count === 0 ? 'Configure devices' : `${count} device${count === 1 ? '' : 's'}`
  }

  if (selectedAction.value?.module === 'menu') {
    const destination = selectedDestination.value?.label ?? 'Select a menu'
    const count = form.menu_branches.length

    return count === 0 ? destination : `${destination} · ${count} key${count === 1 ? '' : 's'}`
  }

  if (selectedAction.value?.module === 'pivot') {
    return (
      (props.editor.pivot_endpoints ?? []).find(
        ({ id }) => id === rootActionData.value?.endpoint_id,
      )?.label ?? 'Select a Pivot profile'
    )
  }

  if (selectedAction.value?.module === 'dynamic_cid') {
    return (
      props.editor.phone_numbers.find(({ id }) => id === rootActionData.value?.phone_number_id)
        ?.number ?? 'Select an account phone number'
    )
  }

  return selectedDestination.value?.label ?? 'Select a destination'
})
const rootBranchPreviews = computed(() => {
  const branches =
    selectedAction.value?.module === 'menu'
      ? form.menu_branches.map((branch) =>
          branchPreview(branch.key, branch.destination_type, branch.destination_id, false),
        )
      : []

  if (form.fallback_enabled) {
    branches.push(
      branchPreview('_', form.fallback_destination_type, form.fallback_destination_id, true),
    )
  }

  if (
    selectedAction.value?.module === 'temporal_route' &&
    form.manage_temporal_match &&
    form.temporal_match_enabled
  ) {
    branches.unshift(
      branchPreview(
        'rule_set',
        form.temporal_match_destination_type,
        form.temporal_match_destination_id,
        false,
      ),
    )
  }

  return branches
})
const draftNodeCount = computed(() =>
  rootActionChosen.value ? 1 + rootBranchPreviews.value.length : 0,
)
const draftMaxDepth = computed(() => {
  if (!rootActionChosen.value) return 0

  return rootBranchPreviews.value.length ? 2 : 1
})
const draftModuleCount = computed(
  () =>
    new Set(
      [
        selectedAction.value?.module,
        ...rootBranchPreviews.value.map(({ module }) => module),
      ].filter((module): module is string => Boolean(module)),
    ).size,
)
const rootConfigurationContext = computed<CallflowNodeEditorContext>(() => ({
  operation: 'create',
  path: [],
  module: isInlineRootModule(selectedAction.value?.module)
    ? selectedAction.value!.module
    : 'ring_group',
  node: {
    module: 'callflow',
    target: null,
    reference_status: 'not_applicable',
    children: {},
  },
  preset: rootActionData.value ?? undefined,
}))

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function isInlineRootModule(module: string | undefined): module is CallflowInlineRootModule {
  return callflowInlineRootModules.includes(module as CallflowInlineRootModule)
}

function selectRootAction(action: CallflowAction): void {
  const destinationType = callflowActionDestinationType(action.module)
  const inlineRoot = isInlineRootModule(action.module)
  if (!destinationType && !inlineRoot) return

  if (selectedAction.value?.id === action.id) {
    actionOpen.value = true
    return
  }

  if (rootActionChosen.value && selectedAction.value) {
    pendingRootAction.value = action
    return
  }

  applyRootAction(action)
}

function applyRootAction(action: CallflowAction): void {
  const destinationType = callflowActionDestinationType(action.module)

  selectedAction.value = action
  rootActionChosen.value = true
  rootActionError.value = null
  rootActionData.value = null
  if (destinationType) {
    form.destination_type = destinationType
    form.destination_id = props.editor.destinations[destinationType][0]?.id ?? ''
  }
  form.manage_fallback = props.editor.fallback.editable
  form.fallback_enabled = false
  form.manage_menu_branches = action.module === 'menu' && props.editor.menu_branches.editable
  form.menu_branches = []
  form.manage_temporal_match =
    action.module === 'temporal_route' && props.editor.temporal_match.editable
  form.temporal_match_enabled = form.manage_temporal_match
  actionOpen.value = true
}

function confirmRootActionReplacement(): void {
  const action = pendingRootAction.value
  pendingRootAction.value = null
  if (action) applyRootAction(action)
}

function cancelRootActionReplacement(): void {
  pendingRootAction.value = null
}

function beginRootActionDrag(action: CallflowAction): void {
  draggedRootAction.value = action
  rootActionError.value = null
}

function endRootActionDrag(): void {
  draggedRootAction.value = null
  rootDropActive.value = false
  fallbackDropActive.value = false
  menuBranchDropActive.value = false
}

function allowRootActionDrop(event: DragEvent): void {
  if (!hasRootActionTransfer(event)) return

  event.preventDefault()
  rootDropActive.value = true
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'
}

function leaveRootActionDrop(event: DragEvent): void {
  const dropZone = event.currentTarget
  const nextTarget = event.relatedTarget

  if (
    dropZone instanceof HTMLElement &&
    nextTarget instanceof Node &&
    dropZone.contains(nextTarget)
  ) {
    return
  }

  rootDropActive.value = false
}

function dropRootAction(event: DragEvent): void {
  const action = rootActionFromDrop(event)
  if (!action) return

  event.preventDefault()
  endRootActionDrag()
  selectRootAction(action)
}

function rootActionFromDrop(event: DragEvent): CallflowAction | null {
  const action = paletteActionFromDrop(event)

  return action &&
    (callflowActionDestinationType(action.module) !== null || isInlineRootModule(action.module))
    ? action
    : null
}

function paletteActionFromDrop(event: DragEvent): CallflowAction | null {
  if (draggedRootAction.value) return draggedRootAction.value

  const getData = event.dataTransfer?.getData
  if (typeof getData !== 'function') return null

  const actionId = getData.call(event.dataTransfer, 'application/x-gridpbx-callflow-action')
  const module = getData.call(event.dataTransfer, 'text/plain')

  return actionId ? findCallflowActionById(actionId) : module ? findCallflowAction(module) : null
}

function hasRootActionTransfer(event: DragEvent): boolean {
  return (
    draggedRootAction.value !== null ||
    Array.from(event.dataTransfer?.types ?? []).includes('application/x-gridpbx-callflow-action')
  )
}

function resourceActionFromDrop(event: DragEvent) {
  const action = paletteActionFromDrop(event)
  const destinationType = action ? callflowActionDestinationType(action.module) : null
  if (!action || !destinationType) return null

  const destinationId = props.editor.destinations[destinationType][0]?.id
  return destinationId ? { action, destinationType, destinationId } : null
}

function allowFallbackDrop(event: DragEvent): void {
  const selection = resourceActionFromDrop(event)

  rootDropActive.value = false
  menuBranchDropActive.value = false
  fallbackDropActive.value = selection !== null
  if (event.dataTransfer) event.dataTransfer.dropEffect = selection ? 'copy' : 'none'
  if (selection) event.preventDefault()
}

function leaveFallbackDrop(event: DragEvent): void {
  const dropZone = event.currentTarget
  const nextTarget = event.relatedTarget

  if (
    dropZone instanceof HTMLElement &&
    nextTarget instanceof Node &&
    dropZone.contains(nextTarget)
  ) {
    return
  }

  fallbackDropActive.value = false
}

function dropFallback(event: DragEvent): void {
  const selection = resourceActionFromDrop(event)
  if (!selection) {
    fallbackDropActive.value = false
    return
  }

  event.preventDefault()
  setFallbackDraftType(selection.destinationType)
  endRootActionDrag()
  fallbackOpen.value = true
}

function allowMenuBranchDrop(event: DragEvent): void {
  const selection = resourceActionFromDrop(event)
  const allowed = selection !== null && availableMenuBranchDefinition.value !== undefined

  rootDropActive.value = false
  fallbackDropActive.value = false
  menuBranchDropActive.value = allowed
  if (event.dataTransfer) event.dataTransfer.dropEffect = allowed ? 'copy' : 'none'
  if (allowed) event.preventDefault()
}

function leaveMenuBranchDrop(event: DragEvent): void {
  const dropZone = event.currentTarget
  const nextTarget = event.relatedTarget

  if (
    dropZone instanceof HTMLElement &&
    nextTarget instanceof Node &&
    dropZone.contains(nextTarget)
  ) {
    return
  }

  menuBranchDropActive.value = false
}

function dropMenuBranch(event: DragEvent): void {
  const selection = resourceActionFromDrop(event)
  const definition = availableMenuBranchDefinition.value
  if (!selection || !definition) {
    menuBranchDropActive.value = false
    return
  }

  event.preventDefault()
  form.manage_menu_branches = true
  form.menu_branches = [
    ...form.menu_branches,
    {
      key: definition.key,
      destination_type: selection.destinationType,
      destination_id: selection.destinationId,
    },
  ]
  endRootActionDrag()
  actionOpen.value = true
}

function setDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.destination_id = value
}

function setFallbackDraftType(value: ListboxValue): void {
  if (typeof value !== 'string') return

  fallbackDraftType.value = value as CallflowDestinationType
  fallbackDraftId.value = props.editor.destinations[fallbackDraftType.value][0]?.id ?? ''
}

function setFallbackDraftDestination(value: ListboxValue): void {
  if (typeof value === 'string') fallbackDraftId.value = value
}

function openFallbackConfiguration(): void {
  if (!props.editor.fallback.editable) return

  fallbackDraftType.value = form.fallback_destination_type
  fallbackDraftId.value = form.fallback_destination_id
  const options = props.editor.destinations[fallbackDraftType.value]
  if (!options.some(({ id }) => id === fallbackDraftId.value)) {
    const firstType = branchDestinationTypeOptions.value[0]?.value
    if (typeof firstType === 'string') setFallbackDraftType(firstType)
  }

  fallbackOpen.value = true
}

function saveFallback(): void {
  form.manage_fallback = true
  form.fallback_enabled = true
  form.fallback_destination_type = fallbackDraftType.value
  form.fallback_destination_id = fallbackDraftId.value
  fallbackOpen.value = false
}

function removeFallback(): void {
  form.manage_fallback = true
  form.fallback_enabled = false
  fallbackOpen.value = false
}

function setTemporalMatchDestinationType(value: ListboxValue): void {
  if (typeof value !== 'string') return

  form.temporal_match_destination_type = value as CallflowDestinationType
  form.temporal_match_destination_id =
    props.editor.destinations[form.temporal_match_destination_type][0]?.id ?? ''
}

function setTemporalMatchDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.temporal_match_destination_id = value
}

function removeRootAction(): void {
  selectedAction.value = null
  rootActionChosen.value = false
  rootActionError.value = null
  rootActionData.value = null
  pendingRootAction.value = null
  fallbackOpen.value = false
  fallbackDropActive.value = false
  menuBranchDropActive.value = false
  form.manage_fallback = false
  form.fallback_enabled = false
  form.manage_menu_branches = false
  form.menu_branches = []
  form.manage_temporal_match = false
  form.temporal_match_enabled = false
}

function removeRootActionAndCloseDialog(): void {
  removeRootAction()
  actionOpen.value = false
}

function submit(): void {
  if (!rootActionChosen.value) {
    rootActionError.value = 'Drag or select a root action from the catalog.'
    return
  }

  if (isInlineRootModule(selectedAction.value?.module) && rootActionData.value === null) {
    rootActionError.value = {
      ring_group: 'Configure at least one Ring Group device.',
      call_forward: 'Choose a Call Forwarding operation.',
      dynamic_cid: 'Select a synchronized account phone number.',
      pivot: 'Configure an administrator-approved Pivot profile.',
    }[selectedAction.value!.module]
    actionOpen.value = true
    return
  }

  const result = validate(
    isInlineRootModule(selectedAction.value?.module) && rootActionData.value
      ? { module: selectedAction.value.module, data: rootActionData.value }
      : null,
  )
  if (result.success) {
    emit('save', result.data)
    return
  }

  if (result.errors.name || result.errors.phone_number_ids || result.errors.extension_numbers)
    metadataOpen.value = true
  else if (result.errors.fallback_destination_type || result.errors.fallback_destination_id)
    openFallbackConfiguration()
  else actionOpen.value = true
}

function saveInlineRootAction(
  input: CallflowInlineNodeCreateInput | CallflowInlineNodeUpdateInput,
): void {
  if (!isInlineRootModule(input.module)) return

  rootActionData.value = input.data
  rootActionError.value = null
  actionOpen.value = false
}

function branchPreview(key: string, type: CallflowDestinationType, id: string, fallback: boolean) {
  const module = callflowDestinationModule(type) ?? 'callflow'
  const action = findCallflowAction(module)
  const target = props.editor.destinations[type].find((destination) => destination.id === id)

  return {
    key,
    fallback,
    module,
    label: action?.label ?? type,
    detail: target?.label ?? 'Select a destination',
    icon: callflowActionIcon(module),
    appearance: callflowActionAppearance(module, false),
  }
}
</script>

<template>
  <form class="grid gap-4" novalidate @submit.prevent="submit">
    <div
      v-if="error && Object.keys(fieldErrors).length === 0"
      role="alert"
      class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-xs text-danger"
    >
      {{ error }}
    </div>

    <CallflowWorkspaceLayout
      data-callflow-create-workspace
      description="Build the visual route map by dragging actions from the catalog"
      class="min-h-[calc(100dvh-7rem)]"
    >
      <template #summary>
        <CallflowRouteSummary
          :node-count="draftNodeCount"
          :max-depth="draftMaxDepth"
          :module-count="draftModuleCount"
          status-label="Draft"
          status-detail="Not synchronized"
        />
      </template>

      <section
        aria-label="Create callflow canvas"
        data-callflow-root-drop-zone
        class="relative flex h-[calc(100dvh-7rem)] min-h-[36rem] w-full flex-col overflow-hidden bg-slate-50/70 transition"
        :class="rootDropActive && 'ring-2 ring-inset ring-emerald-300'"
        @dragenter="allowRootActionDrop"
        @dragover="allowRootActionDrop"
        @dragleave="leaveRootActionDrop"
        @drop="dropRootAction"
      >
        <div
          class="callflow-create-canvas callflow-canvas-texture min-h-0 flex-1 overflow-auto p-8 pt-20"
        >
          <CallflowCanvasHeader />
          <div class="mx-auto flex w-max min-w-full flex-col items-center pt-4">
            <div data-callflow-create-actions class="mb-3 flex w-80 justify-center gap-2">
              <button
                type="button"
                class="h-8 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50"
                @click="emit('close')"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="h-8 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
              >
                {{ saving ? 'Creating callflow…' : 'Create callflow' }}
              </button>
            </div>

            <CallflowEntryNode
              :name="form.name || 'Callflow'"
              :entries="selectedEntryPoints"
              editable
              @edit="metadataOpen = true"
              @add-entry="entryNumberOpen = true"
            />

            <template v-if="rootActionChosen && selectedAction">
              <CallflowConnectorArrow />
              <div class="relative h-[84px] w-36">
                <CallflowNodeCard
                  :label="selectedAction.label"
                  :module="selectedAction.module"
                  :icon="rootActionIcon"
                  :border-class="rootActionAppearance.nodeBorder"
                  :icon-class="rootActionAppearance.nodeIcon"
                  :detail="rootActionDetail"
                />
                <button
                  type="button"
                  :aria-label="`Remove ${selectedAction.label}`"
                  :title="`Remove ${selectedAction.label}`"
                  class="absolute top-1 right-1 z-10 grid size-4 place-items-center rounded-sm text-white/75 hover:bg-black/30 hover:text-white"
                  @click="removeRootAction"
                >
                  <XMarkIcon class="size-3.5" />
                </button>
                <button
                  type="button"
                  class="absolute inset-0 z-0 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                  :aria-label="`Configure ${selectedAction.label}`"
                  @click="actionOpen = true"
                />
              </div>

              <template v-if="rootBranchPreviews.length">
                <CallflowConnectorArrow v-if="rootBranchPreviews.length === 1" />
                <CallflowBranchConnector v-if="rootBranchPreviews.length > 1" kind="parent-stem" />
                <div class="flex items-start justify-center">
                  <div
                    v-for="(branch, branchIndex) in rootBranchPreviews"
                    :key="branch.key"
                    class="relative flex flex-col items-center"
                    :class="rootBranchPreviews.length > 1 && 'px-3'"
                  >
                    <CallflowBranchConnector
                      v-if="rootBranchPreviews.length > 1"
                      :kind="
                        branchIndex === 0
                          ? 'first'
                          : branchIndex === rootBranchPreviews.length - 1
                            ? 'last'
                            : 'middle'
                      "
                    />
                    <div
                      data-callflow-create-branch-label
                      class="z-10 mt-1 flex h-4 w-36 items-center justify-center rounded-sm bg-callflow-node px-2 text-[8px] font-semibold text-white"
                    >
                      {{ branch.key }}
                    </div>
                    <CallflowConnectorArrow />
                    <div class="relative h-[84px] w-36">
                      <CallflowNodeCard
                        :label="branch.label"
                        :module="branch.module"
                        :icon="branch.icon"
                        :border-class="branch.appearance.nodeBorder"
                        :icon-class="branch.appearance.nodeIcon"
                        :detail="branch.detail"
                      />
                      <button
                        v-if="branch.fallback"
                        type="button"
                        aria-label="Configure fallback branch"
                        class="absolute inset-0 z-0 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                        @click="openFallbackConfiguration"
                      />
                      <button
                        v-if="branch.fallback"
                        type="button"
                        aria-label="Remove fallback branch"
                        title="Remove fallback branch"
                        class="absolute top-1 right-1 z-10 grid size-4 place-items-center rounded-sm text-white/75 hover:bg-black/30 hover:text-white"
                        @click="removeFallback"
                      >
                        <XMarkIcon class="size-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </template>

              <button
                v-if="selectedAction.module === 'menu' && availableMenuBranchDefinition"
                type="button"
                data-callflow-create-menu-drop-zone
                class="mt-4 inline-flex h-7 items-center rounded-md border border-dashed px-3 text-[10px] font-semibold shadow-sm transition"
                :class="
                  menuBranchDropActive
                    ? 'border-emerald-400 bg-emerald-50 text-emerald-700 ring-2 ring-emerald-200'
                    : 'border-slate-400 bg-white/80 text-slate-600 hover:border-brand-400 hover:text-brand-700'
                "
                aria-label="Add menu key branch"
                @click="actionOpen = true"
                @dragenter.stop="allowMenuBranchDrop"
                @dragover.stop="allowMenuBranchDrop"
                @dragleave.stop="leaveMenuBranchDrop"
                @drop.stop="dropMenuBranch"
              >
                {{
                  menuBranchDropActive && draggedRootAction
                    ? `Drop ${draggedRootAction.label} on ${availableMenuBranchDefinition.label}`
                    : '+ Add menu key'
                }}
              </button>

              <button
                v-if="editor.fallback.editable && !form.fallback_enabled"
                type="button"
                data-callflow-create-fallback-drop-zone
                class="mt-4 inline-flex h-7 items-center rounded-md border border-dashed px-3 text-[10px] font-semibold shadow-sm transition"
                :class="
                  fallbackDropActive
                    ? 'border-emerald-400 bg-emerald-50 text-emerald-700 ring-2 ring-emerald-200'
                    : 'border-slate-400 bg-white/80 text-slate-600 hover:border-brand-400 hover:text-brand-700'
                "
                aria-label="Add fallback branch"
                @click="openFallbackConfiguration"
                @dragenter.stop="allowFallbackDrop"
                @dragover.stop="allowFallbackDrop"
                @dragleave.stop="leaveFallbackDrop"
                @drop.stop="dropFallback"
              >
                {{
                  fallbackDropActive && draggedRootAction
                    ? `Drop ${draggedRootAction.label} as fallback`
                    : '+ Add fallback'
                }}
              </button>
            </template>

            <div
              v-else
              class="mt-8 rounded-md border border-dashed px-5 py-4 text-center text-xs"
              :class="
                rootActionError
                  ? 'border-red-300 bg-red-50 text-danger'
                  : rootDropActive
                    ? 'border-emerald-400 bg-emerald-50 text-emerald-700'
                    : 'border-slate-300 bg-white/80 text-slate-500'
              "
            >
              {{
                rootDropActive
                  ? 'Drop to use this as the root action.'
                  : 'Drag an action here or select one from the catalog to start the callflow.'
              }}
            </div>
          </div>
        </div>
      </section>

      <template #palette="{ floating, startMoving, dock, collapse }">
        <CallflowActionPalette
          compact
          movable
          collapsible
          :floating="floating"
          root-only
          enabled
          drag-enabled
          :action-capabilities="editor?.action_capabilities ?? {}"
          @choose="selectRootAction"
          @action-drag-start="beginRootActionDrag"
          @action-drag-end="endRootActionDrag"
          @drag-start="startMoving"
          @dock="dock"
          @collapse="collapse"
        />
      </template>

      <template #sidebar>
        <article class="card-surface p-4">
          <h2 class="text-sm font-semibold text-slate-700">Entry points</h2>
          <div class="mt-4 text-xs">
            <p class="text-[9px] font-bold tracking-wide text-slate-400 uppercase">Numbers</p>
            <p class="mt-1 break-words font-mono text-slate-600">
              {{ selectedEntryPoints.map(({ value }) => value).join(', ') || 'None selected' }}
            </p>
          </div>
        </article>

        <article class="card-surface p-4">
          <h2 class="text-sm font-semibold text-slate-700">Assignments</h2>
          <p class="mt-4 text-xs leading-5 text-slate-500">
            This draft is not assigned until it is created successfully in Switch.
          </p>
        </article>

        <div
          class="flex gap-3 rounded-md border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
        >
          <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
          <p>
            Only projected, account-scoped references are submitted. Switch identifiers remain
            server-side.
          </p>
        </div>
      </template>
    </CallflowWorkspaceLayout>
  </form>

  <CallflowNodeInfoDialog
    :open="metadataOpen"
    title="Callflow"
    breadcrumb="Create callflow / Name and numbers"
    @close="metadataOpen = false"
  >
    <div class="grid gap-5">
      <FormInput
        v-model="form.name"
        label="Callflow name"
        maxlength="128"
        required
        :error="fieldError('name')"
      />
      <CallflowEntryPointsField
        :phone-numbers="editor.phone_numbers"
        :phone-number-ids="form.phone_number_ids"
        :extension-numbers="form.extension_numbers"
        :preserved-numbers="editor.preserved_numbers ?? []"
        :phone-error="fieldError('phone_number_ids')"
        :extension-error="fieldError('extension_numbers')"
        @update:phone-number-ids="form.phone_number_ids = $event"
        @update:extension-numbers="form.extension_numbers = $event"
      />
      <div class="flex justify-end">
        <button
          type="button"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="metadataOpen = false"
        >
          Done
        </button>
      </div>
    </div>
  </CallflowNodeInfoDialog>

  <CallflowAddEntryNumberDialog
    :open="entryNumberOpen"
    :phone-numbers="editor.phone_numbers"
    :phone-number-ids="form.phone_number_ids"
    :extension-numbers="form.extension_numbers"
    :preserved-numbers="editor.preserved_numbers ?? []"
    :error="error"
    :field-errors="errors"
    @close="entryNumberOpen = false"
    @add="addEntryNumber"
  />

  <CallflowNodeInfoDialog
    :open="actionOpen && selectedAction !== null && !isInlineRootModule(selectedAction.module)"
    :title="selectedAction ? `Configure ${selectedAction.label}` : 'Configure action'"
    breadcrumb="Create callflow / Root action"
    @close="actionOpen = false"
  >
    <div v-if="selectedAction" class="grid gap-5">
      <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
        <p class="text-xs font-semibold text-slate-700">{{ selectedAction.label }}</p>
        <p class="mt-1 text-[10px] leading-4 text-slate-500">
          {{ selectedAction.description }}
        </p>
      </div>
      <label class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Destination</span>
        <FormListbox
          :model-value="form.destination_id"
          :options="destinationOptions"
          aria-label="Root action destination"
          :placeholder="
            destinationOptions.length ? 'Select a destination' : 'No projected targets available'
          "
          :invalid="Boolean(fieldError('destination_id'))"
          @update:model-value="setDestination"
        />
        <span v-if="fieldError('destination_id')" class="text-[10px] text-danger">
          {{ fieldError('destination_id') }}
        </span>
      </label>
      <p v-if="selectedAction.module !== 'menu'" class="text-[10px] leading-4 text-slate-500">
        Additional branches and inline actions become available on the visual canvas immediately
        after this callflow is created in Switch.
      </p>
      <section
        v-if="selectedAction.module === 'temporal_route'"
        class="overflow-hidden rounded-md border border-slate-200"
        :class="validationControlClass(fieldError('temporal_match_destination_id'))"
      >
        <header class="border-b border-slate-200 bg-slate-50 px-4 py-3">
          <h3 class="text-xs font-semibold text-slate-700">Schedule routes</h3>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            A matching member follows the literal rule_set branch; no match follows the wildcard
            fallback.
          </p>
        </header>
        <div v-if="editor.temporal_match.editable" class="grid gap-4 p-4">
          <div class="rounded-md border border-slate-200 bg-slate-50/60 p-4">
            <p class="text-xs font-semibold text-slate-700">Rule evaluation order</p>
            <ol v-if="selectedTemporalRules.length" class="mt-3 grid gap-2">
              <li
                v-for="rule in selectedTemporalRules"
                :key="rule.id ?? `unresolved-${rule.position}`"
                class="flex items-center gap-3 text-xs"
              >
                <span
                  class="grid size-6 shrink-0 place-items-center rounded-full border border-slate-300 bg-white text-[10px] font-semibold text-slate-600"
                >
                  {{ rule.position + 1 }}
                </span>
                <span :class="rule.resolved ? 'text-slate-700' : 'font-semibold text-amber-700'">
                  {{ rule.label }}
                </span>
              </li>
            </ol>
            <p v-else class="mt-2 text-[10px] text-amber-700">
              This Rule Set has no projected member rules.
            </p>
          </div>

          <ToggleSwitch
            v-model="form.temporal_match_enabled"
            label="Route matching calls"
            description="Create the rule_set branch required by the Switch temporal-route contract."
          />

          <div v-if="form.temporal_match_enabled" class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Match destination type</span>
              <FormListbox
                :model-value="form.temporal_match_destination_type"
                :options="branchDestinationTypeOptions"
                aria-label="Schedule match destination type"
                :invalid="Boolean(fieldError('temporal_match_destination_type'))"
                @update:model-value="setTemporalMatchDestinationType"
              />
              <span
                v-if="fieldError('temporal_match_destination_type')"
                class="text-[10px] text-danger"
              >
                {{ fieldError('temporal_match_destination_type') }}
              </span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Match destination</span>
              <FormListbox
                :model-value="form.temporal_match_destination_id"
                :options="temporalMatchDestinationOptions"
                aria-label="Schedule match destination"
                :placeholder="
                  temporalMatchOptions.length
                    ? 'Select a match destination'
                    : 'No projected targets available'
                "
                :invalid="Boolean(fieldError('temporal_match_destination_id'))"
                @update:model-value="setTemporalMatchDestination"
              />
              <span
                v-if="fieldError('temporal_match_destination_id')"
                class="text-[10px] text-danger"
              >
                {{ fieldError('temporal_match_destination_id') }}
              </span>
            </label>
          </div>
        </div>
        <p v-else class="bg-amber-50 p-4 text-xs text-amber-800">
          {{ editor.temporal_match.blocked_reason }}
        </p>
      </section>
      <section
        v-if="selectedAction.module === 'menu'"
        class="overflow-hidden rounded-md border border-slate-200"
        :class="validationControlClass(fieldError('menu_branches'))"
      >
        <header class="border-b border-slate-200 bg-slate-50 px-4 py-3">
          <h3 class="text-xs font-semibold text-slate-700">Menu key routes</h3>
          <p class="mt-1 text-[10px] leading-4 text-slate-500">
            Add the Switch keys that should leave this IVR. They appear immediately on the canvas.
          </p>
        </header>
        <div v-if="editor.menu_branches.editable" class="p-4">
          <CallflowMenuBranchesField
            :branches="form.menu_branches"
            :editor="editor"
            :errors="errors"
            @update:branches="form.menu_branches = $event"
          />
        </div>
        <p v-else class="bg-amber-50 p-4 text-xs text-amber-800">
          {{ editor.menu_branches.blocked_reason }}
        </p>
      </section>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600"
          @click="removeRootActionAndCloseDialog"
        >
          Remove action
        </button>
        <button
          type="button"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="actionOpen = false"
        >
          Use action
        </button>
      </div>
    </div>
  </CallflowNodeInfoDialog>

  <CallflowNodeInfoDialog
    :open="fallbackOpen"
    title="Configure fallback"
    breadcrumb="Create callflow / Wildcard branch"
    @close="fallbackOpen = false"
  >
    <div class="grid gap-5">
      <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
        <p class="text-xs font-semibold text-slate-700">Fallback destination</p>
        <p class="mt-1 text-[10px] leading-4 text-slate-500">
          The wildcard branch runs when the root action does not complete the call.
        </p>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Fallback type</span>
          <FormListbox
            :model-value="fallbackDraftType"
            :options="branchDestinationTypeOptions"
            aria-label="Fallback type"
            :invalid="Boolean(fieldError('fallback_destination_type'))"
            @update:model-value="setFallbackDraftType"
          />
          <span v-if="fieldError('fallback_destination_type')" class="text-[10px] text-danger">
            {{ fieldError('fallback_destination_type') }}
          </span>
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Fallback destination</span>
          <FormListbox
            :model-value="fallbackDraftId"
            :options="fallbackDestinationOptions"
            aria-label="Fallback destination"
            :placeholder="
              fallbackOptions.length
                ? 'Select a fallback destination'
                : 'No projected targets available'
            "
            :invalid="Boolean(fieldError('fallback_destination_id'))"
            @update:model-value="setFallbackDraftDestination"
          />
          <span v-if="fieldError('fallback_destination_id')" class="text-[10px] text-danger">
            {{ fieldError('fallback_destination_id') }}
          </span>
        </label>
      </div>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="h-8 rounded-md border border-red-200 bg-white px-4 text-xs font-semibold text-danger hover:bg-red-50"
          @click="removeFallback"
        >
          Remove fallback
        </button>
        <button
          type="button"
          class="h-8 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="saveFallback"
        >
          Use fallback
        </button>
      </div>
    </div>
  </CallflowNodeInfoDialog>

  <CallflowNodeInfoDialog
    :open="pendingRootAction !== null"
    title="Replace root action?"
    breadcrumb="Create callflow / Root action"
    @close="cancelRootActionReplacement"
  >
    <div class="grid gap-5">
      <p class="text-xs leading-5 text-slate-600">
        Replacing {{ selectedAction?.label }} with {{ pendingRootAction?.label }} removes the
        current unsaved root configuration and its branches from this draft.
      </p>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600"
          @click="cancelRootActionReplacement"
        >
          Keep current action
        </button>
        <button
          type="button"
          class="h-9 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white"
          @click="confirmRootActionReplacement"
        >
          Replace root action
        </button>
      </div>
    </div>
  </CallflowNodeInfoDialog>

  <CallflowInlineNodeEditorPanel
    v-if="actionOpen && isInlineRootModule(selectedAction?.module)"
    root-configuration
    :context="rootConfigurationContext"
    :editor="editor"
    :saving="false"
    :error="error"
    :field-errors="rootActionFieldErrors"
    @close="actionOpen = false"
    @save="saveInlineRootAction"
  />
</template>

<style scoped>
.callflow-create-canvas {
  background-color: rgb(248 250 252);
  background-image:
    linear-gradient(to right, rgb(148 163 184 / 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgb(148 163 184 / 0.1) 1px, transparent 1px),
    radial-gradient(circle, rgb(71 85 105 / 0.2) 1px, transparent 1.25px);
  background-position:
    0 0,
    0 0,
    12px 12px;
  background-size:
    96px 96px,
    96px 96px,
    24px 24px;
}
</style>
