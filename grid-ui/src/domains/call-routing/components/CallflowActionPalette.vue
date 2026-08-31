<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon } from '@heroicons/vue/20/solid'
import { ArrowUturnLeftIcon, ArrowsPointingOutIcon } from '@heroicons/vue/24/outline'
import SearchInput from '@/shared/components/SearchInput.vue'
import { callflowActionAppearance } from '../catalog/callflowActionAppearance'
import {
  callflowActionDestinationType,
  callflowActionCatalog,
  searchableCallflowActions,
  type CallflowAction,
} from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import CallflowNodeCard from './CallflowNodeCard.vue'

const props = withDefaults(
  defineProps<{
    enabled?: boolean
    dragEnabled?: boolean
    compact?: boolean
    movable?: boolean
    floating?: boolean
    rootOnly?: boolean
  }>(),
  {
    enabled: false,
    dragEnabled: false,
    compact: false,
    movable: false,
    floating: false,
    rootOnly: false,
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
const categories = computed(() => {
  const visibleCategories = callflowActionCatalog
    .map((category) => ({
      ...category,
      actions: category.actions.filter((action) => {
        if (!normalizedSearch.value) return true

        return [action.label, action.module, action.description].some((value) =>
          value.toLowerCase().includes(normalizedSearch.value),
        )
      }),
    }))
    .filter((category) => category.actions.length > 0)

  if (!normalizedSearch.value) return visibleCategories

  const compatibilityMatches = searchableCallflowActions.filter((action) =>
    [action.label, action.module, action.description].some((value) =>
      value.toLowerCase().includes(normalizedSearch.value),
    ),
  )

  if (!compatibilityMatches.length) return visibleCategories

  const advanced = visibleCategories.find((category) => category.id === 'advanced')
  if (advanced) {
    advanced.actions.push(...compatibilityMatches)
    return visibleCategories
  }

  return [
    {
      id: 'advanced',
      label: 'Advanced',
      description: 'Additional actions shown by the Switch callflow editor.',
      actions: compatibilityMatches,
    },
    ...visibleCategories,
  ]
})
const resultCount = computed(() =>
  categories.value.reduce((total, category) => total + category.actions.length, 0),
)
const openCategoryId = ref<string | null>('basic')

watch(categories, (matchingCategories) => {
  if (normalizedSearch.value) {
    openCategoryId.value = matchingCategories[0]?.id ?? null
    return
  }

  if (!matchingCategories.some((category) => category.id === openCategoryId.value)) {
    openCategoryId.value = matchingCategories[0]?.id ?? null
  }
})

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

const statusDotClass = {
  guided: 'bg-emerald-500',
  planned: 'bg-blue-500',
  restricted: 'bg-amber-500',
} as const

function choose(action: CallflowAction): void {
  if (props.enabled && isActionEnabled(action)) emit('choose', action)
}

function startActionDrag(event: DragEvent, action: CallflowAction): void {
  if (!props.dragEnabled || !isActionEnabled(action)) {
    event.preventDefault()
    return
  }

  event.dataTransfer?.setData('application/x-gridpbx-callflow-action', action.id)
  event.dataTransfer?.setData('text/plain', action.module)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy'
  emit('action-drag-start', action)
}

function isActionEnabled(action: CallflowAction): boolean {
  return (
    action.status === 'guided' &&
    (!props.rootOnly ||
      (action.action === undefined && callflowActionDestinationType(action.module) !== null))
  )
}

function toggleCategory(categoryId: string): void {
  openCategoryId.value = openCategoryId.value === categoryId ? null : categoryId
}
</script>

<template>
  <section
    aria-label="Callflow action catalog"
    class="overflow-hidden rounded-lg border"
    :class="compact ? 'border-slate-700 bg-callflow-node' : 'border-slate-200 bg-white'"
  >
    <header
      class="border-b"
      :class="compact ? 'border-slate-700 px-2.5 py-2' : 'border-slate-200 px-4 py-3'"
    >
      <div class="flex items-center gap-2">
        <h3 class="text-xs font-semibold" :class="compact ? 'text-white' : 'text-slate-700'">
          Action catalog
        </h3>
        <button
          v-if="movable"
          type="button"
          aria-label="Move action palette"
          title="Drag to move the action palette"
          class="ml-auto grid touch-none place-items-center rounded-md border"
          :class="[
            compact
              ? 'border-slate-600 bg-slate-800 text-slate-300 hover:border-blue-400 hover:text-white'
              : 'border-slate-200 bg-white text-slate-500 hover:border-brand-300 hover:text-brand-600',
            compact ? 'size-6' : 'size-7',
          ]"
          @pointerdown="emit('drag-start', $event)"
        >
          <ArrowsPointingOutIcon :class="compact ? 'size-3.5' : 'size-4'" />
        </button>
        <button
          v-if="movable && floating"
          type="button"
          aria-label="Dock action palette"
          title="Return the action palette to the right rail"
          class="grid place-items-center rounded-md border"
          :class="[
            compact
              ? 'border-slate-600 bg-slate-800 text-slate-300 hover:border-blue-400 hover:text-white'
              : 'border-slate-200 bg-white text-slate-500 hover:border-brand-300 hover:text-brand-600',
            compact ? 'size-6' : 'size-7',
          ]"
          @click="emit('dock')"
        >
          <ArrowUturnLeftIcon :class="compact ? 'size-3.5' : 'size-4'" />
        </button>
      </div>
      <p v-if="!compact" class="mt-0.5 text-[10px] text-slate-500">
        Switch modules available now or planned for the visual editor
      </p>
      <SearchInput
        v-model="search"
        label="Search callflow actions"
        :class="compact ? 'mt-2' : 'mt-3'"
        placeholder="Search actions…"
        :input-class="
          compact
            ? 'h-7 !border-slate-600 !bg-slate-800 px-2 text-[10px] !text-white placeholder:!text-slate-400'
            : 'h-9 bg-white text-xs'
        "
      />
      <p
        class="font-medium"
        :class="compact ? 'mt-1 text-[9px] text-slate-300' : 'mt-2 text-[10px] text-slate-500'"
        aria-live="polite"
      >
        {{ resultCount }} {{ resultCount === 1 ? 'action' : 'actions' }}
      </p>
    </header>

    <div
      v-if="categories.length"
      data-callflow-palette-categories
      class="divide-y"
      :class="compact ? 'divide-slate-700' : 'divide-slate-200'"
    >
      <Disclosure
        v-for="category in categories"
        :key="`${category.id}:${openCategoryId === category.id}`"
        v-slot="{ open }"
        :default-open="openCategoryId === category.id"
        as="section"
      >
        <DisclosureButton
          class="flex w-full items-center text-left focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-500"
          :class="
            compact
              ? 'gap-1.5 px-2.5 py-1.5 hover:bg-slate-800'
              : 'gap-3 px-4 py-3 hover:bg-slate-50'
          "
          @click="toggleCategory(category.id)"
        >
          <span class="min-w-0">
            <span
              :class="
                compact
                  ? 'block text-[10px] font-semibold text-slate-100'
                  : 'block text-xs font-semibold text-slate-700'
              "
            >
              {{ category.label }}
            </span>
            <span v-if="!compact" class="mt-0.5 block text-[10px] text-slate-500">
              {{ category.description }}
            </span>
          </span>
          <span
            class="ml-auto text-[10px] font-semibold"
            :class="compact ? 'text-slate-300' : 'text-slate-500'"
          >
            {{ category.actions.length }}
          </span>
          <ChevronDownIcon
            class="shrink-0 transition-transform"
            :class="[
              compact ? 'size-3 text-slate-300' : 'size-4 text-slate-500',
              open && 'rotate-180',
            ]"
          />
        </DisclosureButton>
        <DisclosurePanel
          class="grid"
          :class="
            compact
              ? 'grid-cols-2 gap-1 bg-slate-950/20 px-1.5 pb-2'
              : 'gap-2 bg-slate-50/60 px-4 pb-4 sm:grid-cols-2'
          "
        >
          <button
            v-for="action in category.actions"
            :key="action.id"
            type="button"
            :disabled="!isActionEnabled(action) || (!enabled && !dragEnabled)"
            :draggable="dragEnabled && isActionEnabled(action)"
            :title="`${action.label} · ${action.module} · ${statusLabel[action.status]}`"
            class="relative rounded-md text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-default"
            :class="[
              compact ? 'h-14' : 'p-3',
              !compact && callflowActionAppearance(action.module).paletteBorder,
              dragEnabled && isActionEnabled(action) && 'cursor-grab active:cursor-grabbing',
            ]"
            :aria-label="
              !isActionEnabled(action)
                ? undefined
                : enabled
                  ? rootOnly
                    ? `Use ${action.label} as root action`
                    : `Add ${action.label}`
                  : dragEnabled
                    ? `Drag ${action.label} onto route`
                    : `${action.label} unavailable in read-only mode`
            "
            @click="choose(action)"
            @dragstart="startActionDrag($event, action)"
            @dragend="emit('action-drag-end')"
          >
            <CallflowNodeCard
              v-if="compact"
              variant="palette"
              :label="action.label"
              :module="action.module"
              :icon="callflowActionIcon(action.module, { action: action.action })"
              border-class="border-white/10"
              :icon-class="callflowActionAppearance(action.module).paletteIcon"
            />
            <div v-else class="flex items-start gap-2">
              <span
                class="grid size-7 shrink-0 place-items-center rounded-md"
                :class="callflowActionAppearance(action.module).paletteIcon"
              >
                <component
                  :is="callflowActionIcon(action.module, { action: action.action })"
                  class="size-3.5"
                />
              </span>
              <div class="min-w-0">
                <h4 class="truncate text-[11px] font-semibold text-slate-700">
                  {{ action.label }}
                </h4>
                <p class="mt-0.5 truncate font-mono text-[9px] text-slate-500">
                  {{ action.module }}
                </p>
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
              class="absolute top-1 right-1 z-10 size-1.5 shrink-0 rounded-full"
              :class="statusDotClass[action.status]"
              :title="statusLabel[action.status]"
            >
              <span class="sr-only">{{ statusLabel[action.status] }}</span>
            </span>
            <p v-if="!props.compact" class="mt-2 text-[10px] leading-4 text-slate-600">
              {{ action.description }}
            </p>
            <p
              v-if="!compact && isActionEnabled(action) && (enabled || dragEnabled)"
              class="mt-2 text-[9px] font-semibold text-brand-600"
            >
              {{
                enabled
                  ? rootOnly
                    ? 'Use as the root action'
                    : 'Add after the selected node'
                  : 'Drag onto an eligible route node'
              }}
            </p>
          </button>
        </DisclosurePanel>
      </Disclosure>
    </div>
    <p
      v-else
      class="p-6 text-center text-xs"
      :class="compact ? 'text-slate-300' : 'text-slate-500'"
    >
      No callflow actions match this search.
    </p>
  </section>
</template>
