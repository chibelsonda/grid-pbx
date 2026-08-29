<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon, MusicalNoteIcon } from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import OrderedStringPriority from '@/shared/components/OrderedStringPriority.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import { endpointAudioCodecs, endpointVideoCodecs } from '@/shared/switch/endpointMedia'
import type { ExtensionUpdate } from '../types/extension'

type Model = Pick<ExtensionUpdate, 'media' | 'music_on_hold' | 'ringtones'>

const props = defineProps<{
  fieldErrors: Record<string, string[]>
  mediaOptions: Array<{ id: string; name: string | null }>
}>()
const settings = defineModel<Model>({ required: true })

function error(path: string): string | null {
  return props.fieldErrors[path]?.[0] ?? null
}

function selectMusicOnHold(value: unknown): void {
  settings.value.music_on_hold.media_id = typeof value === 'string' ? value : null
  settings.value.music_on_hold.preserve_media = false
}

function toggleEncryptionMethod(method: 'srtp' | 'zrtp'): void {
  const methods = settings.value.media.encryption.methods
  settings.value.media.encryption.methods = methods.includes(method)
    ? methods.filter((value) => value !== method)
    : [...methods, method]
}

function setProgressTimeout(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  settings.value.media.progress_timeout = value === '' ? null : Number(value)
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-cyan-50 text-cyan-600">
        <MusicalNoteIcon class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Media and endpoint audio</h2>
        <p class="text-[10px] leading-4 text-slate-500">
          Advanced User media values from the connected Switch schema.
        </p>
      </div>
    </header>

    <div class="grid gap-5 p-5">
      <div class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Music on hold</span>
        <FormListbox
          :model-value="settings.music_on_hold.media_id"
          :options="[
            { value: null, label: 'Inherit account music' },
            ...mediaOptions.map((media) => ({
              value: media.id,
              label: media.name || 'Untitled media',
            })),
          ]"
          :invalid="Boolean(error('music_on_hold.media_id'))"
          aria-label="Select extension music on hold"
          @update:model-value="selectMusicOnHold"
        />
        <span v-if="error('music_on_hold.media_id')" class="text-[10px] text-danger">{{
          error('music_on_hold.media_id')
        }}</span>
        <div
          v-if="settings.music_on_hold.preserve_media"
          class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] leading-4 text-amber-800"
        >
          The current Switch media is not projected locally.
          <ToggleSwitch
            v-model="settings.music_on_hold.preserve_media"
            class="mt-2"
            label="Preserve unresolved music"
          />
        </div>
      </div>

      <Disclosure v-slot="{ open }">
        <DisclosureButton
          class="flex w-full items-center justify-between rounded-md border border-slate-200 px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Codec, transport, and ringtone controls
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-6 border-x border-b border-slate-200 p-4">
          <OrderedStringPriority
            v-model="settings.media.audio.codecs"
            label="Audio codec priority"
            description="The Switch negotiates selected codecs from first priority to last."
            :options="endpointAudioCodecs"
            :error="error('media.audio.codecs')"
          />
          <OrderedStringPriority
            v-model="settings.media.video.codecs"
            label="Video codec priority"
            description="Leave empty when this User has no video codec override."
            :options="endpointVideoCodecs"
            :error="error('media.video.codecs')"
          />

          <div class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Bypass media</span>
              <FormListbox
                v-model="settings.media.bypass_media"
                :invalid="Boolean(error('media.bypass_media'))"
                :options="[
                  { value: false, label: 'Disabled' },
                  { value: true, label: 'Enabled' },
                  { value: 'auto', label: 'Automatic' },
                ]"
              />
              <span v-if="error('media.bypass_media')" class="text-[10px] text-danger">{{
                error('media.bypass_media')
              }}</span>
            </label>
            <ToggleSwitch
              v-model="settings.media.encryption.enforce_security"
              label="Require media encryption"
            />
            <div
              v-if="settings.media.encryption.enforce_security"
              class="grid gap-2 sm:col-span-2"
            >
              <span class="text-xs font-semibold text-slate-600">Encryption methods</span>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="method in (['srtp', 'zrtp'] as const)"
                  :key="method"
                  type="button"
                  :aria-pressed="settings.media.encryption.methods.includes(method)"
                  class="rounded-md border px-3 py-2 text-xs font-semibold uppercase transition"
                  :class="
                    settings.media.encryption.methods.includes(method)
                      ? 'border-brand-500 bg-brand-50 text-brand-700'
                      : 'border-slate-200 text-slate-600 hover:border-slate-300'
                  "
                  @click="toggleEncryptionMethod(method)"
                >
                  {{ method }}
                </button>
              </div>
            </div>
            <ToggleSwitch v-model="settings.media.fax_option" label="Enable T.38 fax" />
            <ToggleSwitch
              v-model="settings.media.ignore_early_media"
              label="Ignore early media"
            />
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Progress timeout (seconds)</span>
              <input
                :value="settings.media.progress_timeout ?? ''"
                type="number"
                min="0"
                max="3600"
                class="field-control"
                :class="validationControlClass(error('media.progress_timeout'))"
                :aria-invalid="Boolean(error('media.progress_timeout'))"
                @input="setProgressTimeout"
              />
              <span v-if="error('media.progress_timeout')" class="text-[10px] text-danger">{{
                error('media.progress_timeout')
              }}</span>
            </label>
          </div>

          <div class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2">
            <label v-for="field in (['internal', 'external'] as const)" :key="field" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600 capitalize">
                {{ field }} ringtone header
              </span>
              <input
                v-model="settings.ringtones[field]"
                maxlength="256"
                class="field-control"
                :class="validationControlClass(error(`ringtones.${field}`))"
                :aria-invalid="Boolean(error(`ringtones.${field}`))"
              />
              <span v-if="error(`ringtones.${field}`)" class="text-[10px] text-danger">{{
                error(`ringtones.${field}`)
              }}</span>
            </label>
          </div>
        </DisclosurePanel>
      </Disclosure>
    </div>
  </article>
</template>
