<script setup lang="ts">
import { reactive, ref } from 'vue'
import { CalendarDaysIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
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
const form = reactive<TemporalRuleSetInput>({
  name: props.record?.name ?? '',
  rule_ids:
    props.record?.rules?.flatMap((membership) => (membership.rule ? [membership.rule.id] : [])) ??
    [],
})
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View rule set' : record ? 'Edit rule set' : 'Create rule set'"
    eyebrow="GridPBX / Business hours"
    description="Order schedule rules into one reusable routing target."
    width="medium"
    @close="emit('close')"
  >
    <form
      class="grid gap-5"
      @submit.prevent="canManage && emit('save', { ...form, rule_ids: [...form.rule_ids] })"
    >
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <CalendarDaysIcon class="size-5 text-brand-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Rule set</h2>
              <p class="text-[10px] text-slate-400">Rules are evaluated in the order selected.</p>
            </div>
          </header>
          <div class="grid gap-4 p-5">
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Name</span
              ><input
                v-model="form.name"
                required
                maxlength="128"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                :aria-invalid="Boolean(fieldErrors.name)"
            /></label>
            <div class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Schedule rules</span
              ><label
                v-for="rule in options.rules"
                :key="rule.id"
                class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-100 px-4 py-3 hover:bg-slate-50"
                ><input
                  v-model="form.rule_ids"
                  type="checkbox"
                  :value="rule.id"
                  class="size-4 accent-brand-500"
                  :aria-invalid="Boolean(fieldErrors.rule_ids)"
                /><span
                  ><span class="block text-xs font-semibold text-slate-700">{{ rule.label }}</span
                  ><span class="text-[10px] text-slate-400">{{ rule.detail }}</span></span
                ></label
              >
              <p v-if="!options.rules.length" class="text-xs text-slate-400">
                Create at least one temporal rule first.
              </p>
              <span v-if="fieldErrors.rule_ids" class="text-[10px] text-danger">{{
                fieldErrors.rule_ids[0]
              }}</span>
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
          :disabled="saving || !form.name.trim() || !form.rule_ids.length"
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
