<script setup lang="ts">
import type { Component } from 'vue'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import {
  ArrowPathRoundedSquareIcon,
  Bars3BottomLeftIcon,
  BuildingOffice2Icon,
  ChartBarSquareIcon,
  ChevronDoubleLeftIcon,
  ChevronDoubleRightIcon,
  ClockIcon,
  Cog6ToothIcon,
  DevicePhoneMobileIcon,
  HashtagIcon,
  MicrophoneIcon,
  MusicalNoteIcon,
  QueueListIcon,
  BookOpenIcon,
  Squares2X2Icon,
  UserGroupIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps<{ collapsed: boolean; mobileOpen: boolean }>()
const emit = defineEmits<{ collapse: []; closeMobile: [] }>()
const route = useRoute()

type Item = { label: string; to: string; icon: Component }
const sections: { label: string; items: Item[] }[] = [
  { label: 'Overview', items: [{ label: 'Dashboard', to: '/', icon: ChartBarSquareIcon }] },
  {
    label: 'Cloud phone system',
    items: [
      { label: 'People & Extensions', to: '/extensions', icon: UserGroupIcon },
      { label: 'Devices', to: '/devices', icon: DevicePhoneMobileIcon },
      { label: 'Phone Numbers', to: '/phone-numbers', icon: HashtagIcon },
      { label: 'Call Routing', to: '/call-routing', icon: ArrowPathRoundedSquareIcon },
      { label: 'Directories', to: '/directories', icon: BookOpenIcon },
      { label: 'Groups & Ring Groups', to: '/groups', icon: UserGroupIcon },
      { label: 'Queues & Agents', to: '/queues', icon: QueueListIcon },
      { label: 'Menus & IVR', to: '/menus', icon: Bars3BottomLeftIcon },
      { label: 'Voicemail', to: '/voicemail', icon: MicrophoneIcon },
      { label: 'Media & Music on Hold', to: '/media', icon: MusicalNoteIcon },
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
const active = (to: string) => (to === '/' ? route.path === '/' : route.path.startsWith(to))
</script>

<template>
  <div
    v-if="mobileOpen"
    class="fixed inset-0 z-40 bg-slate-950/30 lg:hidden"
    @click="emit('closeMobile')"
  />
  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-[280px] flex-col bg-white shadow-[7px_0_60px_rgb(0_0_0/5%)] transition-[width,transform] duration-300"
    :class="sidebarClasses"
  >
    <div class="flex h-[60px] items-center border-b border-slate-100 px-5">
      <span class="grid size-9 shrink-0 place-items-center rounded-md bg-brand-500 text-white"
        ><Squares2X2Icon class="size-5"
      /></span>
      <div v-if="!collapsed" class="ml-3 min-w-0 flex-1">
        <div class="text-[15px] font-bold text-slate-800">GridPBX</div>
        <div class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">
          Phone system
        </div>
      </div>
      <button
        type="button"
        class="hidden size-8 place-items-center text-slate-400 lg:grid"
        @click="emit('collapse')"
      >
        <ChevronDoubleRightIcon v-if="collapsed" class="size-4" /><ChevronDoubleLeftIcon
          v-else
          class="size-4"
        />
      </button>
      <button type="button" class="ml-auto lg:hidden" @click="emit('closeMobile')">
        <XMarkIcon class="size-5" />
      </button>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-5">
      <section v-for="section in sections" :key="section.label" class="mb-5">
        <h2
          v-if="!collapsed"
          class="mb-2 px-3 text-[10px] font-bold tracking-wider text-brand-500 uppercase"
        >
          {{ section.label }}
        </h2>
        <RouterLink
          v-for="item in section.items"
          :key="item.to"
          :to="item.to"
          class="mb-1 flex h-10 items-center rounded-md text-[13px] font-medium"
          :class="[
            collapsed ? 'justify-center px-2' : 'px-3',
            active(item.to) ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50',
          ]"
          @click="emit('closeMobile')"
        >
          <component :is="item.icon" class="size-[19px] shrink-0" :class="!collapsed && 'mr-3'" />
          <span v-if="!collapsed">{{ item.label }}</span>
        </RouterLink>
      </section>
    </nav>
    <div class="border-t border-slate-100 p-4 text-center text-[10px] font-semibold text-slate-400">
      {{ collapsed ? 'PBX' : 'SWITCH PROJECTION CONSOLE' }}
    </div>
  </aside>
</template>
