<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(
  defineProps<{
    title: string
    eyebrow?: string
    description?: string
    width?: 'medium' | 'wide'
    scrollKey?: string | number
  }>(),
  { eyebrow: 'GridPBX', description: '', width: 'wide', scrollKey: '' },
)

const emit = defineEmits<{ close: [] }>()
const content = ref<HTMLElement | null>(null)

watch(
  () => props.scrollKey,
  async () => {
    await nextTick()
    content.value?.scrollTo({ top: 0 })
  },
)
</script>

<template>
  <TransitionRoot appear :show="true" as="template">
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
        <div class="fixed inset-0 bg-slate-950/35 backdrop-blur-[1px]" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
          <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-8 sm:pl-12">
            <TransitionChild
              as="template"
              enter="transform transition ease-out duration-300"
              enter-from="translate-x-full"
              enter-to="translate-x-0"
              leave="transform transition ease-in duration-200"
              leave-from="translate-x-0"
              leave-to="translate-x-full"
            >
              <DialogPanel
                class="pointer-events-auto flex w-screen flex-col bg-slate-50 shadow-2xl"
                :class="width === 'medium' ? 'max-w-2xl' : 'max-w-5xl'"
              >
                <header
                  class="flex shrink-0 items-start gap-4 border-b border-slate-200 bg-white px-5 py-5 sm:px-7"
                >
                  <div class="min-w-0">
                    <p class="mb-1 text-[11px] font-medium text-slate-400">{{ eyebrow }}</p>
                    <DialogTitle class="text-xl font-semibold tracking-tight text-slate-800">
                      {{ title }}
                    </DialogTitle>
                    <p v-if="description" class="mt-1 text-xs leading-5 text-slate-500">
                      {{ description }}
                    </p>
                  </div>
                  <button
                    type="button"
                    class="ml-auto grid size-9 shrink-0 place-items-center rounded-md border border-slate-200 text-slate-500 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-600"
                    aria-label="Close panel"
                    @click="emit('close')"
                  >
                    <XMarkIcon class="size-5" />
                  </button>
                </header>
                <div
                  ref="content"
                  data-testid="slide-over-content"
                  class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-7"
                >
                  <slot />
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
