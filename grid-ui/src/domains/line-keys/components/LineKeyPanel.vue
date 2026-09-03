<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, TrashIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { useLineKeyForm } from '../composables/useLineKeyForm'
import type {
  LineKeyInput,
  LineKeyPreview,
  LineKeyType,
  LineKeyValueChoice,
} from '../types/lineKey'

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
const { add, canAdd, form, orderedAssignments, remove, safePreview, validate, validationErrors } =
  useLineKeyForm(props.preview)
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
function categoryOptionsFor(key: LineKeyInput): ListboxOptionValue[] {
  return key.type === 'line' ? categoryOptions.slice(0, 1) : categoryOptions
}

type SuggestedLineKeyType = LineKeyValueChoice['types'][number]

function isSuggestedLineKeyType(type: LineKeyType): type is SuggestedLineKeyType {
  return type === 'presence' || type === 'personal_parking' || type === 'speed_dial'
}

function valueChoiceOptions(key: LineKeyInput): ListboxOptionValue[] {
  const placeholder =
    key.type === 'presence' || key.type === 'personal_parking'
      ? 'Select an extension'
      : 'Select a suggested destination'

  const type = key.type
  const choices = isSuggestedLineKeyType(type)
    ? props.preview.value_choices.filter((choice) => choice.types.includes(type))
    : []

  return [
    { value: '', label: placeholder },
    ...choices.map((choice) => ({
      value: choice.value,
      label: choice.label,
      description: `Extension${choice.description ? ` · ${choice.description}` : ''}`,
    })),
  ]
}
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(index: number, field: string): string | null {
  return errors.value[`line_keys.${index}.${field}`]?.[0] ?? null
}

function normalizeKeyValue(key: LineKeyInput): void {
  if (key.type !== 'parking' && typeof key.value === 'number') {
    key.value = String(key.value)
  }
}

function setCategory(key: LineKeyInput, value: ListboxValue): void {
  if (value === 'combo' || (value === 'feature' && key.type !== 'line')) key.category = value
}

function setType(key: LineKeyInput, value: ListboxValue): void {
  if (keyTypes.value.some((option) => option.value === value)) {
    if (key.type !== value) {
      key.value = null
      key.label = null
    }

    key.type = value as LineKeyType
    if (key.type === 'line') key.category = 'combo'
    normalizeKeyValue(key)
  }
}

function setSuggestedValue(key: LineKeyInput, value: ListboxValue): void {
  if (typeof value === 'string') key.value = value === '' ? null : value
}

