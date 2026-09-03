<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline'
import SlideOver from './SlideOver.vue'

type SlideOverWidth = 'medium' | 'wide' | 'extra-wide'

withDefaults(
  defineProps<{
    title: string
    eyebrow?: string
    description?: string
    width?: SlideOverWidth
    scrollKey?: string | number
    embedded?: boolean
    embeddedHeader?: boolean
  }>(),
  {
    eyebrow: 'GridPBX',
    description: '',
    width: 'wide',
    scrollKey: '',
    embedded: false,
    embeddedHeader: true,
  },
)

const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <section v-if="embedded" data-testid="embedded-crud-panel" :aria-label="title" class="grid gap-5">
    <header v-if="embeddedHeader" class="card-surface flex items-start gap-4 px-5 py-5 sm:px-7">
      <div class="min-w-0">
        <p class="mb-1 text-[11px] font-medium text-slate-400">{{ eyebrow }}</p>
        <h2 class="text-xl font-semibold tracking-tight text-slate-800">{{ title }}</h2>
        <p v-if="description" class="mt-1 text-xs leading-5 text-slate-500">
          {{ description }}
        </p>
      </div>
      <button
        type="button"
        class="group ml-auto grid size-9 shrink-0 place-items-center rounded-full text-slate-400 transition-colors hover:bg-brand-50 hover:text-brand-600 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500"
        aria-label="Close panel"
        @click="emit('close')"
      >
        <XMarkIcon
          class="size-6 transition-transform duration-150 ease-out group-hover:scale-110"
        />
      </button>
    </header>
    <div data-testid="embedded-crud-content">
      <slot />
    </div>
  </section>

  <SlideOver
    v-else
    :title="title"
    :eyebrow="eyebrow"
    :description="description"
    :width="width"
    :scroll-key="scrollKey"
    @close="emit('close')"
  >
    <slot />
  </SlideOver>
</template>
