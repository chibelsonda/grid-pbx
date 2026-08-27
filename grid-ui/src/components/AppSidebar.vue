<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import {
  ArrowPathRoundedSquareIcon,
  BuildingOffice2Icon,
  ChartBarSquareIcon,
  ChevronDoubleLeftIcon,
  ChevronDoubleRightIcon,
  ClockIcon,
  Cog6ToothIcon,
  DevicePhoneMobileIcon,
  HashtagIcon,
  MicrophoneIcon,
  PhoneArrowUpRightIcon,
  Squares2X2Icon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps<{
  collapsed: boolean
  mobileOpen: boolean
}>()

const emit = defineEmits<{
  collapse: []
  closeMobile: []
}>()

type NavigationItem = {
  label: string
  to: string
  icon: Component
  badge?: string
}

type NavigationSection = {
  label: string
  items: NavigationItem[]
}

const route = useRoute()

const sections: NavigationSection[] = [
  {
    label: 'Overview',
    items: [{ label: 'Dashboard', to: '/', icon: ChartBarSquareIcon }],
  },
  {
    label: 'Cloud phone system',
    items: [
      { label: 'People & Extensions', to: '/extensions', icon: UserGroupIcon },
      { label: 'Devices', to: '/devices', icon: DevicePhoneMobileIcon },
      { label: 'Phone Numbers', to: '/phone-numbers', icon: HashtagIcon },
      { label: 'Call Routing', to: '/call-routing', icon: ArrowPathRoundedSquareIcon },
      { label: 'Voicemail & Media', to: '/voicemail', icon: MicrophoneIcon },
      { label: 'Call History', to: '/call-history', icon: ClockIcon },
    ],
  },
  {
    label: 'Workspace',
    items: [
      { label: 'Accounts', to: '/accounts', icon: BuildingOffice2Icon },
      { label: 'Settings', to: '/settings', icon: Cog6ToothIcon },
    ],
  },
]

const sidebarClasses = computed(() => [
  props.collapsed ? 'lg:w-20' : 'lg:w-[280px]',
  props.mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
])

const isActive = (to: string) => (to === '/' ? route.path === '/' : route.path.startsWith(to))
</script>

<template>
  <div
    v-if="mobileOpen"
    class="fixed inset-0 z-40 bg-slate-950/30 backdrop-blur-[1px] lg:hidden"
    aria-hidden="true"
    @click="emit('closeMobile')"
  />

  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-[280px] flex-col bg-white shadow-[7px_0_60px_rgb(0_0_0/5%)] transition-[width,transform] duration-300"
    :class="sidebarClasses"
  >
    <div class="flex h-[60px] shrink-0 items-center border-b border-slate-100 px-5">
      <div class="flex min-w-0 flex-1 items-center gap-3 overflow-hidden">
        <span
          class="grid size-9 shrink-0 place-items-center rounded-md bg-brand-500 text-white shadow-sm"
        >
          <Squares2X2Icon class="size-5" />
        </span>
        <div v-if="!collapsed" class="min-w-0 whitespace-nowrap">
          <div class="text-[15px] font-bold tracking-tight text-slate-800">GridPBX</div>
          <div class="text-[10px] font-semibold tracking-[0.12em] text-slate-400 uppercase">
            Phone system
          </div>
        </div>
      </div>

      <button
        type="button"
        class="ml-2 hidden size-8 place-items-center rounded-full text-slate-400 transition hover:bg-brand-50 hover:text-brand-500 lg:grid"
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="emit('collapse')"
      >
        <ChevronDoubleRightIcon v-if="collapsed" class="size-4" />
        <ChevronDoubleLeftIcon v-else class="size-4" />
      </button>

      <button
        type="button"
        class="grid size-8 place-items-center rounded-full text-slate-500 hover:bg-slate-100 lg:hidden"
        aria-label="Close navigation"
        @click="emit('closeMobile')"
      >
        <XMarkIcon class="size-5" />
      </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Primary navigation">
      <section v-for="section in sections" :key="section.label" class="mb-5">
        <h2
          v-if="!collapsed"
          class="mb-2 px-3 text-[10px] font-bold tracking-[0.09em] text-brand-500 uppercase"
        >
          {{ section.label }}
        </h2>
        <div v-else class="mx-2 mb-2 h-px bg-brand-100" />

        <RouterLink
          v-for="item in section.items"
          :key="item.to"
          :to="item.to"
          :title="collapsed ? item.label : undefined"
          class="group mb-1 flex h-10 items-center rounded-md text-[13px] font-medium transition"
          :class="[
            collapsed ? 'justify-center px-2' : 'px-3',
            isActive(item.to)
              ? 'bg-brand-50 text-brand-600'
              : 'text-slate-600 hover:bg-slate-50 hover:text-brand-500',
          ]"
          @click="emit('closeMobile')"
        >
          <component
            :is="item.icon"
            class="size-[19px] shrink-0 transition"
            :class="[
              !collapsed && 'mr-3',
              isActive(item.to) ? 'text-brand-500' : 'text-slate-400 group-hover:text-brand-500',
            ]"
          />
          <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
          <span
            v-if="item.badge && !collapsed"
            class="ml-auto rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold text-brand-600"
          >
            {{ item.badge }}
          </span>
        </RouterLink>
      </section>
    </nav>

    <div class="border-t border-slate-100 p-3">
      <div
        class="flex items-center rounded-md bg-slate-50"
        :class="collapsed ? 'justify-center p-2' : 'gap-3 p-3'"
      >
        <span
          class="grid size-8 shrink-0 place-items-center rounded-full bg-success/15 text-success"
        >
          <PhoneArrowUpRightIcon class="size-4" />
        </span>
        <div v-if="!collapsed" class="min-w-0">
          <p class="truncate text-xs font-semibold text-slate-700">Kazoo local</p>
          <p class="mt-0.5 flex items-center gap-1.5 text-[10px] text-slate-500">
            <span class="size-1.5 rounded-full bg-success" /> API target ready
          </p>
        </div>
      </div>
    </div>
  </aside>
</template>
