<script setup lang="ts">
import { MusicalNoteIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { validationControlClass } from '@/shared/forms/validationStyles'
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

    <label v-for="field in ringtoneFields" :key="field" class="grid gap-2">
      <span class="text-xs font-semibold text-slate-600 capitalize">{{ field }} ringtone</span>
      <span class="relative">
        <input
          v-model="ringtones[field]"
          maxlength="256"
          class="h-10 w-full rounded-md border border-slate-200 px-3 pr-10 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
          :class="validationControlClass(error(field))"
          :aria-invalid="Boolean(error(field))"
          :placeholder="field === 'internal' ? 'Internal-ring' : 'External-ring'"
        />
        <button
          v-if="ringtones[field]"
          type="button"
          :aria-label="`Clear ${field} ringtone`"
          class="absolute inset-y-0 right-1 grid w-8 place-items-center text-slate-400 hover:text-slate-700"
          @click="ringtones[field] = null"
        >
          <XMarkIcon class="size-4" />
        </button>
      </span>
      <span v-if="error(field)" class="text-[11px] text-danger">{{ error(field) }}</span>
    </label>
  </section>
</template>
