<script setup lang="ts">
import { computed, ref } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon } from '@heroicons/vue/20/solid'
import { ArrowUturnLeftIcon, ArrowsPointingOutIcon } from '@heroicons/vue/24/outline'
import SearchInput from '@/shared/components/SearchInput.vue'
import { callflowActionCatalog, type CallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'

const props = withDefaults(
  defineProps<{
    enabled?: boolean
    dragEnabled?: boolean
    compact?: boolean
    movable?: boolean
    floating?: boolean
  }>(),
  {
    enabled: false,
    dragEnabled: false,
    compact: false,
    movable: false,
    floating: false,
  },
)
const emit = defineEmits<{
  choose: [action: CallflowAction]
  'action-drag-start': [action: CallflowAction]
  'action-drag-end': []
  'drag-start': [event: PointerEvent]
  dock: []
}>()

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

function choose(action: CallflowAction): void {
  if (props.enabled && action.status === 'guided') emit('choose', action)
}

function startActionDrag(event: DragEvent, action: CallflowAction): void {
  if (!props.dragEnabled || action.status !== 'guided') {
    event.preventDefault()
    return
  }

  event.dataTransfer?.setData('application/x-gridpbx-callflow-action', action.module)
  event.dataTransfer?.setData('text/plain', action.module)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy'
  emit('action-drag-start', action)
}
</script>

<template>
  <section
    aria-label="Callflow action catalog"
    class="overflow-hidden rounded-lg border border-slate-200 bg-white"
  >
    <header class="border-b border-slate-200 px-4 py-3">
      <div class="flex items-center gap-2">
        <h3 class="text-xs font-semibold text-slate-700">Action catalog</h3>
        <button
          v-if="movable"
          type="button"
          aria-label="Move action palette"
          title="Drag to move the action palette"
          class="ml-auto grid size-7 touch-none place-items-center rounded-md border border-slate-200 bg-white text-slate-500 hover:border-brand-300 hover:text-brand-600"
          @pointerdown="emit('drag-start', $event)"
        >
          <ArrowsPointingOutIcon class="size-4" />
        </button>
        <button
          v-if="movable && floating"
          type="button"
          aria-label="Dock action palette"
          title="Return the action palette to the right rail"
          class="grid size-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 hover:border-brand-300 hover:text-brand-600"
          @click="emit('dock')"
        >
          <ArrowUturnLeftIcon class="size-4" />
        </button>
      </div>
      <p class="mt-0.5 text-[10px] text-slate-500">
        Switch modules available now or planned for the visual editor
      </p>
      <SearchInput
        v-model="search"
        label="Search callflow actions"
        class="mt-3"
        placeholder="Search actions…"
        input-class="h-9 bg-white text-xs"
      />
      <p class="mt-2 text-[10px] font-medium text-slate-500" aria-live="polite">
        {{ resultCount }} {{ resultCount === 1 ? 'action' : 'actions' }}
      </p>
    </header>

    <div
      v-if="categories.length"
      class="divide-y divide-slate-200"
      :class="compact && 'max-h-[calc(100vh-14rem)] overflow-y-auto overscroll-contain'"
    >
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
          class="grid gap-2 bg-slate-50/60 pb-4"
          :class="compact ? 'grid-cols-2 px-2' : 'px-4 sm:grid-cols-2'"
        >
          <button
            v-for="action in category.actions"
            :key="action.module"
            type="button"
            :disabled="action.status !== 'guided'"
            :draggable="dragEnabled && action.status === 'guided'"
            class="rounded-md border border-slate-200 bg-white text-left transition enabled:hover:border-brand-300 enabled:hover:bg-brand-50/40 disabled:cursor-default"
            :class="[
              compact ? 'p-2.5' : 'p-3',
              dragEnabled && action.status === 'guided' && 'cursor-grab active:cursor-grabbing',
            ]"
            :aria-label="
              action.status !== 'guided'
                ? undefined
                : enabled
                  ? `Add ${action.label}`
                  : dragEnabled
                    ? `Drag ${action.label} onto route`
                    : undefined
            "
            @click="choose(action)"
            @dragstart="startActionDrag($event, action)"
            @dragend="emit('action-drag-end')"
          >
            <div class="flex items-start gap-2">
              <span
                class="grid size-7 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600"
              >
                <component :is="callflowActionIcon(action.module)" class="size-3.5" />
              </span>
              <div class="min-w-0">
                <h4 class="text-[11px] font-semibold text-slate-700">{{ action.label }}</h4>
                <p class="mt-0.5 font-mono text-[9px] text-slate-500">{{ action.module }}</p>
              </div>
              <span
                v-if="!compact"
                class="ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-semibold"
                :class="statusClass[action.status]"
              >
                {{ statusLabel[action.status] }}
              </span>
            </div>
            <span
              v-if="compact"
              class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[9px] font-semibold"
              :class="statusClass[action.status]"
            >
              {{ statusLabel[action.status] }}
            </span>
            <p v-if="!props.compact" class="mt-2 text-[10px] leading-4 text-slate-600">
              {{ action.description }}
            </p>
            <p
              v-if="action.status === 'guided' && (enabled || dragEnabled)"
              class="mt-2 text-[9px] font-semibold text-brand-600"
            >
              {{ enabled ? 'Add after the selected node' : 'Drag onto an eligible route node' }}
            </p>
          </button>
        </DisclosurePanel>
      </Disclosure>
    </div>
    <p v-else class="p-6 text-center text-xs text-slate-500">
      No callflow actions match this search.
    </p>
  </section>
</template>
