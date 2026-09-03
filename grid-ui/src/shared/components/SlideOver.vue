<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

export type SlideOverWidth = 'narrow' | 'medium' | 'wide' | 'extra-wide'

const widthClasses: Record<SlideOverWidth, string> = {
  narrow: 'max-w-lg',
  medium: 'max-w-2xl',
  wide: 'max-w-5xl',
  'extra-wide': 'max-w-7xl',
}

const props = withDefaults(
  defineProps<{
    show?: boolean
    title: string
    eyebrow?: string
    description?: string
    width?: SlideOverWidth
    scrollKey?: string | number
    compactHeader?: boolean
    closeLabel?: string
    contentClass?: string
    overlayClass?: string
  }>(),
  {
    show: true,
    eyebrow: '',
    description: '',
    width: 'wide',
    scrollKey: '',
    compactHeader: false,
    closeLabel: 'Close panel',
    contentClass: 'p-4 sm:p-6 lg:p-7',
    overlayClass: 'bg-slate-950/35 backdrop-blur-[1px]',
  },
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
  <TransitionRoot appear :show="show" as="template">
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
        <div class="fixed inset-0" :class="overlayClass" />
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
                data-testid="slide-over-panel"
                :data-width="width"
                class="pointer-events-auto flex min-w-0 w-screen flex-col bg-slate-50 shadow-2xl"
                :class="widthClasses[width]"
              >
                <header
                  class="flex shrink-0 border-b border-slate-200 bg-white"
                  :class="
                    compactHeader
                      ? 'items-center gap-3 px-5 py-4'
                      : 'items-start gap-4 px-5 py-5 sm:px-7'
                  "
                >
                  <slot name="leading" />
                  <div class="min-w-0">
                    <p v-if="eyebrow" class="mb-1 text-[11px] font-medium text-slate-400">
                      {{ eyebrow }}
                    </p>
                    <DialogTitle
                      class="font-semibold text-slate-800"
                      :class="compactHeader ? 'text-base' : 'text-xl tracking-tight'"
                    >
                      {{ title }}
                    </DialogTitle>
                    <p
                      v-if="description"
                      class="text-slate-500"
                      :class="compactHeader ? 'mt-0.5 text-[11px]' : 'mt-1 text-xs leading-5'"
                    >
                      {{ description }}
                    </p>
                  </div>
                  <button
                    type="button"
                    class="group ml-auto grid size-9 shrink-0 place-items-center rounded-full text-slate-400 transition-colors hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500"
                    :aria-label="closeLabel"
                    @click="emit('close')"
                  >
                    <XMarkIcon
                      class="size-6 transition-transform duration-150 ease-out group-hover:scale-110"
                    />
                  </button>
                </header>
                <div
                  ref="content"
                  data-testid="slide-over-content"
                  class="min-h-0 flex-1 overflow-y-auto"
                  :class="contentClass"
                >
                  <slot />
                </div>
                <slot name="footer" />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
