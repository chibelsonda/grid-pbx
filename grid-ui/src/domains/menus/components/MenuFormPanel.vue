<script setup lang="ts">
import { computed, ref } from 'vue'
import { Bars3BottomLeftIcon, MusicalNoteIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { useMenuForm } from '../composables/useMenuForm'
import type { Menu, MenuInput, MenuOptions } from '../types/menu'

const props = defineProps<{
  record: Menu | null
  options: MenuOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: MenuInput]; remove: [] }>()
const confirmDelete = ref(false)
const { form, validate, validationErrors } = useMenuForm(props.record)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const mediaOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Switch system prompt' },
  ...props.options.media.map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
])

type MediaIdField = 'greeting_media_id' | 'invalid_media_id' | 'transfer_media_id' | 'exit_media_id'

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function setMediaReference(field: MediaIdField, value: ListboxValue): void {
  if (value === null || typeof value === 'string') form[field] = value
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)
  }
}
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View menu' : record ? 'Edit menu' : 'Create menu'"
    eyebrow="GridPBX / Menus"
    description="Configure an interactive voice menu and its prompts."
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
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><Bars3BottomLeftIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Menu behavior</h2>
              <p class="text-[10px] text-slate-400">
                Digit collection, retries, and direct extension dialing.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <FormInput
              v-model="form.name"
              label="Name"
              class="sm:col-span-2"
              required
              maxlength="128"
              :error="fieldError('name')"
            />
            <FormInput
              v-model.number="form.timeout"
              label="Initial digit timeout (ms)"
              type="number"
              min="1"
              max="60000"
              :error="fieldError('timeout')"
            />
            <FormInput
              v-model.number="form.interdigit_timeout"
              label="Interdigit timeout (ms)"
              type="number"
              min="1"
              max="10000"
              :error="fieldError('interdigit_timeout')"
            />
            <FormInput
              v-model.number="form.max_extension_length"
              label="Maximum digits"
              type="number"
              min="1"
              max="6"
              :error="fieldError('max_extension_length')"
            />
            <FormInput
              v-model.number="form.retries"
              label="Retries"
              type="number"
              min="1"
              max="10"
              :error="fieldError('retries')"
            />
            <FormInput
              v-model="form.record_pin"
              label="Recording PIN"
              inputmode="numeric"
              minlength="3"
              maxlength="6"
              :placeholder="
                record?.record_pin_configured ? 'Configured — enter to replace' : 'Optional'
              "
              description="Write-only; the current PIN is never returned."
              :error="fieldError('record_pin')"
            />
            <div class="grid gap-3 pt-6">
              <ToggleSwitch
                v-model="form.hunt"
                label="Allow extension dialing"
                :class="validationControlClass(fieldError('hunt'))"
                :invalid="Boolean(fieldError('hunt'))"
              />
              <ToggleSwitch
                v-model="form.allow_record_from_offnet"
                label="Allow off-network recording"
                :class="validationControlClass(fieldError('allow_record_from_offnet'))"
                :invalid="Boolean(fieldError('allow_record_from_offnet'))"
              />
              <ToggleSwitch
                v-model="form.suppress_media"
                label="Suppress invalid prompt"
                :class="validationControlClass(fieldError('suppress_media'))"
                :invalid="Boolean(fieldError('suppress_media'))"
              />
            </div>
            <FormInput
              v-model="form.hunt_allow"
              label="Allowed extension pattern"
              maxlength="256"
              placeholder="Optional regular expression"
              :error="fieldError('hunt_allow')"
            />
            <FormInput
              v-model="form.hunt_deny"
              label="Denied extension pattern"
              maxlength="256"
              placeholder="Optional regular expression"
              :error="fieldError('hunt_deny')"
            />
          </div>
        </article>
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <MusicalNoteIcon class="size-5 text-brand-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Prompts</h2>
              <p class="text-[10px] text-slate-400">
                Choose projected media or keep the Switch system prompt.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Greeting</span
              ><FormListbox
                :model-value="form.greeting_media_id"
                :options="mediaOptions"
                aria-label="Greeting media"
                placeholder="No custom greeting"
                :invalid="Boolean(fieldError('greeting_media_id'))"
                @update:model-value="setMediaReference('greeting_media_id', $event)"
              /><span v-if="fieldError('greeting_media_id')" class="text-[10px] text-danger">{{
                fieldError('greeting_media_id')
              }}</span></label
            >
            <label
              v-for="prompt in ['invalid', 'transfer', 'exit'] as const"
              :key="prompt"
              class="grid gap-2 rounded-md border border-slate-200 p-3"
              :class="validationControlClass(fieldError(`${prompt}_media_enabled`))"
              ><span class="text-xs font-semibold text-slate-600 capitalize"
                >{{ prompt }} prompt</span
              ><ToggleSwitch
                v-model="form[`${prompt}_media_enabled`]"
                label="Enabled"
                :invalid="Boolean(fieldError(`${prompt}_media_enabled`))"
              />
              <FormListbox
                :model-value="form[`${prompt}_media_id`]"
                :options="mediaOptions"
                :aria-label="`${prompt} prompt media`"
                :disabled="!form[`${prompt}_media_enabled`]"
                size="small"
                :invalid="Boolean(fieldError(`${prompt}_media_id`))"
                @update:model-value="setMediaReference(`${prompt}_media_id`, $event)"
              /><span v-if="fieldError(`${prompt}_media_id`)" class="text-[10px] text-danger">{{
                fieldError(`${prompt}_media_id`)
              }}</span></label
            >
          </div>
        </article>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete menu
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
          {{ saving ? 'Saving…' : 'Save menu' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete menu"
    description="Delete this menu after checking its call-routing dependencies?"
    confirm-label="Delete menu"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
