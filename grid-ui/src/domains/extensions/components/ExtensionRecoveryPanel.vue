<script setup lang="ts">
import { reactive } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormInput from '@/shared/components/FormInput.vue'
import type { ExtensionRecoveryOperation } from '../types/extension'

defineProps<{
  records: ExtensionRecoveryOperation[]
  loading: boolean
  actionLoading: boolean
  error: string | null
  actionError: string | null
}>()
const emit = defineEmits<{
  close: []
  recover: [operation: ExtensionRecoveryOperation, confirmation: string | null]
}>()
const confirmations = reactive<Record<string, string>>({})

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function actionLabel(action: ExtensionRecoveryOperation['recovery_action']): string {
  if (action === 'cleanup') return 'Retry cleanup'
  if (action === 'reconcile') return 'Reconcile from Switch'
  if (action === 'resume') return 'Resume deletion'
  return 'Unsupported recovery'
}

function canRecover(operation: ExtensionRecoveryOperation): boolean {
  if (operation.recovery_action === 'unsupported') return false
  if (operation.recovery_action !== 'resume') return true
  return (confirmations[operation.id] ?? '').trim() === (operation.extension ?? '')
}
</script>

<template>
  <CrudSlideOver
    title="Extension recovery queue"
    eyebrow="GridPBX / People & Extensions / Recovery"
    description="Inspect incomplete managed workflows and run their bounded recovery action."
    width="medium"
    @close="emit('close')"
  >
    <div
      v-if="loading"
      class="card-surface grid min-h-56 place-items-center text-xs text-slate-400"
    >
      Loading recovery operations…
    </div>
    <div
      v-else-if="error"
      class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
    >
      {{ error }}
    </div>
    <div v-else class="grid gap-5">
      <aside
        class="flex items-start gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <WrenchScrewdriverIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          Create failures retry cleanup of leaked managed resources. Update failures reconcile MySQL
          from Switch before edits continue. Delete failures resume recorded steps only after
          exact-number confirmation.
        </p>
      </aside>

      <div
        v-if="actionError"
        class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger"
      >
        {{ actionError }}
      </div>

      <article
        v-if="records.length === 0"
        class="card-surface grid min-h-52 place-items-center p-8 text-center"
      >
        <div>
          <CheckCircleIcon class="mx-auto size-10 text-emerald-400" />
          <h2 class="mt-4 text-sm font-semibold text-slate-700">No recovery work pending</h2>
          <p class="mt-2 text-xs text-slate-500">
            Failed and stale managed workflows will appear here.
          </p>
        </div>
      </article>

      <article
        v-for="operation in records"
        :key="operation.id"
        class="card-surface overflow-hidden"
      >
        <header class="flex items-start gap-3 border-b border-slate-100 px-5 py-4">
          <span
            class="grid size-9 shrink-0 place-items-center rounded-md bg-amber-50 text-amber-600"
            ><ExclamationTriangleIcon class="size-5"
          /></span>
          <div class="min-w-0 flex-1">
            <h2 class="truncate text-sm font-semibold text-slate-700">
              {{ operation.display_name }}
            </h2>
            <p class="mt-1 font-mono text-[10px] text-brand-600">
              {{ operation.extension ?? 'Unprojected extension' }}
            </p>
          </div>
          <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700">{{
            humanize(operation.operation)
          }}</span>
        </header>
        <div class="grid gap-4 p-5">
          <div>
            <p class="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
              Completed upstream steps
            </p>
            <div class="mt-2 flex flex-wrap gap-1.5">
              <span
                v-for="step in operation.completed_steps"
                :key="step"
                class="rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-600"
                >{{ humanize(step) }}</span
              ><span
                v-if="operation.completed_steps.length === 0"
                class="text-[10px] text-slate-400"
                >None recorded</span
              >
            </div>
          </div>
          <p class="text-xs text-slate-500">
            Failed during
            <span class="font-semibold text-slate-700">{{
              humanize(operation.failed_step ?? 'unknown step')
            }}</span
            >.
          </p>
          <FormInput
            v-if="operation.recovery_action === 'resume'"
            :model-value="confirmations[operation.id] ?? ''"
            :label="`Type ${operation.extension} to resume deletion`"
            autocomplete="off"
            input-class="font-mono"
            @update:model-value="confirmations[operation.id] = String($event)"
          />
          <button
            type="button"
            :disabled="actionLoading || !canRecover(operation)"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-40"
            @click="
              emit(
                'recover',
                operation,
                operation.recovery_action === 'resume'
                  ? (confirmations[operation.id] ?? '').trim()
                  : null,
              )
            "
          >
            <ArrowPathIcon class="size-4" :class="actionLoading && 'animate-spin'" />{{
              actionLoading ? 'Running recovery…' : actionLabel(operation.recovery_action)
            }}
          </button>
        </div>
      </article>

      <div class="flex justify-end border-t border-slate-200 pt-5">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600"
          @click="emit('close')"
        >
          Close queue
        </button>
      </div>
    </div>
  </CrudSlideOver>
</template>
