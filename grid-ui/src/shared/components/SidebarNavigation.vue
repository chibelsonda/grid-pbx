<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { TransitionRoot } from '@headlessui/vue'
import {
  ChevronDoubleLeftIcon,
  ChevronDoubleRightIcon,
  ChevronDownIcon,
  Squares2X2Icon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useRoute } from 'vue-router'
import SidebarNavLink from './SidebarNavLink.vue'
import {
  businessItems,
  cloudPhoneGroups,
  overviewItems,
  workspaceItems,
  type SidebarGroup,
  type SidebarItem,
} from '@/shared/navigation/sidebarNavigation'

const props = defineProps<{ collapsed: boolean; mobile?: boolean }>()
const emit = defineEmits<{ collapse: []; close: [] }>()
const route = useRoute()

const active = (to: string): boolean =>
  to === '/' ? route.path === '/' : route.path.startsWith(to)
const activeGroup = computed<SidebarGroup | null>(
  () => cloudPhoneGroups.find((group) => group.items.some((item) => active(item.to))) ?? null,
)
const openGroupId = ref<SidebarGroup['id'] | null>(activeGroup.value?.id ?? 'people-endpoints')

watch(
  () => route.path,
  () => {
    if (activeGroup.value) openGroupId.value = activeGroup.value.id
  },
)

function toggleGroup(group: SidebarGroup): void {
  openGroupId.value = openGroupId.value === group.id ? null : group.id
}

function expandGroup(group: SidebarGroup): void {
  openGroupId.value = group.id
  emit('collapse')
}

function selectItem(): void {
  if (props.mobile) emit('close')
}

function sectionLabel(label: string): string | undefined {
  return props.collapsed ? undefined : label
}

function groupActive(group: SidebarGroup): boolean {
  return group.items.some((item) => active(item.to))
}

function itemKey(item: SidebarItem): string {
  return item.to
}
</script>

<template>
  <div class="flex h-[60px] items-center border-b border-slate-100 px-5">
    <span class="grid size-9 shrink-0 place-items-center rounded-md bg-brand-500 text-white">
      <Squares2X2Icon class="size-5" />
    </span>
    <div v-if="!collapsed" class="ml-3 min-w-0 flex-1">
      <div class="text-[15px] font-bold text-slate-800">GridPBX</div>
      <div class="text-[10px] font-semibold tracking-widest text-slate-500 uppercase">
        Phone system
      </div>
    </div>
    <button
      v-if="!mobile"
      type="button"
      class="hidden size-8 place-items-center text-slate-500 lg:grid"
      aria-label="Toggle navigation width"
      @click="emit('collapse')"
    >
      <ChevronDoubleRightIcon v-if="collapsed" class="size-4" />
      <ChevronDoubleLeftIcon v-else class="size-4" />
    </button>
    <button
      v-else
      type="button"
      class="ml-auto grid size-8 place-items-center text-slate-500"
      aria-label="Close navigation"
      @click="emit('close')"
    >
      <XMarkIcon class="size-5" />
    </button>
  </div>

  <nav aria-label="Primary navigation" class="flex-1 overflow-y-auto px-3 py-4">
    <section class="mb-4" :aria-label="sectionLabel('Overview')">
      <h2
        v-if="!collapsed"
        class="mb-2 px-3 text-[10px] font-bold tracking-wider text-brand-500 uppercase"
      >
        Overview
      </h2>
      <SidebarNavLink
        v-for="item in overviewItems"
        :key="itemKey(item)"
        :item="item"
        :active="active(item.to)"
        :collapsed="collapsed"
        @select="selectItem"
      />
    </section>

    <section class="mb-4" :aria-label="sectionLabel('Cloud phone system')">
      <h2
        v-if="!collapsed"
        class="mb-2 px-3 text-[10px] font-bold tracking-wider text-brand-500 uppercase"
      >
        Cloud phone system
      </h2>

      <template v-if="collapsed">
        <button
          v-for="group in cloudPhoneGroups"
          :key="group.id"
          type="button"
          :title="group.label"
          :aria-label="`${group.label}. Expand navigation to view links.`"
          class="mb-1 flex h-10 w-full items-center justify-center rounded-md px-2 transition-colors"
          :class="
            groupActive(group) ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'
          "
          @click="expandGroup(group)"
        >
          <component :is="group.icon" class="size-[19px]" />
        </button>
      </template>

      <div v-for="group in cloudPhoneGroups" v-else :key="group.id" class="mb-1">
        <button
          type="button"
          class="flex h-10 w-full items-center rounded-md px-3 text-left text-[12px] font-semibold transition-colors"
          :class="
            groupActive(group)
              ? 'bg-brand-50/70 text-brand-700'
              : 'text-slate-700 hover:bg-slate-50'
          "
          :aria-expanded="openGroupId === group.id"
          :aria-controls="`sidebar-group-${group.id}`"
          @click="toggleGroup(group)"
        >
          <component :is="group.icon" class="mr-3 size-[18px] shrink-0" />
          <span class="min-w-0 flex-1 truncate">{{ group.label }}</span>
          <ChevronDownIcon
            class="size-4 shrink-0 text-slate-400 transition-transform duration-200"
            :class="openGroupId === group.id && 'rotate-180 text-brand-500'"
          />
        </button>
        <TransitionRoot
          :show="openGroupId === group.id"
          as="div"
          enter="transition duration-150 ease-out"
          enter-from="-translate-y-1 opacity-0"
          enter-to="translate-y-0 opacity-100"
          leave="transition duration-100 ease-in"
          leave-from="translate-y-0 opacity-100"
          leave-to="-translate-y-1 opacity-0"
        >
          <div :id="`sidebar-group-${group.id}`" class="ml-5 border-l border-slate-200 py-1 pl-2">
            <SidebarNavLink
              v-for="item in group.items"
              :key="itemKey(item)"
              :item="item"
              :active="active(item.to)"
              child
              @select="selectItem"
            />
          </div>
        </TransitionRoot>
      </div>
    </section>

    <section class="mb-4" :aria-label="sectionLabel('Business')">
      <h2
        v-if="!collapsed"
        class="mb-2 px-3 text-[10px] font-bold tracking-wider text-brand-500 uppercase"
      >
        Business
      </h2>
      <SidebarNavLink
        v-for="item in businessItems"
        :key="itemKey(item)"
        :item="item"
        :active="active(item.to)"
        :collapsed="collapsed"
        @select="selectItem"
      />
    </section>

    <section :aria-label="sectionLabel('Workspace')">
      <h2
        v-if="!collapsed"
        class="mb-2 px-3 text-[10px] font-bold tracking-wider text-brand-500 uppercase"
      >
        Workspace
      </h2>
      <SidebarNavLink
        v-for="item in workspaceItems"
        :key="itemKey(item)"
        :item="item"
        :active="active(item.to)"
        :collapsed="collapsed"
        @select="selectItem"
      />
    </section>
  </nav>

  <div class="border-t border-slate-100 p-4 text-center text-[10px] font-semibold text-slate-500">
    {{ collapsed ? 'PBX' : 'SWITCH PROJECTION CONSOLE' }}
  </div>
</template>
