<script setup lang="ts">
import { computed, ref } from 'vue'
import { PencilSquareIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { callflowActionAppearance } from '../catalog/callflowActionAppearance'
import {
  callflowActionDestinationType,
  findCallflowAction,
  findCallflowActionById,
  type CallflowAction,
} from '../catalog/callflowActionCatalog'
import { callflowActionIcon, callflowEntryIcon } from '../catalog/callflowActionIcons'
import { useCallflowForm } from '../composables/useCallflowForm'
import type {
  CallflowCreateInput,
  CallflowDestinationType,
  CallflowEditor,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeData,
  CallflowInlineNodeUpdateInput,
  CallflowNodeEditorContext,
} from '../types/callRouting'
import CallflowActionPalette from './CallflowActionPalette.vue'
import CallflowConnectorArrow from './CallflowConnectorArrow.vue'
import CallflowInlineNodeEditorPanel from './CallflowInlineNodeEditorPanel.vue'
import CallflowNodeCard from './CallflowNodeCard.vue'
import CallflowNodeInfoDialog from './CallflowNodeInfoDialog.vue'

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
const actionOpen = ref(false)
const selectedAction = ref<CallflowAction | null>(null)
const rootActionChosen = ref(false)
const rootActionError = ref<string | null>(null)
const draggedRootAction = ref<CallflowAction | null>(null)
const rootDropActive = ref(false)
const rootActionData = ref<CallflowInlineNodeData | null>(null)
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
const destinationOptions = computed<ListboxOptionValue[]>(() =>
  props.editor.destinations[form.destination_type].map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const selectedDestination = computed(() =>
  props.editor.destinations[form.destination_type].find(({ id }) => id === form.destination_id),
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

  return selectedDestination.value?.label ?? 'Select a destination'
})
const rootConfigurationContext = computed<CallflowNodeEditorContext>(() => ({
  operation: 'create',
  path: [],
  module: 'ring_group',
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

function selectRootAction(action: CallflowAction): void {
  const destinationType = callflowActionDestinationType(action.module)
  const inlineRoot = action.module === 'ring_group'
  if (!destinationType && !inlineRoot) return

  selectedAction.value = action
  rootActionChosen.value = true
  rootActionError.value = null
  rootActionData.value = null
  if (destinationType) {
    form.destination_type = destinationType
    form.destination_id = props.editor.destinations[destinationType][0]?.id ?? ''
  }
  form.manage_fallback = false
  form.fallback_enabled = false
  form.manage_menu_branches = false
  form.menu_branches = []
  form.manage_temporal_match = false
  form.temporal_match_enabled = false
  actionOpen.value = true
}

function beginRootActionDrag(action: CallflowAction): void {
  draggedRootAction.value = action
  rootActionError.value = null
}

function endRootActionDrag(): void {
  draggedRootAction.value = null
  rootDropActive.value = false
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
  if (draggedRootAction.value) return draggedRootAction.value

  const getData = event.dataTransfer?.getData
  if (typeof getData !== 'function') return null

  const actionId = getData.call(event.dataTransfer, 'application/x-gridpbx-callflow-action')
  const module = getData.call(event.dataTransfer, 'text/plain')
  const action = actionId
    ? findCallflowActionById(actionId)
    : module
      ? findCallflowAction(module)
      : null

  return action &&
    (callflowActionDestinationType(action.module) !== null || action.module === 'ring_group')
    ? action
    : null
}

function hasRootActionTransfer(event: DragEvent): boolean {
  return (
    draggedRootAction.value !== null ||
    Array.from(event.dataTransfer?.types ?? []).includes('application/x-gridpbx-callflow-action')
  )
}

function setDestination(value: ListboxValue): void {
  if (typeof value === 'string') form.destination_id = value
}

function removeRootAction(): void {
  selectedAction.value = null
  rootActionChosen.value = false
  rootActionError.value = null
  rootActionData.value = null
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

  if (selectedAction.value?.module === 'ring_group' && rootActionData.value === null) {
    rootActionError.value = 'Configure at least one Ring Group device.'
    actionOpen.value = true
    return
  }

  const result = validate(
    selectedAction.value?.module === 'ring_group' && rootActionData.value
      ? { module: 'ring_group', data: rootActionData.value }
      : null,
  )
  if (result.success) {
    emit('save', result.data)
    return
  }

  if (result.errors.name || result.errors.phone_number_ids) metadataOpen.value = true
  else actionOpen.value = true
}

function saveInlineRootAction(
  input: CallflowInlineNodeCreateInput | CallflowInlineNodeUpdateInput,
): void {
  if (input.module !== 'ring_group') return

  rootActionData.value = input.data
  rootActionError.value = null
  actionOpen.value = false
}
</script>

<template>
  <form class="grid gap-4" novalidate @submit.prevent="submit">
    <div
      v-if="error && Object.keys(fieldErrors).length === 0"
      class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-xs text-danger"
    >
      {{ error }}
    </div>

    <div class="grid min-h-[calc(100dvh-14rem)] gap-3 xl:grid-cols-[minmax(0,1fr)_11.5rem]">
      <section
        aria-label="New callflow canvas"
        data-callflow-root-drop-zone
        class="callflow-create-canvas relative flex min-h-[34rem] flex-col overflow-auto rounded-lg border p-8 transition"
        :class="rootDropActive ? 'border-emerald-400 ring-2 ring-emerald-200' : 'border-slate-200'"
        @dragenter="allowRootActionDrop"
        @dragover="allowRootActionDrop"
        @dragleave="leaveRootActionDrop"
        @drop="dropRootAction"
      >
        <div class="mx-auto flex w-max min-w-full flex-1 flex-col items-center pt-4">
          <article
            aria-label="Callflow entry"
            class="h-14 w-80 overflow-hidden rounded-md border border-brand-500 bg-white shadow-sm"
          >
            <header class="flex h-6 items-center gap-2 bg-brand-600 px-2 text-white">
              <component :is="callflowEntryIcon" class="size-3.5" />
              <p class="text-[10px] font-semibold">{{ form.name || 'Callflow' }}</p>
              <button
                type="button"
                aria-label="Edit callflow name and numbers"
                title="Edit callflow name and numbers"
                class="ml-auto grid size-5 place-items-center rounded-sm text-blue-100 hover:bg-white/10 hover:text-white"
                @click="metadataOpen = true"
              >
                <PencilSquareIcon class="size-3.5" />
              </button>
            </header>
            <button
              type="button"
              class="grid h-8 w-full grid-cols-2 divide-x divide-slate-200 text-left"
              @click="metadataOpen = true"
            >
              <span class="min-w-0 px-2 py-1">
                <span
                  v-if="selectedPhoneNumbers[0]"
                  class="block truncate font-mono text-[9px] font-semibold text-slate-700"
                >
                  {{ selectedPhoneNumbers[0].number }}
                </span>
                <span v-else class="block text-[9px] text-slate-500">Click to add number</span>
              </span>
              <span class="min-w-0 px-2 py-1">
                <span
                  v-if="selectedPhoneNumbers.length > 1"
                  class="block truncate font-mono text-[9px] font-semibold text-slate-700"
                >
                  {{ selectedPhoneNumbers.at(1)?.number }}
                  <template v-if="selectedPhoneNumbers.length > 2">
                    +{{ selectedPhoneNumbers.length - 2 }}
                  </template>
                </span>
                <span v-else class="block text-[9px] text-slate-400">Click to add number</span>
              </span>
            </button>
          </article>

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

        <div
          class="sticky bottom-0 z-20 mt-6 flex w-full justify-end gap-2 border-t border-slate-200 bg-slate-50/95 pt-2 backdrop-blur-sm"
        >
          <button
            type="button"
            class="h-8 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 hover:bg-slate-50"
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
      </section>

      <aside class="xl:sticky xl:top-3 xl:self-start">
        <CallflowActionPalette
          compact
          root-only
          enabled
          drag-enabled
          @choose="selectRootAction"
          @action-drag-start="beginRootActionDrag"
          @action-drag-end="endRootActionDrag"
        />
      </aside>
    </div>
  </form>

  <CallflowNodeInfoDialog
    :open="metadataOpen"
    title="Callflow"
    breadcrumb="New route / Name and numbers"
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
      <section
        class="overflow-hidden rounded-md border"
        :class="[
          validationControlClass(fieldError('phone_number_ids')),
          fieldError('phone_number_ids') ? 'border-red-400' : 'border-slate-200',
        ]"
      >
        <header class="border-b border-slate-200 bg-slate-50 px-4 py-3">
          <h3 class="text-xs font-semibold text-slate-700">Phone-number entry points</h3>
          <p class="mt-1 text-[10px] text-slate-500">Choose at least one available number.</p>
        </header>
        <div v-if="editor.phone_numbers.length" class="max-h-72 divide-y divide-slate-100">
          <FormCheckbox
            v-for="phoneNumber in editor.phone_numbers"
            :key="phoneNumber.id"
            :model-value="form.phone_number_ids"
            :value="phoneNumber.id"
            :label="phoneNumber.number"
            :description="
              phoneNumber.available
                ? phoneNumber.state?.replaceAll('_', ' ') || 'Available'
                : `Assigned to ${phoneNumber.assigned_callflow?.name ?? 'another route'}`
            "
            :disabled="!phoneNumber.available"
            variant="row"
            @update:model-value="form.phone_number_ids = $event as string[]"
          />
        </div>
        <p v-else class="p-4 text-xs text-slate-500">
          No projected phone numbers are available for this account.
        </p>
        <p v-if="fieldError('phone_number_ids')" class="px-4 pb-3 text-[10px] text-danger">
          {{ fieldError('phone_number_ids') }}
        </p>
      </section>
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

  <CallflowNodeInfoDialog
    :open="actionOpen && selectedAction !== null && selectedAction.module !== 'ring_group'"
    :title="selectedAction ? `Configure ${selectedAction.label}` : 'Configure action'"
    breadcrumb="New route / Root action"
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
      <p class="text-[10px] leading-4 text-slate-500">
        Additional branches and inline actions become available on the visual canvas immediately
        after this callflow is created in Switch.
      </p>
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

  <CallflowInlineNodeEditorPanel
    v-if="actionOpen && selectedAction?.module === 'ring_group'"
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
