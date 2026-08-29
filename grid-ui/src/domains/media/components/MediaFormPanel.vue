<script setup lang="ts">
import { computed } from 'vue'
import { MusicalNoteIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
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
const { form, validate, validationErrors } = useMediaForm(props.mode, props.record ?? null)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
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