function suggestedValue(key: LineKeyInput): ListboxValue {
  const type = key.type

  if (typeof key.value !== 'string' || !isSuggestedLineKeyType(type)) return ''

  return props.preview.value_choices.some(
    (choice) => choice.value === key.value && choice.types.includes(type),
  )
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
    <form class="grid gap-4" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the line keys"
      />
      <article
        data-testid="line-key-provisioning-identity"
        class="card-surface flex flex-wrap items-center gap-4 px-4 py-3"
      >
        <header class="flex min-w-48 items-center gap-3">
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
        <div class="ml-auto grid min-w-full flex-1 grid-cols-3 gap-4 sm:min-w-0 sm:max-w-xl">
          <div>
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Brand</p>
            <p class="text-xs font-medium text-slate-700">{{ preview.device.make ?? '—' }}</p>
          </div>
          <div>
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Family</p>
            <p class="text-xs font-medium text-slate-700">
              {{ preview.device.endpoint_family ?? '—' }}
            </p>
          </div>
          <div>
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Model</p>
            <p class="text-xs font-medium text-slate-700">{{ preview.device.model ?? '—' }}</p>
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
        <header class="border-b border-slate-100 px-4 py-3">
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
              · Extension selections resolve to Switch presence IDs server-side.
            </p>
          </div>
        </header>
        <div class="divide-y divide-slate-100">
          <section v-for="group in slotGroups" :key="group.key">
            <header
              class="flex items-center justify-between border-l-2 border-brand-400 bg-brand-50/60 px-4 py-2.5"
            >
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
                v-for="{ index, key } in orderedAssignments"
                :key="`${group.key}-${key.category}-${key.position}-${index}`"
              >
                <fieldset
                  v-if="isInGroup(key.position, group)"
                  :disabled="!canManage"
                  data-testid="line-key-assignment"
                  :data-key-category="key.category"
                  :data-key-position="key.position"
                  class="grid items-start gap-3 border-l-2 px-4 py-3 sm:grid-cols-2 lg:grid-cols-[120px_80px_minmax(170px,1fr)_minmax(360px,2fr)_32px] disabled:opacity-70"
                  :class="
                    key.category === 'feature'
                      ? 'border-indigo-400 bg-indigo-50/30'
                      : 'border-sky-500 bg-sky-50/40'
                  "
                >
                  <label class="flex min-w-0 self-start flex-col">
                    <span class="text-xs font-semibold text-slate-600">Category</span>
                    <span class="mt-2">
                      <FormListbox
                        :model-value="key.category"
                        :options="categoryOptionsFor(key)"
                        :aria-label="`Select category for position ${key.position}`"
                        size="small"
                        :invalid="Boolean(fieldError(index, 'category'))"
                        @update:model-value="setCategory(key, $event)"
                      />
                    </span>
                    <span
                      v-if="fieldError(index, 'category')"
                      class="mt-1 text-[10px] leading-4 text-danger"
                    >
                      {{ fieldError(index, 'category') }}
                    </span>
                  </label>
                  <FormInput
                    v-model.number="key.position"
                    class="min-w-0 self-start"
                    label="Position"
                    type="number"
                    min="0"
                    :max="maximumPosition"
                    input-class="h-9 px-2"
                    :error="fieldError(index, 'position')"
                  />
                  <label class="flex min-w-0 self-start flex-col">
                    <span class="text-xs font-semibold text-slate-600">Type</span>
                    <span class="mt-2">
                      <FormListbox
                        :model-value="key.type"
                        :options="keyTypes"
                        :aria-label="`Select type for position ${key.position}`"
                        size="small"
                        :invalid="Boolean(fieldError(index, 'type'))"
                        @update:model-value="setType(key, $event)"
                      />
                    </span>
                    <span
                      v-if="fieldError(index, 'type')"
                      class="mt-1 text-[10px] leading-4 text-danger"
                    >
                      {{ fieldError(index, 'type') }}
                    </span>
                  </label>
                  <div class="grid min-w-0 self-start gap-2 lg:grid-cols-2">
                    <label
                      v-if="
                        key.type !== 'line' &&
                        key.type !== 'parking' &&
                        valueChoiceOptions(key).length > 1
                      "
                      class="flex min-w-0 flex-col"
                    >
                      <span class="text-xs font-semibold text-slate-600">
                        {{
                          key.type === 'presence' || key.type === 'personal_parking'
                            ? 'Extension'
                            : 'Suggested destination'
                        }}
                      </span>
                      <span class="mt-2">
                        <FormListbox
                          :model-value="suggestedValue(key)"
                          :options="valueChoiceOptions(key)"
                          :aria-label="`Select value for position ${key.position}`"
                          size="small"
                          :invalid="
                            (key.type === 'presence' || key.type === 'personal_parking') &&
                            Boolean(fieldError(index, 'value'))
                          "
                          @update:model-value="setSuggestedValue(key, $event)"
                        />
                      </span>
                      <span
                        v-if="
                          (key.type === 'presence' || key.type === 'personal_parking') &&
                          fieldError(index, 'value')
                        "
                        class="mt-1 text-[10px] leading-4 text-danger"
                        >{{ fieldError(index, 'value') }}</span
                      >
                      <span
                        v-else-if="key.type !== 'presence' && key.type !== 'personal_parking'"
                        class="mt-1 text-[10px] leading-4 text-slate-500"
                      >
                        Stores the dialable value; internal IDs remain private.
                      </span>
                    </label>
                    <p
                      v-if="key.type === 'line'"
                      class="self-center text-[10px] leading-4 text-slate-500"
                    >
                      Uses the device's primary account. Switch does not use a custom value or label
                      for a line appearance.
                    </p>
                    <p
                      v-else-if="
                        (key.type === 'presence' || key.type === 'personal_parking') &&
                        valueChoiceOptions(key).length === 1
                      "
                      class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] text-amber-800"
                    >
                      No synchronized extensions are available. Synchronize People & Extensions
                      before assigning this key type.
                    </p>
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
                      v-else-if="key.type === 'speed_dial'"
                      v-model="key.value"
                      label="Dialable destination"
                      maxlength="255"
                      input-class="h-9 px-2"
                      :error="fieldError(index, 'value')"
                    />
                    <FormInput
                      v-if="key.type !== 'line'"
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
                    class="mt-5 grid size-8 place-items-center justify-self-end rounded-md text-danger transition hover:bg-red-50 hover:text-red-700 focus-visible:bg-red-50 focus-visible:text-red-700"
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
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
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
