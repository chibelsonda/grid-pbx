<script setup lang="ts">
import { ArrowDownTrayIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import type { Fax } from '../types/fax'
defineProps<{ record: Fax; downloading: boolean; error: string | null }>()
const emit = defineEmits<{ close: []; download: [] }>()
const format = (value: number | null): string =>
  value === null ? 'Unknown' : value < 1024 ? `${value} B` : `${(value / 1024).toFixed(1)} KB`
</script>
<template>
  <CrudSlideOver
    title="Fax details"
    eyebrow="GridPBX / Fax history"
    description="Operational metadata and authorized document access."
    width="medium"
    @close="emit('close')"
    ><div class="grid gap-5">
      <div v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </div>
      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <DocumentTextIcon class="size-5 text-brand-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">
              {{ record.folder === 'inbox' ? 'Received fax' : 'Sent fax' }}
            </h2>
            <p class="text-[10px] text-heading-description">
              {{
                record.created_at
                  ? new Date(record.created_at).toLocaleString()
                  : 'Time unavailable'
              }}
            </p>
          </div>
        </header>
        <dl class="grid grid-cols-2 gap-4 p-5 text-xs">
          <div>
            <dt class="text-slate-400">From</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ record.from.name || record.from.number || 'Unknown' }}
            </dd>
            <dd v-if="record.from.name" class="text-slate-500">{{ record.from.number }}</dd>
          </div>
          <div>
            <dt class="text-slate-400">To</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ record.to.name || record.to.number || 'Unknown' }}
            </dd>
            <dd v-if="record.to.name" class="text-slate-500">{{ record.to.number }}</dd>
          </div>
          <div>
            <dt class="text-slate-400">Status</dt>
            <dd class="mt-1 font-semibold text-slate-700">{{ record.status || 'Unknown' }}</dd>
          </div>
          <div>
            <dt class="text-slate-400">Fax box</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ record.fax_box?.name || 'Unassigned' }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-400">Pages</dt>
            <dd class="mt-1 font-semibold text-slate-700">{{ record.pages }}</dd>
          </div>
          <div>
            <dt class="text-slate-400">Speed</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ record.fax_speed ? `${record.fax_speed} baud` : 'Unknown' }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-400">Attempts</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ record.attempts }} / {{ record.retries + 1 }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-400">Document</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ record.document_content_type || 'Unavailable' }} ·
              {{ format(record.document_size) }}
            </dd>
          </div>
        </dl>
        <p
          v-if="record.error_message"
          class="border-t border-red-100 bg-red-50 p-4 text-xs text-red-700"
        >
          {{ record.error_message }}
        </p>
      </article>
      <button
        v-if="record.has_document"
        type="button"
        :disabled="downloading"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white disabled:opacity-50"
        @click="emit('download')"
      >
        <ArrowDownTrayIcon class="size-4" />{{ downloading ? 'Downloading…' : 'Download document' }}
      </button>
      <p class="text-[10px] text-slate-400">
        Document access is authenticated and audited. The binary is streamed from Switch and is not
        stored by GridPBX.
      </p>
    </div></CrudSlideOver
  >
</template>
