<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, TrashIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { useLineKeyForm } from '../composables/useLineKeyForm'
import type { LineKeyInput, LineKeyPreview, LineKeyType } from '../types/lineKey'

type SlotGroup = {
  key: string
  label: string
  description: string
  start: number
  end: number
  positions?: number[]
}

const props = defineProps<{
  preview: LineKeyPreview
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [keys: LineKeyInput[]] }>()
const { add, canAdd, form, remove, safePreview, validate, validationErrors } = useLineKeyForm(
  props.preview,
)
const categoryOptions: ListboxOptionValue[] = [
  { value: 'combo', label: 'Combo key', description: 'Primary programmable line key' },
  { value: 'feature', label: 'Feature key', description: 'Secondary programmable feature key' },
]
const allKeyTypes: Array<{ value: LineKeyType; label: string; description: string }> = [
  { value: 'line', label: 'Line', description: 'Register or select a line appearance' },
  { value: 'presence', label: 'Presence / BLF', description: 'Monitor another endpoint' },
  { value: 'speed_dial', label: 'Speed dial', description: 'Dial a stored destination' },
  {
    value: 'personal_parking',
    label: 'Personal parking',
    description: 'Personal call parking key',
  },
  { value: 'parking', label: 'Parking slot', description: 'Shared parking slot 1–10' },
]
const keyTypes = computed(() => {
  const supported = props.preview.capability.model.supported_key_types

  return supported.length
    ? allKeyTypes.filter((option) => supported.includes(option.value))
    : allKeyTypes
})
const canApply = computed(() => props.canManage && props.preview.capability.apply_available)
const maximumPosition = computed(() =>
  Math.max(0, (props.preview.capability.model.total_keys ?? 1000) - 1),
)
const slotGroups = computed<SlotGroup[]>(() => {
  const model = props.preview.capability.model

  if (!model.matched || model.max_keys === null) {
    return [
      {
        key: 'assignments',
        label: 'Programmable keys',
        description: 'Model slot metadata is unavailable; up to 100 assignments are supported.',
        start: 0,
        end: 999,
      },
    ]
  }

  const groups: SlotGroup[] = []

  if (model.max_keys > 0) {
    groups.push({
      key: 'main',
      label: 'Main unit',
      description: `Positions 0–${model.max_keys - 1} · ${model.max_keys} keys`,
      start: 0,
      end: model.max_keys - 1,
    })
  }

  if (model.keys_per_expansion_module && model.max_expansion_modules) {
    for (let index = 0; index < model.max_expansion_modules; index += 1) {
      const start = model.max_keys + index * model.keys_per_expansion_module
      const end = start + model.keys_per_expansion_module - 1
      groups.push({
        key: `expansion-${index + 1}`,
        label: `Expansion ${index + 1}`,
        description: `Positions ${start}–${end} · ${model.keys_per_expansion_module} keys`,
        start,
        end,
      })
    }
  }

  const unmappedPositions = [
    ...new Set(
      form
        .map((key) => key.position)
        .filter(
          (position) => !groups.some((group) => position >= group.start && position <= group.end),
        ),
    ),
  ].sort((left, right) => left - right)

  if (unmappedPositions.length > 0) {
    groups.push({
      key: 'unmapped',
      label: 'Unmapped assignments',
      description:
        'These saved positions fall outside the current model metadata. Move or remove them.',
      start: 0,
      end: -1,
      positions: unmappedPositions,
    })
  }

  return groups.length
    ? groups
    : [
        {
          key: 'none',
          label: 'Programmable keys',
          description: 'The selected model does not expose programmable line-key slots.',
          start: 0,
          end: -1,
        },
      ]
})
const valueChoiceOptions = computed<ListboxOptionValue[]>(() => [
  { value: '', label: 'Select a suggested value' },
  ...props.preview.value_choices.map((choice) => ({
    value: choice.value,
    label: choice.label,
    description: `${choice.source === 'extensions' ? 'Extension' : 'Device'}${choice.description ? ` · ${choice.description}` : ''}`,
  })),
])
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(index: number, field: string): string | null {
  return errors.value[`line_keys.${index}.${field}`]?.[0] ?? null
}

function formError(): string | null {
  return errors.value.line_keys?.[0] ?? null
}

