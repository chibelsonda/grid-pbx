<script setup lang="ts">
import { computed } from 'vue'
import { HashtagIcon, IdentificationIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, { type ListboxOptionValue } from '@/shared/components/FormListbox.vue'
import { useCallerIdListForm, type CallerIdEntryMode } from '../composables/useCallerIdListForm'
import type { CallerIdList, CallerIdListInput } from '../types/callerIdList'

const props = defineProps<{
  record: CallerIdList | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{
  close: []
  save: [input: CallerIdListInput]
  requestRemove: []
}>()
const { addEntry, form, removeEntry, setMode, validate, validationErrors } = useCallerIdListForm(
  props.record,
)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const modeOptions: ListboxOptionValue[] = [
  { value: 'number', label: 'Number or prefix' },
  { value: 'pattern', label: 'Regular expression' },
]

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function changeMode(index: number, value: string | number | boolean | null): void {
  if (value === 'number' || value === 'pattern') setMode(index, value as CallerIdEntryMode)
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()
  if (result.success) emit('save', result.data)
}
</script>

<template>
  <CrudSlideOver
    :title="
      !canManage ? 'View Caller-ID List' : record ? 'Edit Caller-ID List' : 'Create Caller-ID List'
    "
    eyebrow="GridPBX / Call routing"
    description="Match caller numbers or safe regular expressions in reusable Callflow branches."
    width="wide"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>

      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
              <IdentificationIcon class="size-5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">List identity</h2>
              <p class="text-[10px] text-slate-500">Metadata stored on the Switch list resource.</p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <FormInput
              v-model="form.name"
              label="Name"
              required
              maxlength="128"
              :error="fieldError('name')"
              class="sm:col-span-2"
            />
            <FormInput
              v-model="form.description"
              label="Description"
              maxlength="128"
              :error="fieldError('description')"
            />
            <FormInput
              v-model="form.organization"
              label="Organization"
              maxlength="255"
              :error="fieldError('organization')"
            />
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-violet-50 text-violet-600">
              <HashtagIcon class="size-5" />
            </span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Match entries</h2>
              <p class="text-[10px] text-slate-500">
                Number entries may be exact values or prefixes. Patterns use Switch regular
                expressions.
              </p>
            </div>
            <div v-if="canManage" class="ml-auto flex gap-2">
              <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600"
                @click="addEntry('number')"
              >
                <PlusIcon class="size-4" />Number
              </button>
              <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600"
                @click="addEntry('pattern')"
              >
                <PlusIcon class="size-4" />Pattern
              </button>
            </div>
          </header>

          <div class="grid gap-3 p-5">
            <p v-if="fieldError('entries')" class="text-[10px] text-danger">
              {{ fieldError('entries') }}
            </p>
            <div
              v-if="form.entries.length === 0"
              class="rounded-md border border-dashed border-slate-300 px-5 py-10 text-center text-xs text-slate-500"
            >
              No match entries yet. An empty list never matches a caller.
            </div>
            <article
              v-for="(entry, index) in form.entries"
              :key="entry.id ?? `new-${index}`"
              class="grid gap-3 rounded-md border border-slate-200 bg-slate-50/50 p-4 sm:grid-cols-[170px_minmax(0,1fr)_minmax(0,1fr)_36px] sm:items-start"
            >
              <div class="grid gap-2">
                <label class="text-xs font-semibold text-slate-600">Match type</label>
                <FormListbox
                  :model-value="entry.mode"
                  :options="modeOptions"
                  :disabled="!canManage"
                  :invalid="
                    Boolean(
                      fieldError(`entries.${index}.number`) ||
                      fieldError(`entries.${index}.pattern`),
                    )
                  "
                  aria-label="Match type"
                  @update:model-value="changeMode(index, $event)"
                />
              </div>
              <FormInput
                v-if="entry.mode === 'number'"
                v-model="entry.number"
                label="Number or prefix"
                placeholder="+1555 or 0123"
                maxlength="32"
                :error="fieldError(`entries.${index}.number`)"
                description="Optional + followed by digits; prefixes are allowed."
              />
              <FormInput
                v-else
                v-model="entry.pattern"
                label="Regular expression"
                placeholder="^\+1555[0-9]+$"
                maxlength="512"
                input-class="font-mono"
                :error="fieldError(`entries.${index}.pattern`)"
                description="Use a bounded expression supported by the Switch."
              />
              <FormInput
                v-model="entry.display_name"
                label="Display name"
                placeholder="Optional label"
                maxlength="128"
                :error="fieldError(`entries.${index}.display_name`)"
              />
              <button
                v-if="canManage"
                type="button"
                class="mt-7 grid size-9 place-items-center rounded-md border border-red-200 bg-white text-danger hover:bg-red-50"
                :aria-label="`Remove entry ${index + 1}`"
                @click="removeEntry(index)"
              >
                <TrashIcon class="size-4" />
              </button>
            </article>
          </div>
        </article>
      </fieldset>

      <div v-if="record && canManage" class="rounded-md border border-red-200 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="emit('requestRemove')"
        >
          <TrashIcon class="size-4" />Delete Caller-ID List
        </button>
      </div>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}
        </button>
        <button
          v-if="canManage"
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save Caller-ID List' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
