<script setup lang="ts">
import { computed } from 'vue'
import { ArrowDownIcon, ArrowUpIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{
  label: string
  description: string
  options: readonly string[]
  error?: string | null
}>()
const selected = defineModel<string[]>({ required: true })
const available = computed(() => props.options.filter((value) => !selected.value.includes(value)))

function add(value: string): void {
  selected.value = [...selected.value, value]
}

function remove(index: number): void {
  selected.value = selected.value.filter((_, selectedIndex) => selectedIndex !== index)
}

function move(index: number, direction: -1 | 1): void {
  const target = index + direction
  if (target < 0 || target >= selected.value.length) return

  const reordered = [...selected.value]
  const [value] = reordered.splice(index, 1)
  if (value) reordered.splice(target, 0, value)
  selected.value = reordered
}
</script>

<template>
  <section class="grid gap-4">
    <div>
      <h3 class="text-xs font-semibold text-slate-700">{{ label }}</h3>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">{{ description }}</p>
    </div>

    <div
      class="overflow-hidden rounded-lg border"
      :class="error ? 'border-danger' : 'border-slate-200'"
      :aria-invalid="Boolean(error)"
      :aria-label="`${label} selected values`"
      role="list"
    >
      <div
        v-for="(value, index) in selected"
        :key="value"
        class="flex items-center gap-3 border-b border-slate-100 px-3 py-2.5 last:border-b-0"
        role="listitem"
      >
        <span
          class="grid size-7 shrink-0 place-items-center rounded-md bg-brand-50 text-[10px] font-bold text-brand-700"
        >
          {{ index + 1 }}
        </span>
        <span class="min-w-0 flex-1 font-mono text-xs font-semibold text-slate-700">{{
          value
        }}</span>
        <button
          type="button"
          :disabled="index === 0"
          :aria-label="`Move ${value} up`"
          class="rounded p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-20"
          @click="move(index, -1)"
        >
          <ArrowUpIcon class="size-4" />
        </button>
        <button
          type="button"
          :disabled="index === selected.length - 1"
          :aria-label="`Move ${value} down`"
          class="rounded p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-20"
          @click="move(index, 1)"
        >
          <ArrowDownIcon class="size-4" />
        </button>
        <button
          type="button"
          :aria-label="`Remove ${value}`"
          class="rounded p-1.5 text-slate-500 transition hover:bg-red-50 hover:text-danger"
          @click="remove(index)"
        >
          <XMarkIcon class="size-4" />
        </button>
      </div>
      <p v-if="selected.length === 0" class="px-4 py-6 text-center text-xs text-slate-500">
        No values selected.
      </p>
    </div>

    <div v-if="available.length" class="grid gap-2">
      <span class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
        Available values
      </span>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="value in available"
          :key="value"
          type="button"
          :aria-label="`Add ${value} to ${label}`"
          class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-2 font-mono text-[11px] font-semibold text-slate-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
          @click="add(value)"
        >
          <PlusIcon class="size-3.5" />
          {{ value }}
        </button>
      </div>
    </div>

    <span v-if="error" class="text-[11px] text-danger">{{ error }}</span>
  </section>
</template>
