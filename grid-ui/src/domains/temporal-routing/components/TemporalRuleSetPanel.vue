<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowDownIcon, ArrowUpIcon, CalendarDaysIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useTemporalRuleSetForm } from '../composables/useTemporalRuleSetForm'
import TemporalEffectiveStatus from './TemporalEffectiveStatus.vue'
import type {
  TemporalControlAction,
  TemporalOptions,
  TemporalRuleSet,
  TemporalRuleSetInput,
} from '../types/temporalRouting'

const props = defineProps<{
  record: TemporalRuleSet | null
  options: TemporalOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{
  close: []
  save: [input: TemporalRuleSetInput]
  remove: []
  control: [action: TemporalControlAction]
}>()
const confirmDelete = ref(false)
const { form, moveRule, validate, validationErrors } = useTemporalRuleSetForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const selectedRules = computed(() =>
  form.rule_ids.flatMap((id) => {
    const option = props.options.rules.find((rule) => rule.id === id)

    return option ? [option] : []
  }),
)

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
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View rule set' : record ? 'Edit rule set' : 'Create rule set'"
    eyebrow="GridPBX / Business hours"
    description="Order schedule rules into one reusable routing target."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the temporal rule set"
      />
      <fieldset :disabled="!canManage || saving" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <CalendarDaysIcon class="size-5 text-brand-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Rule set</h2>
              <p class="text-[10px] text-slate-500">
                Rules are evaluated in the order shown below.
              </p>
            </div>
          </header>
          <div class="grid gap-5 p-5">
            <FormInput
              v-model="form.name"
              label="Name"
              maxlength="128"
              :error="fieldError('name')"
            />

            <div class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Available schedule rules</span>
              <div
                class="grid gap-2 rounded-md border border-slate-300 p-2"
                :class="validationControlClass(fieldError('rule_ids'))"
                :aria-invalid="Boolean(fieldError('rule_ids'))"
                role="group"
                aria-label="Available schedule rules"
              >
                <FormCheckbox
                  v-for="rule in options.rules"
                  :key="rule.id"
                  :model-value="form.rule_ids"
                  :value="rule.id"
                  :label="rule.label"
                  :description="rule.detail"
                  @update:model-value="form.rule_ids = $event as string[]"
                />
                <p v-if="!options.rules.length" class="p-2 text-xs text-slate-500">
                  Create at least one temporal rule first.
                </p>
              </div>
              <span v-if="fieldError('rule_ids')" class="text-[10px] text-danger">{{
                fieldError('rule_ids')
              }}</span>
            </div>

            <div v-if="selectedRules.length" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Evaluation order</span>
              <ol class="overflow-hidden rounded-md border border-slate-300 bg-white">
                <li
                  v-for="(rule, index) in selectedRules"
                  :key="rule.id"
                  class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 last:border-b-0"
                >
                  <span
                    class="grid size-6 shrink-0 place-items-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600"
                    >{{ index + 1 }}</span
                  >
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-slate-700">{{
                      rule.label
                    }}</span>
                    <span class="block truncate text-[10px] text-slate-500">{{ rule.detail }}</span>
                  </span>
                  <span v-if="canManage" class="flex gap-1">
                    <button
                      type="button"
                      :aria-label="`Move ${rule.label} earlier`"
                      :disabled="index === 0"
                      class="grid size-8 place-items-center rounded-md border border-slate-300 text-slate-600 disabled:cursor-not-allowed disabled:opacity-35"
                      @click="moveRule(rule.id, -1)"
                    >
                      <ArrowUpIcon class="size-4" />
                    </button>
                    <button
                      type="button"
                      :aria-label="`Move ${rule.label} later`"
                      :disabled="index === selectedRules.length - 1"
                      class="grid size-8 place-items-center rounded-md border border-slate-300 text-slate-600 disabled:cursor-not-allowed disabled:opacity-35"
                      @click="moveRule(rule.id, 1)"
                    >
                      <ArrowDownIcon class="size-4" />
                    </button>
                  </span>
                </li>
              </ol>
            </div>
          </div>
        </article>
      </fieldset>
      <TemporalEffectiveStatus
        v-if="record"
        :status="record.effective_status"
        subject="rule set"
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
          <TrashIcon class="size-4" />Delete rule set
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
          {{ saving ? 'Saving…' : 'Save rule set' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete temporal rule set"
    description="Remove this rule set from call routing before deleting it."
    confirm-label="Delete rule set"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
