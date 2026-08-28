<script setup lang="ts">
import { reactive, ref } from 'vue'
import { QueueListIcon, TrashIcon, UsersIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Queue, QueueInput, QueueOptions } from '../types/queue'

const props = defineProps<{
  record: Queue | null
  options: QueueOptions
  saving: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
  canManage: boolean
}>()
const emit = defineEmits<{ close: []; save: [input: QueueInput]; remove: [] }>()
const confirmDelete = ref(false)
const form = reactive<QueueInput>({
  name: props.record?.name ?? '', strategy: props.record?.strategy ?? 'round_robin',
  agent_ring_timeout: props.record?.agent_ring_timeout ?? 15,
  agent_wrapup_time: props.record?.agent_wrapup_time ?? 0,
  connection_timeout: props.record?.connection_timeout ?? 3600,
  max_queue_size: props.record?.max_queue_size ?? 0,
  ring_simultaneously: props.record?.ring_simultaneously ?? 1,
  enter_when_empty: props.record?.enter_when_empty ?? true,
  record_caller: props.record?.record_caller ?? false,
  caller_exit_key: props.record?.caller_exit_key ?? '#',
  music_on_hold_media_id: props.record?.music_on_hold_media?.id ?? null,
  agent_ids: props.record?.agents?.flatMap((membership) => membership.agent ? [membership.agent.id] : []) ?? [],
})
</script>

<template>
  <CrudSlideOver :title="!canManage ? 'View queue' : record ? 'Edit queue' : 'Create queue'" eyebrow="GridPBX / Queues" description="Configure caller waiting behavior and the projected Switch agent roster." width="medium" @close="emit('close')">
    <form class="grid gap-5" @submit.prevent="canManage && emit('save', { ...form, agent_ids: [...form.agent_ids] })">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
      <fieldset :disabled="!canManage" class="grid gap-5 disabled:opacity-75">
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><span class="grid size-10 place-items-center rounded-md bg-brand-50 text-brand-600"><QueueListIcon class="size-5" /></span><div><h2 class="text-sm font-semibold text-slate-700">Queue behavior</h2><p class="text-[10px] text-slate-400">The operational settings sent to Switch ACDc.</p></div></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-semibold text-slate-600">Name</span><input v-model="form.name" required maxlength="128" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /><span v-if="fieldErrors.name" class="text-[10px] text-danger">{{ fieldErrors.name[0] }}</span></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Strategy</span><select v-model="form.strategy" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"><option value="round_robin">Round robin</option><option value="most_idle">Most idle</option></select></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Music on hold</span><select v-model="form.music_on_hold_media_id" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"><option :value="null">Account default</option><option v-for="media in options.media" :key="media.id" :value="media.id">{{ media.label }}</option></select></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Agent ring timeout</span><input v-model.number="form.agent_ring_timeout" type="number" min="1" max="300" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Wrap-up time</span><input v-model.number="form.agent_wrapup_time" type="number" min="0" max="3600" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Connection timeout</span><input v-model.number="form.connection_timeout" type="number" min="0" max="86400" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Maximum callers <span class="font-normal text-slate-400">(0 = unlimited)</span></span><input v-model.number="form.max_queue_size" type="number" min="0" max="10000" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Agents rung together</span><input v-model.number="form.ring_simultaneously" type="number" min="1" max="100" class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label>
            <label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Caller exit key</span><select v-model="form.caller_exit_key" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"><option v-for="key in ['#', '*', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9']" :key="key" :value="key">{{ key }}</option></select></label>
            <label class="flex items-center gap-3"><input v-model="form.enter_when_empty" type="checkbox" class="size-4 accent-brand-500" /><span class="text-xs font-semibold text-slate-600">Allow entry when empty</span></label>
            <label class="flex items-center gap-3"><input v-model="form.record_caller" type="checkbox" class="size-4 accent-brand-500" /><span class="text-xs font-semibold text-slate-600">Record callers</span></label>
          </div>
        </article>
        <article class="card-surface overflow-hidden">
          <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><UsersIcon class="size-5 text-brand-500" /><div><h2 class="text-sm font-semibold text-slate-700">Agent roster</h2><p class="text-[10px] text-slate-400">Agents are existing extensions; Switch stores queue membership on their User documents.</p></div></header>
          <div class="grid gap-2 p-5"><label v-for="agent in options.agents" :key="agent.id" class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-100 px-4 py-3 hover:bg-slate-50"><input v-model="form.agent_ids" type="checkbox" :value="agent.id" class="size-4 accent-brand-500" /><span><span class="block text-xs font-semibold text-slate-700">{{ agent.label }}</span><span class="text-[10px] text-slate-400">{{ agent.detail }}</span></span></label><p v-if="!options.agents.length" class="text-xs text-slate-400">No projected extensions can act as agents.</p></div>
        </article>
      </fieldset>
      <div v-if="record && canManage" class="rounded-md border border-red-100 bg-red-50 p-4"><button v-if="!confirmDelete" type="button" class="inline-flex items-center gap-2 text-xs font-semibold text-danger" @click="confirmDelete = true"><TrashIcon class="size-4" />Delete queue</button><div v-else class="flex items-center justify-between gap-3"><p class="text-xs text-red-700">Delete after routing checks?</p><button type="button" :disabled="saving" class="rounded-md bg-red-600 px-4 py-2 text-xs font-semibold text-white" @click="emit('remove')">Confirm delete</button></div></div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><button type="button" class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600" @click="emit('close')">{{ canManage ? 'Cancel' : 'Close' }}</button><button v-if="canManage" type="submit" :disabled="saving || !form.name.trim()" class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving…' : 'Save queue' }}</button></div>
    </form>
  </CrudSlideOver>
</template>
