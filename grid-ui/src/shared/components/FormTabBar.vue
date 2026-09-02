<script setup lang="ts">
import type { Component } from 'vue'
import { Tab, TabGroup, TabList } from '@headlessui/vue'

const props = withDefaults(
  defineProps<{
    tabs: readonly { key: string; label: string; icon?: Component }[]
    ariaLabel?: string
    sticky?: boolean
    compact?: boolean
    variant?: 'underline' | 'segmented'
    embedded?: boolean
    showTabList?: boolean
  }>(),
  {
    ariaLabel: 'Form sections',
    sticky: false,
    compact: false,
    variant: 'underline',
    embedded: false,
    showTabList: true,
  },
)

const selectedIndex = defineModel<number>({ default: 0 })

function tabListClasses(): string[] {
  const classes =
    props.variant === 'segmented' ? segmentedTabListClasses() : underlineTabListClasses()

  if (props.sticky) {
    classes.push('sticky', 'top-0', 'z-30')
    if (props.variant === 'underline') classes.push('backdrop-blur')
  }

  return classes
}

function segmentedTabListClasses(): string[] {
  return [
    'w-fit',
    'max-w-full',
    'gap-0.5',
    'rounded-lg',
    'border',
    'border-slate-200',
    'bg-slate-100',
    'p-0.5',
    'shadow-inner',
  ]
}

function underlineTabListClasses(): string[] {
  return [
    ...(props.embedded ? [] : ['card-surface']),
    'gap-1',
    'overflow-x-auto',
    'border-b',
    'border-slate-100',
    'bg-slate-50/70',
    'px-3',
    'pt-3',
    'sm:px-4',
  ]
}

function tabButtonClasses(index: number): string[] {
  if (props.variant === 'segmented') {
    const classes = ['h-8', 'min-w-28', 'justify-center', 'rounded-md', 'border', 'px-4']
    classes.push(
      ...(index === selectedIndex.value
        ? ['border-slate-200', 'bg-white', 'text-brand-600', 'shadow-sm']
        : [
            'border-transparent',
            'bg-transparent',
            'text-slate-500',
            'hover:bg-white/60',
            'hover:text-slate-700',
          ]),
    )

    return classes
  }

  return [
    'h-10',
    'shrink-0',
    'border-b-2',
    props.compact ? 'gap-1.5 px-2' : 'gap-2 px-3',
    index === selectedIndex.value
      ? 'border-brand-500 text-brand-700'
      : 'border-transparent text-slate-500 hover:text-slate-700',
  ]
}
</script>

<template>
  <TabGroup as="template" :selected-index="selectedIndex" @change="selectedIndex = $event">
    <TabList v-show="showTabList" :aria-label="ariaLabel" class="flex" :class="tabListClasses()">
      <Tab v-for="(tab, index) in tabs" :key="tab.key" as="template">
        <button
          type="button"
          class="inline-flex items-center text-xs font-semibold outline-none transition focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2"
          :class="tabButtonClasses(index)"
        >
          <component :is="tab.icon" v-if="tab.icon" class="size-4" />
          {{ tab.label }}
        </button>
      </Tab>
    </TabList>
    <slot />
  </TabGroup>
</template>
