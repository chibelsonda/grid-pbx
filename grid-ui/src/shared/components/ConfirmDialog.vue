<script setup lang="ts">
import {
  Dialog,
  DialogDescription,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    description: string
    confirmLabel?: string
    busy?: boolean
    disabled?: boolean
    tone?: 'danger' | 'warning' | 'primary'
  }>(),
  { confirmLabel: 'Confirm', busy: false, disabled: false, tone: 'danger' },
)

const emit = defineEmits<{ close: []; confirm: [] }>()
</script>

<template>
  <TransitionRoot appear :show="open" as="template">
    <Dialog class="relative z-[70]" @close="busy ? undefined : emit('close')">
      <TransitionChild
        as="template"
        enter="ease-out duration-200"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-150"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/45" />
      </TransitionChild>
      <div class="fixed inset-0 overflow-y-auto p-4">
        <div class="flex min-h-full items-center justify-center">
          <TransitionChild
            as="template"
            enter="ease-out duration-200"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="ease-in duration-150"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-md rounded-lg bg-white p-6 shadow-2xl">
              <div class="flex items-start gap-4">
                <span
                  class="grid size-10 shrink-0 place-items-center rounded-full"
                  :class="
                    tone === 'danger'
                      ? 'bg-red-50 text-danger'
                      : tone === 'warning'
                        ? 'bg-amber-50 text-amber-600'
                        : 'bg-brand-50 text-brand-600'
                  "
                >
                  <ExclamationTriangleIcon class="size-5" />
                </span>
                <div>
                  <DialogTitle class="text-sm font-semibold text-slate-800">{{
                    title
                  }}</DialogTitle>
                  <DialogDescription class="mt-1 text-xs leading-5 text-slate-500">
                    {{ description }}
                  </DialogDescription>
                </div>
              </div>
              <div class="mt-6 flex justify-end gap-3">
                <button
                  type="button"
                  :disabled="busy"
                  class="h-9 rounded-md border border-slate-200 px-4 text-xs font-semibold text-slate-600 disabled:opacity-50"
                  @click="emit('close')"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  :disabled="busy || disabled"
                  class="h-9 rounded-md px-4 text-xs font-semibold text-white disabled:opacity-50"
                  :class="
                    tone === 'danger'
                      ? 'bg-red-600'
                      : tone === 'warning'
                        ? 'bg-amber-500'
                        : 'bg-brand-500'
                  "
                  @click="emit('confirm')"
                >
                  {{ busy ? 'Working…' : confirmLabel }}
                </button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
