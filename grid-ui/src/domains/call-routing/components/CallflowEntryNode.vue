<script setup lang="ts">
import { computed } from 'vue'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'
import { callflowEntryIcon } from '../catalog/callflowActionIcons'

export interface CallflowEntryPoint {
  value: string
  kind: string
}

const props = withDefaults(
  defineProps<{
    name?: string | null
    entries?: CallflowEntryPoint[]
    editable?: boolean
    editLabel?: string
  }>(),
  {
    name: null,
    entries: () => [],
    editable: false,
    editLabel: 'Edit callflow name and numbers',
  },
)

const emit = defineEmits<{ edit: []; 'add-entry': [] }>()

const accessibleLabel = computed(
  () => `Callflow entry${props.entries[0] ? `: ${props.entries[0].value}` : ''}`,
)
const slots = computed(() => {
  const first = props.entries[0]
  const second = props.entries[1]

  if (!first) {
    return [{ primary: 'Click to add number', secondary: 'Number', empty: true }]
  }

  return [
    { primary: first.value, secondary: first.kind, empty: false },
    second
      ? {
          primary: second.value,
          secondary:
            props.entries.length > 2
              ? `${second.kind} · +${props.entries.length - 2} more`
              : second.kind,
          empty: false,
        }
      : { primary: 'Click to add number', secondary: 'Number', empty: true },
  ]
})
</script>

<template>
  <article
    role="treeitem"
    :aria-label="accessibleLabel"
    class="h-14 w-80 overflow-hidden rounded-md border border-brand-500 bg-white shadow-sm"
  >
    <header class="flex h-6 items-center gap-2 bg-brand-600 px-2 text-white">
      <component :is="callflowEntryIcon" class="size-3.5" />
      <p class="text-[10px] font-semibold">Callflow</p>
      <span v-if="name" class="ml-auto max-w-36 truncate text-[9px] text-blue-100">
        {{ name }}
      </span>
      <button
        v-if="editable"
        type="button"
        :aria-label="editLabel"
        :title="editLabel"
        class="grid size-5 shrink-0 place-items-center rounded-sm text-blue-100 hover:bg-white/10 hover:text-white"
        @click="$emit('edit')"
      >
        <PencilSquareIcon class="size-3.5" />
      </button>
    </header>
    <div
      class="grid h-8 divide-x divide-slate-200 text-left"
      :class="slots.length === 1 ? 'grid-cols-1' : 'grid-cols-2'"
    >
      <component
        :is="editable ? 'button' : 'div'"
        v-for="(entry, index) in slots"
        :key="index"
        :type="editable ? 'button' : undefined"
        :aria-label="
          editable
            ? entry.empty
              ? 'Add callflow entry number'
              : `${entry.primary}. ${editLabel}`
            : undefined
        "
        class="min-w-0 px-2 py-0.5 text-left transition"
        :class="
          editable &&
          'cursor-pointer hover:bg-brand-50 focus-visible:bg-brand-50 focus-visible:outline-none'
        "
        @click="editable && (entry.empty ? emit('add-entry') : emit('edit'))"
      >
        <span
          class="block truncate font-mono text-[9px] font-semibold"
          :class="entry.empty ? 'text-slate-500' : 'text-slate-700'"
        >
          {{ entry.primary }}
        </span>
        <span class="block truncate text-[8px] text-slate-400">{{ entry.secondary }}</span>
      </component>
    </div>
  </article>
</template>
