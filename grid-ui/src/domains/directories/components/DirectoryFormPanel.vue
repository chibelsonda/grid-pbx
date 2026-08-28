<script setup lang="ts">
import { reactive, ref } from 'vue'
import { BookOpenIcon, TrashIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Directory, DirectoryInput, DirectoryOptions } from '../types/directory'

const props = defineProps<{ record: Directory | null; options: DirectoryOptions; saving: boolean; error: string | null; fieldErrors: Record<string, string[]>; canManage: boolean }>()
const emit = defineEmits<{ close: []; save: [input: DirectoryInput]; remove: [] }>()
const confirmDelete = ref(false)
const form = reactive<DirectoryInput>({
  name: props.record?.name ?? '', confirm_match: props.record?.confirm_match ?? true,
  min_dtmf: props.record?.min_dtmf ?? 3, max_dtmf: props.record?.max_dtmf ?? 0,
  sort_by: props.record?.sort_by ?? 'last_name',
  member_ids: props.record?.members?.flatMap((member) => member.extension ? [member.extension.id] : []) ?? [],
})
</script>

<template>
  <CrudSlideOver :title="!canManage ? 'View directory' : record ? 'Edit directory' : 'Create directory'" eyebrow="GridPBX / Directories" description="Directory settings live on the Switch directory; member routes are coordinated through user mappings." width="medium" @close="emit('close')">
    <form class="grid gap-5" @submit.prevent="canManage && emit('save', { ...form, member_ids: [...form.member_ids] })">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"><BookOpenIcon class="size-5" /></span><div><h2 class="text-sm font-semibold text-slate-700">Dial-by-name settings</h2><p class="text-[10px] text-slate-400">Search and confirmation behavior presented to callers.</p></div></header>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">Name</span><input v-model="form.name" required maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500" /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{ fieldErrors.name[0] }}</span></label>
          <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Sort names by</span><select v-model="form.sort_by" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"><option value="last_name">Last name</option><option value="first_name">First name</option></select></label>
          <label class="flex items-center gap-3 self-end pb-2"><input v-model="form.confirm_match" type="checkbox" class="size-4 accent-brand-500" /><span class="text-xs font-semibold text-slate-600">Confirm a single match</span></label>
          <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Minimum digits</span><input v-model.number="form.min_dtmf" type="number" min="1" max="20" required class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
          <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Maximum digits <span class="font-normal text-slate-400">(0 = unlimited)</span></span><input v-model.number="form.max_dtmf" type="number" min="0" max="20" required class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
        </div>
      </article>
      <article class="card-surface overflow-hidden"><header class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-700">Directory members</h2><p class="mt-1 text-[10px] text-slate-400">Only extensions with a projected callflow are selectable.</p></header><div class="grid gap-2 p-5"><label v-for="option in options.extensions" :key="option.id" class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-100 px-4 py-3 hover:bg-slate-50"><input v-model="form.member_ids" type="checkbox" :value="option.id" class="size-4 accent-brand-500" /><span><span class="block text-xs font-semibold text-slate-700">{{ option.label }}</span><span class="text-[10px] text-slate-400">{{ option.detail }}</span></span></label><p v-if="!options.extensions.length" class="text-xs text-slate-400">No eligible extensions are projected.</p></div></article>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4"><button v-if="!confirmDelete" type="button" class="inline-flex items-center gap-2 text-xs font-semibold text-danger" @click="confirmDelete = true"><TrashIcon class="size-4" />Delete directory</button><div v-else class="flex items-center justify-between gap-3"><p class="text-xs text-red-700">Delete after dependency checks?</p><button type="button" :disabled="saving" class="rounded-md bg-red-600 px-4 py-2 text-xs font-semibold text-white" @click="emit('remove')">Confirm delete</button></div></div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><button type="button" class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600" @click="emit('close')">{{ canManage ? 'Cancel' : 'Close' }}</button><button v-if="canManage" type="submit" :disabled="saving || !form.name.trim()" class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Save directory' }}</button></div>
    </form>
  </CrudSlideOver>
</template>
