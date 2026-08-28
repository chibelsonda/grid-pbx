<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{
    title: string
    eyebrow?: string
    description?: string
    width?: 'medium' | 'wide'
  }>(),
  { eyebrow: 'GridPBX', description: '', width: 'wide' },
)

const emit = defineEmits<{ close: [] }>()

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  document.body.classList.add('overflow-hidden')
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" :aria-label="title">
      <Transition
        appear
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
      >
        <button
          type="button"
          class="absolute inset-0 bg-slate-950/35 backdrop-blur-[1px]"
          aria-label="Close panel"
          @click="emit('close')"
        />
      </Transition>

      <Transition
        appear
        enter-active-class="transform transition duration-300 ease-out"
        enter-from-class="translate-x-full"
        leave-active-class="transform transition duration-200 ease-in"
        leave-to-class="translate-x-full"
      >
        <aside
          class="absolute inset-y-0 right-0 flex w-full flex-col bg-slate-50 shadow-2xl"
          :class="width === 'medium' ? 'max-w-2xl' : 'max-w-5xl'"
        >
          <header
            class="flex shrink-0 items-start gap-4 border-b border-slate-200 bg-white px-5 py-5 sm:px-7"
          >
            <div class="min-w-0">
              <p class="mb-1 text-[11px] font-medium text-slate-400">{{ eyebrow }}</p>
              <h1 class="text-xl font-semibold tracking-tight text-slate-800">{{ title }}</h1>
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
          <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-7">
            <slot />
          </div>
        </aside>
      </Transition>
    </div>
  </Teleport>
</template>
