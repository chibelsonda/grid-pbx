<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon } from '@heroicons/vue/20/solid'

withDefaults(defineProps<{ title: string; padded?: boolean }>(), { padded: true })
</script>

<template>
  <Disclosure v-slot="{ open }" as="section" class="card-surface overflow-hidden">
    <DisclosureButton
      class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-xs font-semibold text-slate-600 hover:bg-slate-50"
    >
      {{ title }}
      <ChevronDownIcon
        class="size-4 text-slate-400 transition-transform"
        :class="open && 'rotate-180'"
      />
    </DisclosureButton>
    <DisclosurePanel class="border-t border-slate-100" :class="padded && 'p-5'">
      <slot />
    </DisclosurePanel>
  </Disclosure>
</template>
