<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ClockIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import TemporalEffectiveStatus from './TemporalEffectiveStatus.vue'
import type {
  TemporalControlAction,
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
const weekdays: Array<{ value: Weekday; label: string }> = [
  { value: 'monday', label: 'Mon' },
  { value: 'tuesday', label: 'Tue' },
  { value: 'wednesday', label: 'Wed' },
  { value: 'thursday', label: 'Thu' },
  { value: 'friday', label: 'Fri' },
  { value: 'saturday', label: 'Sat' },
  { value: 'sunday', label: 'Sun' },
]
const form = reactive<TemporalRuleInput>({
  name: props.record?.name ?? '',
  cycle: props.record?.cycle ?? 'weekly',
  interval: props.record?.interval ?? 1,
  start_date: props.record?.start_date ?? null,
  time_window_start: props.record?.time_window_start ?? 32400,
  time_window_stop: props.record?.time_window_stop ?? 61200,
  enabled: props.record?.enabled ?? null,
  days: [...(props.record?.days ?? [])],
  weekdays: [
    ...(props.record?.weekdays ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
  ],
  month: props.record?.month ?? null,
  ordinal: props.record?.ordinal ?? null,
})
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
    <form
      class="grid gap-5"
      @submit.prevent="
        canManage && emit('save', { ...form, days: [...form.days], weekdays: [...form.weekdays] })
      "
    >
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><ClockIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Schedule rule</h2>
              <p class="text-[10px] text-slate-400">
                Times are seconds after midnight in the routing timezone.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Name</span
              ><input
                v-model="form.name"
                required
                maxlength="128"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                :aria-invalid="Boolean(fieldErrors.name)"
              /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{
                fieldErrors.name[0]
              }}</span></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Cycle</span
              ><FormSelect
                v-model="form.cycle"
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
                ><option value="date">Specific date</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option></FormSelect
              ></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Every</span>
              <div class="flex items-center gap-2">
                <input
                  v-model.number="form.interval"
                  type="number"
                  min="1"
                  max="365"
                  class="h-10 min-w-0 flex-1 rounded-md border border-slate-200 px-3 text-xs"
                /><span class="text-xs text-slate-400">cycle(s)</span>
              </div></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Start date</span
              ><input
                v-model="form.start_date"
                type="date"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Window start (seconds)</span
              ><input
                v-model.number="form.time_window_start"
                type="number"
                min="0"
                max="86400"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Window stop (seconds)</span
              ><input
                v-model.number="form.time_window_stop"
                type="number"
                min="0"
                max="86400"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <div v-if="form.cycle === 'weekly'" class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Weekdays</span>
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="day in weekdays"
                  :key="day.value"
                  class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs"
                  ><input
                    v-model="form.weekdays"
                    type="checkbox"
                    :value="day.value"
                    class="accent-brand-500"
                  />{{ day.label }}</label
                >
              </div>
            </div>
            <label v-if="['monthly', 'yearly'].includes(form.cycle)" class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Days of month</span
              ><input
                :value="form.days.join(',')"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                placeholder="1,15,31"
                @input="
                  form.days = ($event.target as HTMLInputElement).value
                    .split(',')
                    .map(Number)
                    .filter((day) => day >= 1 && day <= 31)
                "
            /></label>
            <label v-if="form.cycle === 'yearly'" class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Month</span
              ><input
                v-model.number="form.month"
                type="number"
                min="1"
                max="12"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
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
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="saving || !form.name.trim()"
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
