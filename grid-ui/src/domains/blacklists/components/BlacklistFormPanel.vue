<script setup lang="ts">
import { computed, ref } from 'vue'
import { ShieldExclamationIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { useBlacklistForm } from '../composables/useBlacklistForm'
import type { Blacklist, BlacklistInput } from '../types/blacklist'

const props = defineProps<{
  record: Blacklist | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: BlacklistInput]; remove: [] }>()
const confirmDelete = ref(false)
const { form, invalidNumbers, uniqueNumbers, validate, validationErrors } = useBlacklistForm(
  props.record,
)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

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
    :title="!canManage ? 'View blacklist' : record ? 'Edit blacklist' : 'Create blacklist'"
    eyebrow="GridPBX / Call protection"
    description="Block inbound callers at the account boundary."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-red-50 text-red-600"
              ><ShieldExclamationIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Inbound call protection</h2>
              <p class="text-[10px] text-slate-400">
                Activation is an account setting and is synchronized separately.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5">
            <FormInput
              v-model="form.name"
              label="Name"
              aria-label="Blacklist name"
              maxlength="128"
              required
              :error="fieldError('name')"
            />
            <FormTextarea
              v-model="form.numbersText"
              label="Blocked caller numbers"
              rows="10"
              size="large"
              textarea-class="font-mono"
              placeholder="+15550001000&#10;+15550001001"
              :description="`One E.164 number per line. ${uniqueNumbers.length} unique number${uniqueNumbers.length === 1 ? '' : 's'}.`"
              :error="
                invalidNumbers.length
                  ? `Use E.164 format for: ${invalidNumbers.join(', ')}`
                  : fieldError('numbers')
              "
            />
            <ToggleSwitch
              v-model="form.should_block_anonymous"
              label="Block anonymous callers"
              description="Reject callers whose identity is withheld or unavailable."
              class="rounded-md border border-slate-100 p-4"
              :invalid="Boolean(fieldError('should_block_anonymous'))"
            />
            <p v-if="fieldError('should_block_anonymous')" class="text-[10px] text-danger">
              {{ fieldError('should_block_anonymous') }}
            </p>
            <ToggleSwitch
              v-model="form.is_active"
              label="Active for this account"
              description="Adds this blacklist ID to the Switch account's inbound enforcement list."
              class="rounded-md border border-brand-100 bg-brand-50/50 p-4"
              :invalid="Boolean(fieldError('is_active'))"
            />
            <p v-if="fieldError('is_active')" class="text-[10px] text-danger">
              {{ fieldError('is_active') }}
            </p>
          </div>
        </article>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete blacklist
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
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save blacklist' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete blacklist"
    :description="
      record?.is_active
        ? 'Deactivate and save this blacklist before deleting it.'
        : 'Delete this caller list? This cannot be undone.'
    "
    confirm-label="Delete blacklist"
    :busy="saving"
    :disabled="record?.is_active ?? false"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
