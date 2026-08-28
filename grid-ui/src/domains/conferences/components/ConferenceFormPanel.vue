<script setup lang="ts">
import { reactive, ref } from 'vue'
import { KeyIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Conference, ConferenceInput, ConferenceOptions } from '../types/conference'

const props = defineProps<{ record: Conference | null; options: ConferenceOptions; saving: boolean; error: string | null; fieldErrors: Record<string, string[]>; canManage: boolean }>()
const emit = defineEmits<{ close: []; save: [input: ConferenceInput]; remove: [] }>()
const confirmDelete = ref(false)
const numbers = reactive({
  conference: props.record?.conference_numbers.join(', ') ?? '',
  member: props.record?.member_numbers.join(', ') ?? '',
  moderator: props.record?.moderator_numbers.join(', ') ?? '',
})
const form = reactive<ConferenceInput>({
  name: props.record?.name ?? '', owner_id: props.record?.owner?.id ?? null,
  conference_numbers: [], member_numbers: [], moderator_numbers: [], member_pin: null, clear_member_pin: false,
  moderator_pin: null, clear_moderator_pin: false, member_join_muted: props.record?.member_join_muted ?? true,
  member_join_deaf: props.record?.member_join_deaf ?? false, member_play_entry_prompt: props.record?.member_play_entry_prompt ?? false,
  moderator_join_muted: props.record?.moderator_join_muted ?? false, moderator_join_deaf: props.record?.moderator_join_deaf ?? false,
  max_participants: props.record?.max_participants ?? null, language: props.record?.language ?? null,
  profile_name: props.record?.profile_name ?? null, caller_controls: props.record?.caller_controls ?? null,
  moderator_controls: props.record?.moderator_controls ?? null, play_name: props.record?.play_name ?? false,
  play_welcome: props.record?.play_welcome ?? true, require_moderator: props.record?.require_moderator ?? false,
  wait_for_moderator: props.record?.wait_for_moderator ?? false,
})
function list(value: string): string[] { return [...new Set(value.split(/[\s,]+/).map((item) => item.trim()).filter(Boolean))] }
function save(): void {
  emit('save', { ...form, conference_numbers: list(numbers.conference), member_numbers: list(numbers.member), moderator_numbers: list(numbers.moderator), member_pin: form.member_pin || null, moderator_pin: form.moderator_pin || null })
}
</script>

