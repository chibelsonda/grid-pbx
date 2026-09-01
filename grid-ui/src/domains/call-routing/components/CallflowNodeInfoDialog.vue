<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{ open: boolean; title: string; breadcrumb: string; eyebrow?: string }>(),
  { eyebrow: 'Callflow node' },
)
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <TransitionRoot appear :show="open" as="template">
    <Dialog class="relative z-50" @close="emit('close')">
      <TransitionChild
        as="template"
        enter="ease-out duration-200"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-150"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-[1px]" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div class="flex min-h-full items-center justify-center">
          <TransitionChild
            as="template"
            enter="ease-out duration-200"
            enter-from="translate-y-3 opacity-0 sm:scale-95"
            enter-to="translate-y-0 opacity-100 sm:scale-100"
            leave="ease-in duration-150"
            leave-from="translate-y-0 opacity-100 sm:scale-100"
            leave-to="translate-y-3 opacity-0 sm:scale-95"
          >
            <DialogPanel
              class="w-full max-w-3xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
            >
              <header class="flex items-start gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                  <p class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    {{ eyebrow }}
                  </p>
                  <DialogTitle class="mt-1 text-lg font-semibold text-slate-800">
                    {{ title }}
                  </DialogTitle>
                  <p class="mt-1 break-words text-[10px] text-slate-500">{{ breadcrumb }}</p>
                </div>
                <button
                  type="button"
                  class="ml-auto grid size-9 shrink-0 place-items-center rounded-md border border-slate-300 text-slate-500 shadow-sm hover:bg-slate-50"
                  aria-label="Close node information"
                  @click="emit('close')"
                >
                  <XMarkIcon class="size-5" />
                </button>
              </header>
              <div class="p-5 sm:p-6">
                <slot />
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
