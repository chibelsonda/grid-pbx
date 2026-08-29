<script setup lang="ts">
import { computed, ref } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon } from '@heroicons/vue/20/solid'
import SearchInput from '@/shared/components/SearchInput.vue'
import { callflowActionCatalog } from '../catalog/callflowActionCatalog'

const search = ref('')
const normalizedSearch = computed(() => search.value.trim().toLowerCase())
const categories = computed(() =>
  callflowActionCatalog
    .map((category) => ({
      ...category,
      actions: category.actions.filter((action) => {
        if (!normalizedSearch.value) return true

        return [action.label, action.module, action.description].some((value) =>
          value.toLowerCase().includes(normalizedSearch.value),
        )
      }),
    }))
    .filter((category) => category.actions.length > 0),
)
const resultCount = computed(() =>
  categories.value.reduce((total, category) => total + category.actions.length, 0),
)

const statusLabel = {
  guided: 'Guided now',
  planned: 'Visual editor planned',
  restricted: 'Capability required',
} as const

const statusClass = {
  guided: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  planned: 'border-blue-200 bg-blue-50 text-blue-700',
  restricted: 'border-amber-200 bg-amber-50 text-amber-700',
} as const
</script>

<template>
  <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <header class="border-b border-slate-200 px-4 py-3">
      <h3 class="text-xs font-semibold text-slate-700">Action catalog</h3>
      <p class="mt-0.5 text-[10px] text-slate-500">
        Switch modules available now or planned for the visual editor
      </p>
      <SearchInput v-model="search" label="Search callflow actions" class="mt-3" placeholder="Search actions…" input-class="h-9 bg-white text-xs" />
      <p class="mt-2 text-[10px] font-medium text-slate-500" aria-live="polite">
        {{ resultCount }} {{ resultCount === 1 ? 'action' : 'actions' }}
      </p>
    </header>

    <div v-if="categories.length" class="divide-y divide-slate-200">
      <Disclosure
        v-for="category in categories"
        :key="category.id"
        v-slot="{ open }"
        :default-open="category.id === 'routing'"
        as="section"
      >
        <DisclosureButton
          class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-500"
        >
          <span class="min-w-0">
            <span class="block text-xs font-semibold text-slate-700">{{ category.label }}</span>
            <span class="mt-0.5 block text-[10px] text-slate-500">{{ category.description }}</span>
          </span>
          <span class="ml-auto text-[10px] font-semibold text-slate-500">
            {{ category.actions.length }}
          </span>
          <ChevronDownIcon
            class="size-4 shrink-0 text-slate-500 transition-transform"
            :class="open && 'rotate-180'"
          />
        </DisclosureButton>
        <DisclosurePanel
          :static="Boolean(normalizedSearch)"
          class="grid gap-2 bg-slate-50/60 px-4 pb-4 sm:grid-cols-2"
        >
          <article
            v-for="action in category.actions"
            :key="action.module"
            class="rounded-md border border-slate-200 bg-white p-3"
          >
            <div class="flex items-start gap-2">
              <div class="min-w-0">
                <h4 class="text-[11px] font-semibold text-slate-700">{{ action.label }}</h4>
                <p class="mt-0.5 font-mono text-[9px] text-slate-500">{{ action.module }}</p>
              </div>
              <span
                class="ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-semibold"
                :class="statusClass[action.status]"
              >
                {{ statusLabel[action.status] }}
              </span>
            </div>
            <p class="mt-2 text-[10px] leading-4 text-slate-600">{{ action.description }}</p>
          </article>
        </DisclosurePanel>
      </Disclosure>
    </div>
    <p v-else class="p-6 text-center text-xs text-slate-500">
      No callflow actions match this search.
    </p>
  </section>
</template>
