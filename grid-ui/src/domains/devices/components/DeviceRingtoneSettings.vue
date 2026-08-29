<script setup lang="ts">
import { MusicalNoteIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import type { DeviceConfiguration } from '../types/device'

const props = defineProps<{ fieldErrors: Record<string, string[]> }>()
const ringtones = defineModel<DeviceConfiguration['ringtones']>({ required: true })
const ringtoneFields = ['internal', 'external'] as const

function error(field: 'internal' | 'external'): string | null {
  return props.fieldErrors[`ringtones.${field}`]?.[0] ?? null
}
</script>

<template>
  <section class="grid gap-4 border-t border-slate-100 pt-5 sm:col-span-2">
    <div class="flex items-center gap-2 sm:col-span-2">
      <MusicalNoteIcon class="size-4 text-brand-500" />
      <div>
        <h3 class="text-xs font-semibold text-slate-700">Ringtone headers</h3>
        <p class="mt-0.5 text-[10px] text-slate-400">
          Optional Alert-Info values sent to endpoints for internal and external calls.
        </p>
      </div>
    </div>

    <FormInput
      v-for="field in ringtoneFields"
      :key="field"
      v-model="ringtones[field]"
      :label="`${field.charAt(0).toUpperCase()}${field.slice(1)} ringtone`"
      maxlength="256"
      :placeholder="field === 'internal' ? 'Internal-ring' : 'External-ring'"
      :error="error(field)"
    >
      <template #trailing>
        <button
          v-if="ringtones[field]"
          type="button"
          :aria-label="`Clear ${field} ringtone`"
          class="absolute inset-y-0 right-1 grid w-8 place-items-center text-slate-400 hover:text-slate-700"
          @click="ringtones[field] = null"
        >
          <XMarkIcon class="size-4" />
        </button>
      </template>
    </FormInput>
  </section>
</template>
