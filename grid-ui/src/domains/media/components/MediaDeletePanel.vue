<script setup lang="ts">
import { ref } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import type { Media } from '../types/media'

defineProps<{ record: Media; saving: boolean; error: string | null }>()
const emit = defineEmits<{ close: []; confirm: [] }>()
const confirmation = ref('')
</script>

<template>
  <CrudSlideOver
    title="Delete media"
    eyebrow="GridPBX / Media"
    description="Review dependencies before deleting metadata and audio from the Switch."
    width="medium"
    @close="emit('close')"
  >
    <div class="grid gap-5">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface p-5">
        <span class="grid size-11 place-items-center rounded-md bg-red-50 text-danger"
          ><ExclamationTriangleIcon class="size-5"
        /></span>
        <h2 class="mt-4 text-sm font-semibold text-slate-800">Delete {{ record.name }}?</h2>
        <p class="mt-2 text-xs leading-5 text-heading-description">
          This operation is blocked while the media is selected for music on hold, attached as a
          voicemail greeting, or referenced by a projected callflow.
        </p>
        <div
          v-if="record.dependencies && !record.dependencies.can_delete"
          class="mt-4 rounded-md border border-amber-100 bg-amber-50 p-4 text-xs font-semibold text-amber-800"
        >
          Deletion is blocked by {{ record.dependencies.total }} known
          {{ record.dependencies.total === 1 ? 'dependency' : 'dependencies' }}.
        </div>
        <FormInput
          v-else
          v-model="confirmation"
          :label="`Type ${record.name} to confirm`"
          class="mt-5"
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
          type="button"
          :disabled="saving || !record.dependencies?.can_delete || confirmation !== record.name"
          class="h-10 rounded-md bg-red-600 px-5 text-xs font-semibold text-white disabled:opacity-40"
          @click="emit('confirm')"
        >
          {{ saving ? 'Deleting…' : 'Delete media' }}
        </button>
      </div>
    </div>
  </CrudSlideOver>
</template>
