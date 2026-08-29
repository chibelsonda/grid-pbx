<script setup lang="ts">
import { ref } from 'vue'
import { SpeakerWaveIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import type { Media } from '../types/media'

const props = defineProps<{ records: Media[]; saving: boolean; error: string | null }>()
const emit = defineEmits<{ close: []; save: [mediaId: string | null] }>()
const selected = ref(props.records.find((record) => record.is_music_on_hold)?.id ?? '')
const options: ListboxOptionValue[] = [
  { value: null, label: 'No account default' },
  ...props.records.map((record) => ({
    value: record.id,
    label: record.name,
    description: record.language,
  })),
]

function select(value: ListboxValue): void {
  selected.value = typeof value === 'string' ? value : ''
}
</script>

<template>
  <CrudSlideOver
    title="Music on hold"
    eyebrow="GridPBX / Account voice settings"
    description="Choose the default account media callers hear while held."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" novalidate @submit.prevent="emit('save', selected || null)">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface p-5">
        <span class="grid size-11 place-items-center rounded-md bg-violet-50 text-violet-600"
          ><SpeakerWaveIcon class="size-5"
        /></span>
        <h2 class="mt-4 text-sm font-semibold text-slate-700">Account default</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">
          The account stores a reference to the selected Switch media. Audio remains in the Switch.
        </p>
        <label class="mt-5 grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Hold media</span>
          <FormListbox
            :model-value="selected || null"
            :options="options"
            aria-label="Hold media"
            @update:model-value="select"
          />
        </label>
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
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save music on hold' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