<template>
  <CrudSlideOver :title="!canManage ? 'View conference' : record ? 'Edit conference' : 'Create conference'" eyebrow="GridPBX / Conferences" description="Configure conference access roles, safe PIN replacement, and participant behavior." width="medium" @close="emit('close')">
    <form class="grid gap-5" @submit.prevent="canManage && save()">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"><UserGroupIcon class="size-5" /></span><div><h2 class="text-sm font-semibold text-slate-700">Conference identity</h2><p class="text-[10px] text-slate-400">Name, owner, access numbers, and capacity.</p></div></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">Name</span><input v-model="form.name" required maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{ fieldErrors.name[0] }}</span></label>
            <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">Owner</span><select v-model="form.owner_id" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"><option :value="null">No owner</option><option v-for="owner in options.owners" :key="owner.id" :value="owner.id">{{ owner.label }}{{ owner.detail ? ` · ${owner.detail}` : '' }}</option></select><span v-if="fieldErrors.owner_id" class="text-[10px] text-danger">{{ fieldErrors.owner_id[0] }}</span></label>
            <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">General conference numbers</span><input v-model="numbers.conference" inputmode="numeric" class="h-10 rounded-md border border-slate-200 px-3 text-xs" placeholder="7000, 7002" /><span class="text-[10px] text-slate-400">Comma- or space-separated access identifiers; these are not purchased PSTN numbers.</span></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Maximum participants</span><input v-model.number="form.max_participants" type="number" min="1" max="10000" class="h-10 rounded-md border border-slate-200 px-3 text-xs" placeholder="No explicit limit" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Prompt language</span><input v-model="form.language" maxlength="16" class="h-10 rounded-md border border-slate-200 px-3 text-xs" placeholder="en-US" /></label>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><KeyIcon class="size-5 text-brand-500" /><div><h2 class="text-sm font-semibold text-slate-700">Member access</h2><p class="text-[10px] text-slate-400">PINs are write-only and never returned by the API.</p></div></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">Member numbers</span><input v-model="numbers.member" inputmode="numeric" class="h-10 rounded-md border border-slate-200 px-3 text-xs" placeholder="7001" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Member PIN</span><input v-model="form.member_pin" :disabled="form.clear_member_pin" inputmode="numeric" maxlength="32" class="h-10 rounded-md border border-slate-200 px-3 text-xs disabled:opacity-50" :placeholder="record?.member_pin_configured ? 'Configured — enter to replace' : 'Optional'" /></label>
            <label v-if="record?.member_pin_configured" class="flex items-center gap-2 pt-7 text-xs text-slate-600"><input v-model="form.clear_member_pin" type="checkbox" class="size-4 accent-brand-500" @change="form.member_pin = null" />Remove current member PIN</label>
            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-3"><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.member_join_muted" type="checkbox" class="size-4 accent-brand-500" />Join muted</label><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.member_join_deaf" type="checkbox" class="size-4 accent-brand-500" />Join deaf</label><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.member_play_entry_prompt" type="checkbox" class="size-4 accent-brand-500" />Play entry prompt</label></div>
          </div>
        </article>

        <article class="card-surface overflow-hidden">
          <header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-700">Moderator access & behavior</h2><p class="mt-1 text-[10px] text-slate-400">Separate access credentials and room-start behavior.</p></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">Moderator numbers</span><input v-model="numbers.moderator" inputmode="numeric" class="h-10 rounded-md border border-slate-200 px-3 text-xs" placeholder="7099" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Moderator PIN</span><input v-model="form.moderator_pin" :disabled="form.clear_moderator_pin" inputmode="numeric" maxlength="32" class="h-10 rounded-md border border-slate-200 px-3 text-xs disabled:opacity-50" :placeholder="record?.moderator_pin_configured ? 'Configured — enter to replace' : 'Optional'" /></label>
            <label v-if="record?.moderator_pin_configured" class="flex items-center gap-2 pt-7 text-xs text-slate-600"><input v-model="form.clear_moderator_pin" type="checkbox" class="size-4 accent-brand-500" @change="form.moderator_pin = null" />Remove current moderator PIN</label>
            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2"><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.moderator_join_muted" type="checkbox" class="size-4 accent-brand-500" />Moderator joins muted</label><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.moderator_join_deaf" type="checkbox" class="size-4 accent-brand-500" />Moderator joins deaf</label></div>
            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2"><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.require_moderator" type="checkbox" class="size-4 accent-brand-500" />Require moderator</label><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.wait_for_moderator" type="checkbox" class="size-4 accent-brand-500" />Members wait for moderator</label><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.play_name" type="checkbox" class="size-4 accent-brand-500" />Announce participant names</label><label class="flex items-center gap-2 text-xs text-slate-600"><input v-model="form.play_welcome" type="checkbox" class="size-4 accent-brand-500" />Play welcome prompt</label></div>
          </div>
        </article>

        <details class="card-surface overflow-hidden"><summary class="cursor-pointer px-5 py-4 text-xs font-semibold text-slate-600">Advanced Switch profiles</summary><div class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2"><label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Profile name</span><input v-model="form.profile_name" maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label><label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Caller controls</span><input v-model="form.caller_controls" maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label><label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Moderator controls</span><input v-model="form.moderator_controls" maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label></div></details>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4"><button v-if="!confirmDelete" type="button" class="inline-flex items-center gap-2 text-xs font-semibold text-danger" @click="confirmDelete = true"><TrashIcon class="size-4" />Delete conference</button><div v-else class="flex items-center justify-between gap-3"><p class="text-xs text-red-700">Delete after call-routing checks?</p><button type="button" :disabled="saving" class="rounded-md bg-red-600 px-4 py-2 text-xs font-semibold text-white" @click="emit('remove')">Confirm delete</button></div></div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><button type="button" class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600" @click="emit('close')">{{ canManage ? 'Cancel' : 'Close' }}</button><button v-if="canManage" type="submit" :disabled="saving || !form.name.trim()" class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Save conference' }}</button></div>
    </form>
  </CrudSlideOver>
</template>
