<script setup lang="ts">
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  LinkIcon,
  ServerStackIcon,
} from '@heroicons/vue/24/outline'
import { computed, ref } from 'vue'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import type { ExtensionDeletionPreview } from '../types/extension'

const props = defineProps<{
  preview: ExtensionDeletionPreview | null
  loading: boolean
  error: string | null
  deleting: boolean
  deletionError: string | null
  fieldErrors: Record<string, string[]>
}>()
const emit = defineEmits<{ close: []; delete: [confirmation: string] }>()
const confirmation = ref('')
const confirmed = computed(
  () => confirmation.value.trim() === (props.preview?.extension.extension ?? ''),
)
</script>

<template>
  <CrudSlideOver
    title="Review deletion"
    eyebrow="GridPBX / People & Extensions / Dependencies"
    description="Dependency analysis and explicit confirmation for the managed Switch deletion workflow."
    width="medium"
    @close="emit('close')"
  >
    <div
      v-if="loading"
      class="card-surface grid min-h-56 place-items-center text-xs text-slate-400"
    >
      Inspecting extension dependencies…
    </div>
    <div
      v-else-if="error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else-if="preview" class="grid gap-5">
      <aside
        v-if="preview.recovery"
        class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <span class="font-semibold">Resuming deletion {{ preview.recovery.id }}.</span>
        Completed steps will be skipped. The previous failure occurred during
        {{ preview.recovery.failed_step?.replaceAll('_', ' ') ?? 'finalization' }}.
      </aside>
      <article class="card-surface p-5">
        <div class="flex items-start gap-3">
          <span
            class="grid size-10 shrink-0 place-items-center rounded-md"
            :class="
              preview.can_delete ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'
            "
          >
            <CheckCircleIcon v-if="preview.can_delete" class="size-5" /><ExclamationTriangleIcon
              v-else
              class="size-5"
            />
          </span>
          <div>
            <p class="eyebrow">Safety result</p>
            <h2 class="mt-1 text-sm font-semibold text-slate-700">
              {{ preview.can_delete ? 'No known blockers' : 'Deletion is blocked' }}
            </h2>
            <p class="mt-2 text-xs leading-5 text-slate-500">
              {{
                preview.can_delete
                  ? 'The current projection is eligible for a future managed deletion workflow.'
                  : 'Resolve every dependency below before deletion can be considered safe.'
              }}
            </p>
          </div>
        </div>
      </article>

      <article v-if="preview.blockers.length" class="card-surface overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-700">Blockers</h2>
        </header>
        <ul class="divide-y divide-slate-100">
          <li v-for="blocker in preview.blockers" :key="blocker.code" class="flex gap-3 px-5 py-4">
            <ExclamationTriangleIcon class="mt-0.5 size-4 shrink-0 text-amber-500" />
            <div>
              <p class="text-xs font-semibold text-slate-700">
                {{ blocker.code.replaceAll('_', ' ') }}
              </p>
              <p class="mt-1 text-[11px] leading-5 text-slate-500">{{ blocker.message }}</p>
            </div>
          </li>
        </ul>
      </article>

      <article class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
          <ServerStackIcon class="size-5 text-brand-500" />
          <div>
            <h2 class="text-sm font-semibold text-slate-700">Managed resources</h2>
            <p class="text-[10px] text-slate-400">Resources owned by extension provisioning</p>
          </div>
        </header>
        <div class="grid grid-cols-3 divide-x divide-slate-100 p-5 text-center">
          <div>
            <p class="text-lg font-semibold text-slate-700">
              {{ preview.managed_resources.devices.length }}
            </p>
            <p class="text-[10px] text-slate-400">Devices</p>
          </div>
          <div>
            <p class="text-lg font-semibold text-slate-700">
              {{ preview.managed_resources.voicemail_boxes.length }}
            </p>
            <p class="text-[10px] text-slate-400">Voicemail</p>
          </div>
          <div>
            <p class="text-lg font-semibold text-slate-700">
              {{ preview.managed_resources.callflows.length }}
            </p>
            <p class="text-[10px] text-slate-400">Callflows</p>
          </div>
        </div>
      </article>

      <article class="card-surface p-5">
        <div class="flex items-center gap-2">
          <LinkIcon class="size-4 text-slate-400" />
          <h2 class="text-sm font-semibold text-slate-700">External relationships</h2>
        </div>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
          <div class="rounded-md bg-slate-50 p-3">
            <dt class="text-slate-400">Shared resources</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{
                preview.shared_resources.device_count +
                preview.shared_resources.voicemail_box_count +
                preview.shared_resources.callflow_count
              }}
            </dd>
          </div>
          <div class="rounded-md bg-slate-50 p-3">
            <dt class="text-slate-400">Referencing routes</dt>
            <dd class="mt-1 font-semibold text-slate-700">
              {{ preview.referencing_callflows.length }}
            </dd>
          </div>
        </dl>
        <ul
          v-if="preview.referencing_callflows.length"
          class="mt-3 divide-y divide-slate-100 border-t border-slate-100"
        >
          <li
            v-for="callflow in preview.referencing_callflows"
            :key="callflow.id"
            class="py-3 text-xs text-slate-600"
          >
            {{ callflow.name }}
          </li>
        </ul>
      </article>

      <aside
        v-if="!preview.can_delete"
        class="rounded-md border border-slate-200 bg-white p-4 text-xs leading-5 text-slate-500"
      >
        Deletion remains disabled while blockers exist. GridPBX will never remove shared resources
        as part of this workflow.
      </aside>

      <article v-else class="card-surface overflow-hidden border-red-100">
        <header class="border-b border-red-100 bg-red-50 px-5 py-4">
          <h2 class="text-sm font-semibold text-red-800">Confirm managed deletion</h2>
          <p class="mt-1 text-[10px] leading-4 text-red-600">
            This permanently removes the owned callflow, devices, voicemail box, and Switch user.
            Progress is recorded so an interrupted operation can be resumed safely.
          </p>
        </header>
        <div class="grid gap-2 p-5">
          <FormInput
            id="extension-deletion-confirmation"
            v-model="confirmation"
            :label="`Type ${preview.extension.extension} to confirm`"
            autocomplete="off"
            input-class="font-mono"
            :error="fieldErrors.confirmation"
          />
          <div
            v-if="deletionError"
            class="mt-2 rounded-md border border-red-100 bg-red-50 p-3 text-xs leading-5 text-danger"
          >
            {{ deletionError }}
          </div>
        </div>
      </article>

      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Close review
        </button>
        <button
          v-if="preview.can_delete"
          type="button"
          :disabled="!confirmed || deleting"
          class="h-10 rounded-md bg-red-600 px-5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
          @click="emit('delete', confirmation.trim())"
        >
          {{ deleting ? 'Deleting managed resources…' : 'Delete extension' }}
        </button>
      </div>
    </div>
  </CrudSlideOver>
</template>
