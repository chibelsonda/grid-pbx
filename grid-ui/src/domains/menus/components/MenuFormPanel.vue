<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Bars3BottomLeftIcon, MusicalNoteIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Menu, MenuInput, MenuOptions } from '../types/menu'

const props = defineProps<{
  record: Menu | null
  options: MenuOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: MenuInput]; remove: [] }>()
const confirmDelete = ref(false)
const form = reactive<MenuInput>({
  name: props.record?.name ?? '',
  timeout: props.record?.timeout ?? 10000,
  interdigit_timeout: props.record?.interdigit_timeout ?? 2000,
  max_extension_length: props.record?.max_extension_length ?? 4,
  retries: props.record?.retries ?? 3,
  hunt: props.record?.hunt ?? true,
  allow_record_from_offnet: props.record?.allow_record_from_offnet ?? false,
  suppress_media: props.record?.suppress_media ?? false,
  record_pin: null,
  hunt_allow: props.record?.hunt_allow ?? null,
  hunt_deny: props.record?.hunt_deny ?? null,
  greeting_media_id: props.record?.greeting_media?.id ?? null,
  invalid_media_enabled: props.record?.invalid_media_enabled ?? true,
  invalid_media_id: props.record?.invalid_media?.id ?? null,
  transfer_media_enabled: props.record?.transfer_media_enabled ?? true,
  transfer_media_id: props.record?.transfer_media?.id ?? null,
  exit_media_enabled: props.record?.exit_media_enabled ?? true,
  exit_media_id: props.record?.exit_media?.id ?? null,
})
</script>

<template>
  <CrudSlideOver
    :title="!canManage ? 'View menu' : record ? 'Edit menu' : 'Create menu'"
    eyebrow="GridPBX / Menus"
    description="Configure an interactive voice menu and its prompts."
    width="medium"
    @close="emit('close')"
  >
    <form class="grid gap-5" @submit.prevent="canManage && emit('save', { ...form })">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"
              ><Bars3BottomLeftIcon class="size-5"
            /></span>
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Menu behavior</h2>
              <p class="text-[10px] text-slate-400">
                Digit collection, retries, and direct extension dialing.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Name</span
              ><input
                v-model="form.name"
                required
                maxlength="128"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                :aria-invalid="Boolean(fieldErrors.name)"
              /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{
                fieldErrors.name[0]
              }}</span></label
            >
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Initial digit timeout (ms)</span
              ><input
                v-model.number="form.timeout"
                type="number"
                min="1"
                max="60000"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Interdigit timeout (ms)</span
              ><input
                v-model.number="form.interdigit_timeout"
                type="number"
                min="1"
                max="10000"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Maximum digits</span
              ><input
                v-model.number="form.max_extension_length"
                type="number"
                min="1"
                max="6"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Retries</span
              ><input
                v-model.number="form.retries"
                type="number"
                min="1"
                max="10"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Recording PIN</span
              ><input
                v-model="form.record_pin"
                inputmode="numeric"
                minlength="3"
                maxlength="6"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                :placeholder="
                  record?.record_pin_configured ? 'Configured — enter to replace' : 'Optional'
                "
              /><span class="text-[10px] text-slate-400"
                >Write-only; the current PIN is never returned.</span
              ></label
            >
            <div class="grid gap-3 pt-6">
              <ToggleSwitch v-model="form.hunt" label="Allow extension dialing" />
              <ToggleSwitch
                v-model="form.allow_record_from_offnet"
                label="Allow off-network recording"
              />
              <ToggleSwitch v-model="form.suppress_media" label="Suppress invalid prompt" />
            </div>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Allowed extension pattern</span
              ><input
                v-model="form.hunt_allow"
                maxlength="256"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                placeholder="Optional regular expression"
            /></label>
            <label class="grid gap-2"
              ><span class="text-xs font-semibold text-slate-600">Denied extension pattern</span
              ><input
                v-model="form.hunt_deny"
                maxlength="256"
                class="h-10 rounded-md border border-slate-200 px-3 text-xs"
                placeholder="Optional regular expression"
            /></label>
          </div>
        </article>
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <MusicalNoteIcon class="size-5 text-brand-500" />
            <div>
              <h2 class="text-sm font-semibold text-slate-700">Prompts</h2>
              <p class="text-[10px] text-slate-400">
                Choose projected media or keep the Switch system prompt.
              </p>
            </div>
          </header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"
              ><span class="text-xs font-semibold text-slate-600">Greeting</span
              ><FormSelect
                v-model="form.greeting_media_id"
                class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"
                ><option :value="null">No custom greeting</option>
                <option v-for="media in options.media" :key="media.id" :value="media.id">
                  {{ media.label }}
                </option></FormSelect
              ></label
            >
            <label
              v-for="prompt in ['invalid', 'transfer', 'exit'] as const"
              :key="prompt"
              class="grid gap-2 rounded-md border border-slate-100 p-3"
              ><span class="text-xs font-semibold text-slate-600 capitalize"
                >{{ prompt }} prompt</span
              ><ToggleSwitch v-model="form[`${prompt}_media_enabled`]" label="Enabled" />
              <FormSelect
                v-model="form[`${prompt}_media_id`]"
                :disabled="!form[`${prompt}_media_enabled`]"
                class="h-9 rounded-md border border-slate-200 bg-white px-2 text-xs disabled:opacity-50"
                ><option :value="null">Switch system prompt</option>
                <option v-for="media in options.media" :key="media.id" :value="media.id">
                  {{ media.label }}
                </option></FormSelect
              ></label
            >
          </div>
        </article>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-semibold text-danger"
          @click="confirmDelete = true"
        >
          <TrashIcon class="size-4" />Delete menu
        </button>
      </div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          {{ canManage ? 'Cancel' : 'Close' }}</button
        ><button
          v-if="canManage"
          type="submit"
          :disabled="saving || !form.name.trim()"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : 'Save menu' }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
  <ConfirmDialog
    :open="confirmDelete"
    title="Delete menu"
    description="Delete this menu after checking its call-routing dependencies?"
    confirm-label="Delete menu"
    :busy="saving"
    @close="confirmDelete = false"
    @confirm="emit('remove')"
  />
</template>