function normalizeKeyValue(key: LineKeyInput): void {
  if (key.type !== 'parking' && typeof key.value === 'number') {
    key.value = String(key.value)
  }
}

function setCategory(key: LineKeyInput, value: ListboxValue): void {
  if (value === 'combo' || value === 'feature') key.category = value
}

function setType(key: LineKeyInput, value: ListboxValue): void {
  if (keyTypes.value.some((option) => option.value === value)) {
    key.type = value as LineKeyType
    normalizeKeyValue(key)
  }
}

function setSuggestedValue(key: LineKeyInput, value: ListboxValue): void {
  if (typeof value === 'string' && value !== '') key.value = value
}

function suggestedValue(key: LineKeyInput): ListboxValue {
  return typeof key.value === 'string' &&
    props.preview.value_choices.some((choice) => choice.value === key.value)
    ? key.value
    : ''
}

function isInGroup(position: number, group: SlotGroup): boolean {
  if (group.positions) return group.positions.includes(position)

  return position >= group.start && position <= group.end
}

function groupAssignmentCount(group: SlotGroup): number {
  return form.filter((key) => isInGroup(key.position, group)).length
}

function canAddToGroup(group: SlotGroup): boolean {
  return (
    !group.positions &&
    canAdd.value &&
    group.end >= group.start &&
    groupAssignmentCount(group) < group.end - group.start + 1
  )
}

function addToGroup(group: SlotGroup): void {
  add(group.start, group.end)
}

function positionLocation(position: number): string | null {
  const model = props.preview.capability.model

  if (!model.matched || model.max_keys === null) return null
  if (position < model.max_keys) return 'Main unit'
  if (!model.keys_per_expansion_module) return null

  const module = Math.floor((position - model.max_keys) / model.keys_per_expansion_module) + 1

  return module <= (model.max_expansion_modules ?? 0) ? `Expansion ${module}` : null
}

function submit(): void {
  if (!canApply.value) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)
  }
}
</script>

