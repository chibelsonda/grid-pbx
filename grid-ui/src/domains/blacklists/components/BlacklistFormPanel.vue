<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ShieldExclamationIcon, TrashIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Blacklist, BlacklistInput } from '../types/blacklist'

const props = defineProps<{ record: Blacklist | null; saving: boolean; error: string | null; fieldErrors: Record<string, string[]>; canManage: boolean }>()
const emit = defineEmits<{ close: []; save: [input: BlacklistInput]; remove: [] }>()
const confirmDelete = ref(false)
const form = reactive({ name: props.record?.name ?? '', should_block_anonymous: props.record?.should_block_anonymous ?? false, is_active: props.record?.is_active ?? false, numbersText: (props.record?.numbers ?? []).map((entry) => entry.number).join('\n') })
const numbers = computed(() => [...new Set(form.numbersText.split(/[\s,]+/).map((number) => number.trim()).filter(Boolean))])
const invalidNumbers = computed(() => numbers.value.filter((number) => !/^\+[1-9]\d{6,14}$/.test(number)))
function submit(): void { if (!invalidNumbers.value.length) emit('save', { name: form.name, should_block_anonymous: form.should_block_anonymous, is_active: form.is_active, numbers: numbers.value }) }
</script>

<template>
  <CrudSlideOver :title="!canManage ? 'View blacklist' : record ? 'Edit blacklist' : 'Create blacklist'" eyebrow="GridPBX / Call protection" description="Block inbound callers at the account boundary." width="medium" @close="emit('close')">
    <form class="grid gap-5" @submit.prevent="canManage && submit()">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden"><header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><span class="grid size-10 place-items-center rounded-md bg-red-50 text-red-600"><ShieldExclamationIcon class="size-5" /></span><div><h2 class="text-sm font-semibold text-slate-700">Inbound call protection</h2><p class="text-[10px] text-slate-400">Activation is an account setting and is synchronized separately.</p></div></header><div class="grid gap-4 p-5">
          <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Name</span><input v-model="form.name" required maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{ fieldErrors.name[0] }}</span></label>
          <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Blocked caller numbers</span><textarea v-model="form.numbersText" rows="10" class="rounded-md border border-slate-200 px-3 py-2 font-mono text-xs" placeholder="+15550001000&#10;+15550001001" /><span class="text-[10px] text-slate-400">One E.164 number per line. {{ numbers.length }} unique number{{ numbers.length === 1 ? '' : 's' }}.</span><span v-if="invalidNumbers.length" class="text-[10px] text-danger">Use E.164 format for: {{ invalidNumbers.join(', ') }}</span><span v-if="fieldErrors.numbers" class="text-[10px] text-danger">{{ fieldErrors.numbers[0] }}</span></label>
          <label class="flex items-start gap-3 rounded-md border border-slate-100 p-4"><input v-model="form.should_block_anonymous" type="checkbox" class="mt-0.5 size-4 accent-brand-500" /><span><strong class="block text-xs text-slate-600">Block anonymous callers</strong><small class="text-[10px] text-slate-400">Reject callers whose identity is withheld or unavailable.</small></span></label>
          <label class="flex items-start gap-3 rounded-md border border-brand-100 bg-brand-50/50 p-4"><input v-model="form.is_active" type="checkbox" class="mt-0.5 size-4 accent-brand-500" /><span><strong class="block text-xs text-slate-600">Active for this account</strong><small class="text-[10px] text-slate-400">Adds this blacklist ID to the Switch account's inbound enforcement list.</small></span></label>
        </div></article>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4"><button v-if="!confirmDelete" type="button" class="inline-flex items-center gap-2 text-xs font-semibold text-danger" @click="confirmDelete = true"><TrashIcon class="size-4" />Delete blacklist</button><div v-else class="flex items-center justify-between gap-3"><p class="text-xs text-red-700">{{ record.is_active ? 'Deactivate and save before deleting.' : 'Delete this caller list?' }}</p><button type="button" :disabled="saving || record.is_active" class="rounded-md bg-red-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-40" @click="emit('remove')">Confirm delete</button></div></div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><button type="button" class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600" @click="emit('close')">{{ canManage ? 'Cancel' : 'Close' }}</button><button v-if="canManage" type="submit" :disabled="saving || !form.name.trim() || invalidNumbers.length > 0" class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Save blacklist' }}</button></div>
    </form>
  </CrudSlideOver>
</template>
