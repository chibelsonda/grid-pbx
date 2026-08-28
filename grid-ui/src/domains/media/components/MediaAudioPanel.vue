<script setup lang="ts">
import { ref } from 'vue'
import { ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'

defineProps<{ name: string; saving: boolean; error: string | null; fieldErrors: Record<string, string[]> }>()
const emit = defineEmits<{ close: []; save: [audio: File] }>()
const audio = ref<File | null>(null)
function choose(event: Event): void {
  audio.value = (event.target as HTMLInputElement).files?.[0] ?? null
}
</script>

<template>
  <CrudSlideOver title="Replace media audio" eyebrow="GridPBX / Media" :description="`Upload a new audio file for ${name}. Metadata remains unchanged.`" width="medium" @close="emit('close')">
    <form class="grid gap-5" @submit.prevent="audio && emit('save', audio)">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
      <article class="card-surface p-5">
        <span class="grid size-11 place-items-center rounded-md bg-brand-50 text-brand-600"><ArrowUpTrayIcon class="size-5" /></span>
        <h2 class="mt-4 text-sm font-semibold text-slate-700">New audio file</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">The Switch replaces the binary. MySQL stores only the refreshed content metadata.</p>
        <input required type="file" accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg" class="mt-5 w-full rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-xs file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white" @change="choose" />
        <span v-if="fieldErrors.audio" class="mt-2 block text-[10px] text-danger">{{ fieldErrors.audio[0] }}</span>
      </article>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button type="button" class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600" @click="emit('close')">Cancel</button>
        <button type="submit" :disabled="saving || !audio" class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50">{{ saving ? 'Replacing…' : 'Replace audio' }}</button>
      </div>
    </form>
  </CrudSlideOver>
</template>