<template>
  <CrudSlideOver
    title="Line keys"
    eyebrow="GridPBX / Device provisioning"
    :description="preview.device.name ?? 'Unnamed device'"
    width="wide"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <div
        v-if="formError()"
        class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
      >
        {{ formError() }}
      </div>
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
            ><WrenchScrewdriverIcon class="size-5"
          /></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Provisioning identity</h2>
            <p class="text-[10px] text-slate-400">
              {{
                [preview.device.make, preview.device.endpoint_family, preview.device.model]
                  .filter(Boolean)
                  .join(' / ') || 'Not configured'
              }}
            </p>
          </div>
        </header>
        <div class="grid gap-3 p-5 sm:grid-cols-3">
          <div>
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Brand</p>
            <p class="mt-1 text-xs font-medium text-slate-700">{{ preview.device.make ?? '—' }}</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Family</p>
            <p class="mt-1 text-xs font-medium text-slate-700">
              {{ preview.device.endpoint_family ?? '—' }}
            </p>
          </div>
          <div>
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Model</p>
            <p class="mt-1 text-xs font-medium text-slate-700">{{ preview.device.model ?? '—' }}</p>
          </div>
        </div>
      </article>

      <div
        v-if="preview.capability.reason"
        class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800"
      >
        {{ preview.capability.reason }}
      </div>
      <article class="card-surface overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Key assignments</h2>
            <p class="text-[10px] text-slate-400">
              <template v-if="preview.capability.model.matched">
                {{ preview.capability.model.max_keys ?? 0 }} main-unit keys
                <template v-if="preview.capability.model.max_expansion_modules">
                  · up to {{ preview.capability.model.max_expansion_modules }} expansion modules ·
                  {{ preview.capability.model.keys_per_expansion_module ?? 0 }} keys each
                </template>
              </template>
              <template v-else
                >A full replacement of the device's combo and feature key maps.</template
              >
            </p>
          </div>
        </header>
        <div class="divide-y divide-slate-100">
          <section v-for="group in slotGroups" :key="group.key">
            <header class="flex items-center justify-between bg-slate-50 px-5 py-3">
              <div>
                <h3 class="text-xs font-semibold text-slate-700">{{ group.label }}</h3>
                <p class="text-[10px] text-slate-400">{{ group.description }}</p>
              </div>
              <button
                v-if="canManage"
                type="button"
                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-600"
                :disabled="!canAddToGroup(group)"
                :class="{ 'cursor-not-allowed opacity-50': !canAddToGroup(group) }"
                @click="addToGroup(group)"
              >
                <PlusIcon class="size-4" />Add key
              </button>
            </header>
            <div class="divide-y divide-slate-100">
              <div
                v-if="groupAssignmentCount(group) === 0"
                class="p-6 text-center text-xs text-slate-400"
              >
                No keys are assigned to this hardware section.
              </div>
              <template
                v-for="(key, index) in form"
                :key="`${group.key}-${key.category}-${key.position}-${index}`"
              >
                <fieldset
                  v-if="isInGroup(key.position, group)"
                  :disabled="!canManage"
                  class="grid gap-3 p-5 sm:grid-cols-[110px_90px_1fr_1fr_36px] disabled:opacity-70"
                >
                  <label class="grid gap-1"
                    ><span class="text-[10px] font-semibold text-slate-500">Category</span
                    ><FormListbox
                      :model-value="key.category"
                      :options="categoryOptions"
                      size="small"
                      :invalid="Boolean(fieldError(index, 'category'))"
                      @update:model-value="setCategory(key, $event)"
                    /><span v-if="fieldError(index, 'category')" class="text-[10px] text-danger">{{
                      fieldError(index, 'category')
                    }}</span></label
                  >
                  <FormInput
                    v-model.number="key.position"
                    label="Position"
                    type="number"
                    min="0"
                    :max="maximumPosition"
                    :description="positionLocation(key.position)"
                    input-class="h-9 px-2"
                    :error="fieldError(index, 'position')"
                  />
                  <label class="grid gap-1"
                    ><span class="text-[10px] font-semibold text-slate-500">Type</span
                    ><FormListbox
                      :model-value="key.type"
                      :options="keyTypes"
                      size="small"
                      :invalid="Boolean(fieldError(index, 'type'))"
                      @update:model-value="setType(key, $event)"
                    /><span v-if="fieldError(index, 'type')" class="text-[10px] text-danger">{{
                      fieldError(index, 'type')
                    }}</span></label
                  >
                  <div class="grid grid-cols-2 gap-2">
                    <label
                      v-if="key.type !== 'parking' && valueChoiceOptions.length > 1"
                      class="col-span-2 grid gap-1"
                      ><span class="text-[10px] font-semibold text-slate-500">Suggested value</span
                      ><FormListbox
                        :model-value="suggestedValue(key)"
                        :options="valueChoiceOptions"
                        size="small"
                        @update:model-value="setSuggestedValue(key, $event)"
                      />
                      <span class="text-[10px] text-slate-400"
                        >Account-scoped choices from the model's allowlisted value sources.</span
                      ></label
                    >
                    <FormInput
                      v-if="key.type === 'parking'"
                      v-model.number="key.value"
                      label="Value"
                      type="number"
                      min="1"
                      max="10"
                      input-class="h-9 px-2"
                      :error="fieldError(index, 'value')"
                    />
                    <FormInput
                      v-else
                      v-model="key.value"
                      label="Value"
                      maxlength="255"
                      input-class="h-9 px-2"
                      :error="fieldError(index, 'value')"
                    />
                    <FormInput
                      v-model="key.label"
                      label="Label"
                      maxlength="255"
                      input-class="h-9 px-2"
                      :error="fieldError(index, 'label')"
                    />
                  </div>
                  <button
                    v-if="canManage"
                    type="button"
                    class="mt-5 grid size-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-danger"
                    aria-label="Remove key"
                    @click="remove(index)"
                  >
                    <TrashIcon class="size-4" />
                  </button>
                </fieldset>
              </template>
            </div>
          </section>
        </div>
      </article>

      <DisclosureCard title="Safe Switch payload preview">
        <p class="text-[10px] text-slate-400">
          Only the line-key provisioning subtree is shown. SIP credentials and provisioning
          infrastructure are never included.
        </p>
        <pre
          class="mt-3 max-h-64 overflow-auto rounded-md bg-slate-950 p-4 text-[10px] text-slate-200"
          >{{ JSON.stringify(safePreview, null, 2) }}</pre>
      </DisclosureCard>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Close</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="saving || !canApply"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-40"
        >
          {{ saving ? 'Applying…' : 'Apply to device' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
