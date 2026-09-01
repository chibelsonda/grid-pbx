<script setup lang="ts">
import { computed, ref } from 'vue'
import { ClockIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useTemporalRuleForm } from '../composables/useTemporalRuleForm'
import TemporalEffectiveStatus from './TemporalEffectiveStatus.vue'
import type {
  TemporalControlAction,
  TemporalCycle,
  TemporalRule,
  TemporalRuleInput,
  Weekday,
} from '../types/temporalRouting'

const props = defineProps<{
  record: TemporalRule | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{
  close: []
  save: [input: TemporalRuleInput]
  remove: []
  control: [action: TemporalControlAction]
}>()
const confirmDelete = ref(false)
const { daysText, form, validate, validationErrors } = useTemporalRuleForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const weekdays: Array<{ value: Weekday; label: string }> = [
  { value: 'monday', label: 'Mon' },
  { value: 'tuesday', label: 'Tue' },
  { value: 'wednesday', label: 'Wed' },
  { value: 'thursday', label: 'Thu' },
  { value: 'friday', label: 'Fri' },
  { value: 'saturday', label: 'Sat' },
  { value: 'sunday', label: 'Sun' },
]
const cycleOptions: ListboxOptionValue[] = [
  { value: 'date', label: 'Specific date' },
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
]
const ordinalOptions: ListboxOptionValue[] = [
  { value: null, label: 'No ordinal' },
  { value: 'every', label: 'Every matching weekday' },
  { value: 'first', label: 'First' },
  { value: 'second', label: 'Second' },
  { value: 'third', label: 'Third' },
  { value: 'fourth', label: 'Fourth' },
  { value: 'fifth', label: 'Fifth' },
  { value: 'last', label: 'Last' },
]

function fieldError(field: string): string | null {
  const direct = errors.value[field]?.[0]
  if (direct) return direct

  return (
    Object.entries(errors.value).find(
      ([key, messages]) => key.startsWith(`${field}.`) && Boolean(messages[0]),
    )?.[1][0] ?? null
  )
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) emit('save', result.data)
}

function setCycle(value: ListboxValue): void {
  if (['date', 'daily', 'weekly', 'monthly', 'yearly'].includes(String(value))) {
    form.cycle = value as TemporalCycle
  }
}

function setOrdinal(value: ListboxValue): void {
  if (
    value === null ||
    ['every', 'first', 'second', 'third', 'fourth', 'fifth', 'last'].includes(String(value))
  ) {
    form.ordinal = value as TemporalRuleInput['ordinal']
  }
}
</script>

<template>
  <CrudSlideOver
    :title="
      !canManage ? 'View temporal rule' : record ? 'Edit temporal rule' : 'Create temporal rule'
    "
    eyebrow="GridPBX / Business hours"
    description="Define when a schedule branch is active."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="error && Object.keys(fieldErrors).length === 0"
        class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
        role="alert"
      >
        {{ error }}
      </div>
      <fieldset :disabled="!canManage || saving" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><ClockIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Schedule rule</h2>
              <p class="text-[10px] text-slate-500">
                Times are seconds after midnight in the account routing timezone.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <FormInput
              v-model="form.name"
              label="Name"
              class="sm:col-span-2"
              maxlength="128"
              :error="fieldError('name')"
            />
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Cycle</span
              ><FormListbox
                :model-value="form.cycle"
                :options="cycleOptions"
                aria-label="Cycle"
                :invalid="Boolean(fieldError('cycle'))"
                :disabled="!canManage"
                @update:model-value="setCycle"
              /><span v-if="fieldError('cycle')" class="text-[10px] text-danger">{{
                fieldError('cycle')
              }}</span></label
            >
            <FormInput
              v-model.number="form.interval"
              label="Every"
              description="Cycle(s)"
              type="number"
              min="1"
              :error="fieldError('interval')"
            />
            <FormInput
              v-model="form.start_date"
              label="Start date"
              class="sm:col-span-2"
              type="date"
              description="Optional recurrence anchor; required only when your routing design needs a fixed start."
              :error="fieldError('start_date')"
            />
            <FormInput
              v-model.number="form.time_window_start"
              label="Window start (seconds)"
              type="number"
              min="0"
              max="86400"
              :error="fieldError('time_window_start')"
            />
            <FormInput
              v-model.number="form.time_window_stop"
              label="Window stop (seconds)"
              type="number"
              min="0"
              max="86400"
              :error="fieldError('time_window_stop')"
            />

            <FormInput
              v-if="['monthly', 'yearly'].includes(form.cycle)"
              v-model="daysText"
              label="Days of month"
              placeholder="1, 15, 31"
              description="Comma- or space-separated values."
              :error="fieldError('days')"
            />
            <FormInput
              v-if="form.cycle === 'yearly'"
              v-model.number="form.month"
              label="Month"
              type="number"
              min="1"
              max="12"
              placeholder="1–12"
              :error="fieldError('month')"
            />
            <label v-if="['monthly', 'yearly'].includes(form.cycle)" class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Ordinal</span
              ><FormListbox
                :model-value="form.ordinal"
                :options="ordinalOptions"
                aria-label="Ordinal"
                :invalid="Boolean(fieldError('ordinal'))"
                :disabled="!canManage"
                @update:model-value="setOrdinal"
              /><span v-if="fieldError('ordinal')" class="text-[10px] text-danger">{{
                fieldError('ordinal')
              }}</span></label
            >

            <div
              v-if="['weekly', 'monthly', 'yearly'].includes(form.cycle)"
              class="grid gap-2 sm:col-span-2"
            >
              <span class="text-xs font-semibold text-slate-600">Weekdays</span>
              <div
                class="flex flex-wrap gap-2 rounded-md border border-slate-300 p-2"
                :class="validationControlClass(fieldError('weekdays'))"
                :aria-invalid="Boolean(fieldError('weekdays'))"
                role="group"
                aria-label="Weekdays"
              >
                <FormCheckbox
                  v-for="day in weekdays"
                  :key="day.value"
                  :model-value="form.weekdays"
                  :value="day.value"
                  :label="day.label"
                  variant="compact"
                  @update:model-value="form.weekdays = $event as Weekday[]"
                />
              </div>
              <span v-if="fieldError('weekdays')" class="text-[10px] text-danger">{{
                fieldError('weekdays')
              }}</span>
              <span
                v-if="['monthly', 'yearly'].includes(form.cycle)"
                class="text-[10px] text-slate-500"
                >Use weekdays with an ordinal for patterns such as “last Friday.” Day numbers and
                ordinal weekdays map directly to the Switch schema.</span
              >
            </div>
          </div>
        </article>
      </fieldset>
      <TemporalEffectiveStatus
        v-if="record"
        :status="record.effective_status"
        subject="rule"
        :can-manage="canManage"
        :busy="saving"
        @control="emit('control', $event)"
      />
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete rule
        </button>
      </div>
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save rule' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete temporal rule"
    description="Remove this rule from rule sets and call routing before deleting it."
    confirm-label="Delete rule"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
