<script setup lang="ts">
import { computed } from 'vue'
import { MusicalNoteIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
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
const { form, validate, validationErrors } = useMediaForm(props.mode, props.record ?? null)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function chooseAudio(event: Event): void {
  form.audio = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit(): void {
  const result = validate()
  if (!result.success) return

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
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600">
            <MusicalNoteIcon class="size-5" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Media metadata</h2>
            <p class="text-[10px] text-slate-400">Only public metadata is projected to MySQL.</p>
          </div>
        </header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Name</span>
            <input
              v-model="form.name"
              aria-label="Media name"
              maxlength="128"
              class="field-control"
              :class="validationControlClass(fieldError('name'))"
              :aria-invalid="Boolean(fieldError('name'))"
            />
            <span v-if="fieldError('name')" class="text-[10px] text-danger">{{
              fieldError('name')
            }}</span>
          </label>
          <label class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Description</span>
            <input
              v-model="form.description"
              aria-label="Media description"
              maxlength="128"
              class="field-control"
              :class="validationControlClass(fieldError('description'))"
              :aria-invalid="Boolean(fieldError('description'))"
            />
            <span v-if="fieldError('description')" class="text-[10px] text-danger">{{
              fieldError('description')
            }}</span>
          </label>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Language</span>
            <input
              v-model="form.language"
              aria-label="Media language"
              maxlength="35"
              placeholder="en-us"
              class="field-control"
              :class="validationControlClass(fieldError('language'))"
              :aria-invalid="Boolean(fieldError('language'))"
            />
            <span v-if="fieldError('language')" class="text-[10px] text-danger">{{
              fieldError('language')
            }}</span>
          </label>
          <div class="self-end pb-2">
            <ToggleSwitch
              v-model="form.streamable"
              label="Allow streaming"
              :invalid="Boolean(fieldError('streamable'))"
            />
            <span v-if="fieldError('streamable')" class="mt-2 block text-[10px] text-danger">{{
              fieldError('streamable')
            }}</span>
          </div>
          <label v-if="mode === 'create'" class="grid gap-2 sm:col-span-2">
            <span class="text-xs font-semibold text-slate-600">Audio file</span>
            <input
              aria-label="Media audio file"
              type="file"
              accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
              class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white"
              :class="validationControlClass(fieldError('audio'))"
              :aria-invalid="Boolean(fieldError('audio'))"
              @change="chooseAudio"
            />
            <span class="text-[10px] text-slate-500">MP3, WAV, or OGG; maximum 5 MB.</span>
            <span v-if="fieldError('audio')" class="text-[10px] text-danger">{{
              fieldError('audio')
            }}</span>
          </label>
        </div>
      </article>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
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
