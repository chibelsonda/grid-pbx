<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  Bars3BottomLeftIcon,
  MusicalNoteIcon,
  PhoneArrowUpRightIcon,
  Squares2X2Icon,
  TrashIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import BasicAdvancedTabSelector from '@/shared/components/BasicAdvancedTabSelector.vue'
import AdvancedFormTabs from '@/shared/components/AdvancedFormTabs.vue'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
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
const selectedView = ref(0)
const selectedSection = ref(0)
const advancedSections = [
  { key: 'basic', label: 'Basic', icon: Squares2X2Icon },
  { key: 'extension-dialing', label: 'Extension Dialing', icon: PhoneArrowUpRightIcon },
  { key: 'options', label: 'Options', icon: WrenchScrewdriverIcon },
] as const
const basicFields = [
  'name',
  'record_pin',
  'clear_record_pin',
  'hunt',
  'greeting_media_id',
  'clear_greeting_media',
] as const
const extensionDialingFields = ['hunt_allow', 'hunt_deny'] as const
const optionFields = [
  'timeout',
  'interdigit_timeout',
  'max_extension_length',
  'retries',
  'allow_record_from_offnet',
  'suppress_media',
  'invalid_media_enabled',
  'invalid_media_id',
  'clear_invalid_media',
  'transfer_media_enabled',
  'transfer_media_id',
  'clear_transfer_media',
  'exit_media_enabled',
  'exit_media_id',
  'clear_exit_media',
] as const
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
type PromptType = 'invalid' | 'transfer' | 'exit'
type ClearMediaField =
  'clear_greeting_media' | 'clear_invalid_media' | 'clear_transfer_media' | 'clear_exit_media'

const clearFieldByMedia: Record<MediaIdField, ClearMediaField> = {
  greeting_media_id: 'clear_greeting_media',
  invalid_media_id: 'clear_invalid_media',
  transfer_media_id: 'clear_transfer_media',
  exit_media_id: 'clear_exit_media',
}

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function routeToError(errorBag: Record<string, string[]>): void {
  if (basicFields.some((field) => errorBag[field]?.length)) {
    selectedView.value = 0
    selectedSection.value = 0

    return
  }

  selectedView.value = 1
  if (extensionDialingFields.some((field) => errorBag[field]?.length)) {
    selectedSection.value = 1

    return
  }

  selectedSection.value = optionFields.some((field) => errorBag[field]?.length) ? 2 : 0
}

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    if (Object.keys(fieldErrors).length > 0) routeToError(fieldErrors)
  },
  { deep: true, immediate: true },
)

watch(selectedView, (view) => {
  if (view === 0) selectedSection.value = 0
})

function setMediaReference(field: MediaIdField, value: ListboxValue): void {
  if (value === null || typeof value === 'string') {
    form[field] = value
    form[clearFieldByMedia[field]] = value === null && mediaIsUnresolved(field)
  }
}

function mediaIsUnresolved(field: MediaIdField): boolean {
  return props.record?.[field.replace('_id', '_unresolved') as keyof Menu] === true
}

function clearPromptField(prompt: PromptType): ClearMediaField {
  return `clear_${prompt}_media`
}

function setClearPrompt(prompt: PromptType, value: boolean): void {
  form[clearPromptField(prompt)] = value

  if (value) form[`${prompt}_media_id`] = null
}

