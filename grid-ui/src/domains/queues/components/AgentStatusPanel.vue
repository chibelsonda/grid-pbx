<script setup lang="ts">
import { reactive } from 'vue'
import { BoltIcon, UserCircleIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Agent, AgentStatus, AgentStatusInput } from '../types/queue'

defineProps<{ agent: Agent; current: AgentStatus | null; loading: boolean; error: string | null; canManage: boolean }>()
const emit = defineEmits<{ close: []; save: [input: AgentStatusInput] }>()
const form = reactive<AgentStatusInput>({ status: 'login', pause_timeout: 300 })
</script>

<template>
  <CrudSlideOver title="Agent status" eyebrow="GridPBX / Queues / Agents" description="Live ACDc state is read from Switch and is not treated as durable MySQL configuration." width="medium" @close="emit('close')">
    <form class="grid gap-5" @submit.prevent="canManage && emit('save', { ...form })">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">{{ error }}</div>
      <article class="card-surface p-5"><div class="flex items-center gap-4"><span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-600"><UserCircleIcon class="size-6" /></span><div><h2 class="text-sm font-semibold text-slate-700">{{ agent.name }}</h2><p class="text-xs text-slate-400">Extension {{ agent.extension ?? 'not assigned' }}</p></div></div><div class="mt-5 rounded-md bg-slate-50 p-4"><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Current Switch status</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ loading ? 'Loading…' : current?.status ?? 'Unknown' }}</p></div></article>
      <article v-if="canManage" class="card-surface overflow-hidden"><header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><BoltIcon class="size-5 text-amber-500" /><div><h2 class="text-sm font-semibold text-slate-700">Request status change</h2><p class="text-[10px] text-slate-400">Commands can be deferred by Switch while an agent is on a call.</p></div></header><div class="grid gap-4 p-5"><label class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Action</span><select v-model="form.status" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-xs"><option value="login">Log in</option><option value="logout">Log out</option><option value="pause">Pause</option><option value="resume">Resume</option><option value="end_wrapup">End wrap-up</option></select></label><label v-if="form.status === 'pause'" class="grid gap-2"><span class="text-xs font-semibold text-slate-600">Pause timeout (seconds)</span><input v-model.number="form.pause_timeout" type="number" min="0" max="86400" required class="h-10 rounded-md border border-slate-200 px-3 text-xs" /></label></div></article>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><button type="button" class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600" @click="emit('close')">{{ canManage ? 'Cancel' : 'Close' }}</button><button v-if="canManage" type="submit" :disabled="loading" class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50">Send command</button></div>
    </form>
  </CrudSlideOver>
</template>
