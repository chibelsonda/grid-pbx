<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { MusicalNoteIcon } from '@heroicons/vue/24/outline'
import BasicAdvancedFormTabs from '@/shared/components/BasicAdvancedFormTabs.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormErrorSummary from '@/shared/components/FormErrorSummary.vue'
import FormFileInput from '@/shared/components/FormFileInput.vue'
import FormInput from '@/shared/components/FormInput.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { useMediaForm } from '../composables/useMediaForm'
import type { Media, MediaCreate, MediaUpdate } from '../types/media'

const props = defineProps<{
  mode: 'create' | 'edit'
  record?: Media | null
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{
  close: []
  create: [input: MediaCreate]
  update: [input: MediaUpdate]
}>()
const selectedTab = ref(0)
const { form, validate, validationErrors } = useMediaForm(props.mode, props.record ?? null)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const basicFields = new Set(['name', 'description', 'language', 'audio'])

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function hasBasicError(fieldErrors: Record<string, string[]>): boolean {
  return Object.entries(fieldErrors).some(
    ([field, messages]) => Boolean(messages[0]) && basicFields.has(field.split('.')[0] ?? field),
  )
}

watch(
  () => props.fieldErrors,
  (fieldErrors) => {
    if (Object.keys(fieldErrors).length === 0) return
    selectedTab.value = hasBasicError(fieldErrors) ? 0 : 1
  },
  { deep: true },
)

function submit(): void {
  const result = validate()
  if (!result.success) {
    selectedTab.value = hasBasicError(validationErrors.value) ? 0 : 1

    return
  }

  if (props.mode === 'edit') emit('update', result.data as MediaUpdate)
  else emit('create', result.data as MediaCreate)
}
</script>

<template>
  <CrudSlideOver
    :title="mode === 'create' ? 'Upload media' : 'Edit media'"
    eyebrow="GridPBX / Media"
    :description="
      mode === 'create'
        ? 'Create Switch metadata and upload the audio as one managed operation.'
        : 'Update searchable metadata without replacing the audio.'
    "
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <FormErrorSummary
        :error="Object.keys(fieldErrors).length === 0 ? error : null"
        :field-errors="errors"
        title="Unable to save the media file"
      />
      <BasicAdvancedFormTabs v-model="selectedTab">
        <template #basic>
          <article class="card-surface overflow-hidden">
            <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
                <MusicalNoteIcon class="size-5" />
              </span>
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Media metadata</h2>
                <p class="text-[10px] text-slate-400">
                  Only public metadata is projected to MySQL.
                </p>
              </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
              <FormInput
                v-model="form.name"
                label="Name"
                aria-label="Media name"
                class="sm:col-span-2"
                maxlength="128"
                :error="fieldError('name')"
              />
              <FormInput
                v-model="form.description"
                label="Description"
                aria-label="Media description"
                class="sm:col-span-2"
                maxlength="128"
                :error="fieldError('description')"
              />
              <FormInput
                v-model="form.language"
                label="Language"
                aria-label="Media language"
                maxlength="35"
                placeholder="en-us"
                :error="fieldError('language')"
              />
              <FormFileInput
                v-if="mode === 'create'"
                v-model="form.audio"
                label="Audio file"
                aria-label="Media audio file"
                class="sm:col-span-2"
                accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
                description="MP3, WAV, or OGG; maximum 5 MB."
                :error="fieldError('audio')"
              />
            </div>
          </article>
        </template>
        <template #advanced>
          <article class="card-surface overflow-hidden">
            <header class="border-b border-slate-100 px-5 py-4">
              <h2 class="text-sm font-semibold text-slate-700">Playback options</h2>
              <p class="mt-1 text-[10px] text-slate-400">
                Streaming behavior from the installed Media schema.
              </p>
            </header>
            <div class="p-5">
              <ToggleSwitch
                v-model="form.streamable"
                label="Allow streaming"
                :invalid="Boolean(fieldError('streamable'))"
              />
              <span v-if="fieldError('streamable')" class="mt-2 block text-[10px] text-danger">{{
                fieldError('streamable')
              }}</span>
            </div>
          </article>
        </template>
      </BasicAdvancedFormTabs>
      <div class="slide-over-actions flex justify-end gap-3 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : mode === 'create' ? 'Upload media' : 'Save changes' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