function submit(): void {
  if (!props.canManage) return
  const result = validate()

  if (result.success) {
    emit('save', result.data)

    return
  }

  routeToError(validationErrors.value)
}
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View menu' : record ? 'Edit menu' : 'Create menu'"
    eyebrow="GridPBX / Menus"
    description="Configure an interactive voice menu and its prompts."
    width="medium"
    @close="confirmDelete ? undefined : emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the menu"
      />
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <BasicAdvancedTabSelector v-model="selectedView" />
        <AdvancedFormTabs
          v-model="selectedSection"
          :tabs="advancedSections"
          aria-label="Menu advanced sections"
          :active="selectedView === 1"
        >
          <div v-show="selectedSection === 0" role="tabpanel" class="grid gap-5">
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
                  ><Bars3BottomLeftIcon class="size-5"
                /></span>
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Menu basics</h2>
                  <p class="text-[10px] text-heading-description">Identity and caller entry points.</p>
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
                  v-model="form.record_pin"
                  label="Recording PIN"
                  :disabled="form.clear_record_pin"
                  inputmode="numeric"
                  minlength="3"
                  maxlength="6"
                  :placeholder="
                    record?.record_pin_configured ? 'Configured — enter to replace' : 'Optional'
                  "
                  description="Write-only; the current PIN is never returned."
                  :error="fieldError('record_pin')"
                />
                <ToggleSwitch
                  v-if="record?.record_pin_configured"
                  v-model="form.clear_record_pin"
                  label="Remove current recording PIN"
                  class="pt-7"
                  :invalid="Boolean(fieldError('clear_record_pin'))"
                  @update:model-value="form.record_pin = null"
                />
                <div class="pt-6">
                  <ToggleSwitch
                    v-model="form.hunt"
                    label="Allow extension dialing"
                    :class="validationControlClass(fieldError('hunt'))"
                    :invalid="Boolean(fieldError('hunt'))"
                  />
                </div>
              </div>
            </article>
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <MusicalNoteIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Prompts</h2>
                  <p class="text-[10px] text-heading-description">
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
                    @update:model-value="setMediaReference('greeting_media_id', $event)" /><span
                    v-if="fieldError('greeting_media_id')"
                    class="text-[10px] text-danger"
                    >{{ fieldError('greeting_media_id') }}</span
                  >
                  <div
                    v-if="record?.greeting_media_unresolved"
                    class="rounded-md border border-amber-200 bg-amber-50 p-3"
                  >
                    <ToggleSwitch
                      v-model="form.clear_greeting_media"
                      label="Replace unavailable greeting with the system prompt"
                      description="The current Switch media is not in this account's projected catalog and remains private."
                      :invalid="Boolean(fieldError('clear_greeting_media'))"
                      @update:model-value="form.greeting_media_id = null"
                    /></div
                ></label>
              </div>
            </article>
          </div>

          <div v-show="selectedSection === 1" role="tabpanel" class="grid gap-5">
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <PhoneArrowUpRightIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Extension dialing</h2>
                  <p class="text-[10px] text-heading-description">
                    Limit which directly dialed extensions may leave the menu.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
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
          </div>

          <div v-show="selectedSection === 2" role="tabpanel" class="grid gap-5">
            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <Bars3BottomLeftIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Menu options</h2>
                  <p class="text-[10px] text-heading-description">
                    Digit timeouts, retries, and recording behavior.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
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
                <ToggleSwitch
                  v-model="form.allow_record_from_offnet"
                  label="Allow off-network recording"
                  :class="validationControlClass(fieldError('allow_record_from_offnet'))"
                  :invalid="Boolean(fieldError('allow_record_from_offnet'))"
                />
                <ToggleSwitch
                  v-model="form.suppress_media"
                  label="Suppress result prompts"
                  description="Disable invalid, transfer, and exit prompts at runtime."
                  :class="validationControlClass(fieldError('suppress_media'))"
                  :invalid="Boolean(fieldError('suppress_media'))"
                />
              </div>
            </article>

            <article class="card-surface overflow-hidden">
              <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <MusicalNoteIcon class="size-5 text-brand-500" />
                <div>
                  <h2 class="text-sm font-semibold text-slate-700">Result prompts</h2>
                  <p class="text-[10px] text-heading-description">
                    Invalid-entry, transfer, and exit prompt behavior.
                  </p>
                </div>
              </header>
              <div class="grid gap-4 p-5 sm:grid-cols-2">
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
                    :disabled="form.suppress_media"
                    :invalid="Boolean(fieldError(`${prompt}_media_enabled`))" />
                  <FormListbox
                    :model-value="form[`${prompt}_media_id`]"
                    :options="mediaOptions"
                    :aria-label="`${prompt} prompt media`"
                    :disabled="form.suppress_media || !form[`${prompt}_media_enabled`]"
                    size="small"
                    :invalid="Boolean(fieldError(`${prompt}_media_id`))"
                    @update:model-value="setMediaReference(`${prompt}_media_id`, $event)" /><span
                    v-if="fieldError(`${prompt}_media_id`)"
                    class="text-[10px] text-danger"
                    >{{ fieldError(`${prompt}_media_id`) }}</span
                  >
                  <ToggleSwitch
                    v-if="record?.[`${prompt}_media_unresolved`]"
                    :model-value="form[clearPromptField(prompt)]"
                    label="Replace unavailable current prompt"
                    description="Use the Switch system prompt instead of the private unresolved media reference."
                    :disabled="form.suppress_media || !form[`${prompt}_media_enabled`]"
                    :invalid="Boolean(fieldError(clearPromptField(prompt)))"
                    @update:model-value="setClearPrompt(prompt, $event)"
                /></label>
              </div>
            </article>
          </div>
        </AdvancedFormTabs>
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
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
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
