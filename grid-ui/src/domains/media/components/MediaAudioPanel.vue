<script setup lang="ts">
import { computed } from 'vue'
import { ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormFileInput from '@/shared/components/FormFileInput.vue'
import { useMediaAudioForm } from '../composables/useMediaAudioForm'

const props = defineProps<{
  name: string
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; save: [audio: File] }>()
const { audio, validate, validationErrors } = useMediaAudioForm()
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const audioError = computed(() => errors.value.audio?.[0] ?? null)
function submit(): void {
  const result = validate()
  if (result.success) emit('save', result.data.audio)
}
</script>

<template>
  <CrudSlideOver
    title="Replace media audio"
    eyebrow="GridPBX / Media"
    :description="`Upload a new audio file for ${name}. Metadata remains unchanged.`"
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface p-5">
        <span class="grid size-11 place-items-center rounded-md bg-brand-50 text-brand-600"
          ><ArrowUpTrayIcon class="size-5"
        /></span>
        <h2 class="mt-4 text-sm font-semibold text-slate-700">New audio file</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">
          The Switch replaces the binary. MySQL stores only the refreshed content metadata.
        </p>
        <FormFileInput
          v-model="audio"
          label="Replacement audio file"
          class="mt-5"
          accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
          :error="audioError"
        />
      </article>
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
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Replacing…' : 'Replace audio' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
