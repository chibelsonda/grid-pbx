<script setup lang="ts">
import { computed } from 'vue'
import { PlusIcon, TrashIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import DisclosureCard from '@/shared/components/DisclosureCard.vue'
import { useLineKeyForm } from '../composables/useLineKeyForm'
import type { LineKeyInput, LineKeyPreview, LineKeyType } from '../types/lineKey'

const props = defineProps<{
  preview: LineKeyPreview
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [keys: LineKeyInput[]] }>()
const { add, form, remove, safePreview, validate, validationErrors } = useLineKeyForm(props.preview)
const keyTypes: { value: LineKeyType; label: string }[] = [
  { value: 'line', label: 'Line' },
  { value: 'presence', label: 'Presence / BLF' },
  { value: 'speed_dial', label: 'Speed dial' },
  { value: 'personal_parking', label: 'Personal parking' },
  { value: 'parking', label: 'Parking slot' },
]
const canApply = computed(() => props.canManage && props.preview.capability.apply_available)
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
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Key assignments</h2>
            <p class="text-[10px] text-slate-400">
              A full replacement of the device's combo and feature key maps.
            </p>
          </div>
          <button
            v-if="canManage"
            type="button"
            class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 px-3 text-[11px] font-semibold text-slate-600"
            @click="add"
          >
            <PlusIcon class="size-4" />Add key
          </button>
        </header>
        <div class="divide-y divide-slate-100">
          <div v-if="!form.length" class="p-8 text-center text-xs text-slate-400">
            No line keys are assigned.
          </div>
          <fieldset
            v-for="(key, index) in form"
            :key="`${key.category}-${key.position}-${index}`"
            :disabled="!canManage"
            class="grid gap-3 p-5 sm:grid-cols-[110px_90px_1fr_1fr_36px] disabled:opacity-70"
          >
            <label class="grid gap-1"
              ><span class="text-[10px] font-semibold text-slate-500">Category</span
              ><FormSelect
                v-model="key.category"
                class="h-9 rounded-md border border-slate-200 px-2 text-xs"
              >
                <option value="combo">Combo</option>
                <option value="feature">Feature</option>
              </FormSelect><span
                v-if="fieldError(index, 'category')"
                class="text-[10px] text-danger"
                >{{ fieldError(index, 'category') }}</span
              ></label
            >
            <label class="grid gap-1"
              ><span class="text-[10px] font-semibold text-slate-500">Position</span
              ><input
                v-model.number="key.position"
                type="number"
                min="0"
                max="999"
                class="h-9 rounded-md border border-slate-200 px-2 text-xs"
              /><span v-if="fieldError(index, 'position')" class="text-[10px] text-danger">{{
                fieldError(index, 'position')
              }}</span></label
            >
            <label class="grid gap-1"
              ><span class="text-[10px] font-semibold text-slate-500">Type</span
              ><FormSelect
                v-model="key.type"
                class="h-9 rounded-md border border-slate-200 px-2 text-xs"
                @change="normalizeKeyValue(key)"
              >
                <option v-for="type in keyTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </FormSelect><span v-if="fieldError(index, 'type')" class="text-[10px] text-danger">{{
                fieldError(index, 'type')
              }}</span></label
            >
            <div class="grid grid-cols-2 gap-2">
              <label class="grid gap-1"
                ><span class="text-[10px] font-semibold text-slate-500">Value</span
                ><input
                  v-if="key.type === 'parking'"
                  v-model.number="key.value"
                  type="number"
                  min="1"
                  max="10"
                  class="h-9 rounded-md border border-slate-200 px-2 text-xs"
                /><input
                  v-else
                  v-model="key.value"
                  maxlength="255"
                  class="h-9 rounded-md border border-slate-200 px-2 text-xs"
                /><span v-if="fieldError(index, 'value')" class="text-[10px] text-danger">{{
                  fieldError(index, 'value')
                }}</span></label
              ><label class="grid gap-1"
                ><span class="text-[10px] font-semibold text-slate-500">Label</span
                ><input
                  v-model="key.label"
                  maxlength="255"
                  class="h-9 rounded-md border border-slate-200 px-2 text-xs"
                /><span v-if="fieldError(index, 'label')" class="text-[10px] text-danger">{{
                  fieldError(index, 'label')
                }}</span></label
              >
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
