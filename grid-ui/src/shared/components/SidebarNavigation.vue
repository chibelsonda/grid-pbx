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
import type { SidebarBrandDisplay } from '@/app/stores/uiStore'
import SidebarNavLink from './SidebarNavLink.vue'
import {
  businessItems,
  cloudPhoneGroups,
  overviewItems,
  workspaceItems,
  type SidebarGroup,
  type SidebarItem,
} from '@/shared/navigation/sidebarNavigation'

const props = withDefaults(
  defineProps<{
    collapsed: boolean
    mobile?: boolean
    logoUrl?: string | null
    organizationName?: string | null
    brandDisplay?: SidebarBrandDisplay
  }>(),
  {
    mobile: false,
    logoUrl: null,
    organizationName: null,
    brandDisplay: 'logo-and-name',
  },
)
const emit = defineEmits<{ collapse: []; close: [] }>()
const route = useRoute()
const organizationName = computed(() => props.organizationName?.trim() || 'GridPBX')
const showBrandName = computed(() => !props.collapsed && props.brandDisplay === 'logo-and-name')
const showLargeBrandMark = computed(() => !props.collapsed && props.brandDisplay === 'logo-only')

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
  <div
    class="sidebar-border flex h-[60px] items-center border-b"
    :class="showLargeBrandMark ? 'px-3' : 'px-5'"
    data-sidebar-header
  >
    <img
      v-if="logoUrl"
      :src="logoUrl"
      alt="Organization logo"
      class="shrink-0 object-contain object-left"
      :class="showLargeBrandMark ? 'h-12 w-auto max-w-[190px]' : 'size-9 rounded-md'"
      :data-sidebar-brand-size="showLargeBrandMark ? 'large' : 'compact'"
      data-sidebar-brand-mark
    />
    <span
      v-else
      class="sidebar-accent-bg grid shrink-0 place-items-center rounded-md text-white"
      :class="showLargeBrandMark ? 'size-12' : 'size-9'"
      :data-sidebar-brand-size="showLargeBrandMark ? 'large' : 'compact'"
      data-sidebar-brand-mark
    >
      <Squares2X2Icon :class="showLargeBrandMark ? 'size-7' : 'size-5'" />
    </span>
    <div v-if="showBrandName" class="ml-3 min-w-0 flex-1" data-sidebar-brand-name>
      <div class="sidebar-foreground truncate text-[15px] font-bold">{{ organizationName }}</div>
      <div class="sidebar-muted text-[10px] font-semibold tracking-widest uppercase">
        Phone system
      </div>
    </div>
    <button
      v-if="!mobile"
      type="button"
      class="sidebar-action ml-auto hidden size-8 place-items-center rounded-md lg:grid"
      aria-label="Toggle navigation width"
      @click="emit('collapse')"
    >
      <ChevronDoubleRightIcon v-if="collapsed" class="size-4" />
      <ChevronDoubleLeftIcon v-else class="size-4" />
    </button>
    <button
      v-else
      type="button"
      class="sidebar-action ml-auto grid size-8 place-items-center rounded-md"
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
        class="sidebar-section-label mb-2 px-3 text-[10px] font-bold tracking-wider uppercase"
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
        class="sidebar-section-label mb-2 px-3 text-[10px] font-bold tracking-wider uppercase"
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
          class="sidebar-nav-item mb-1 flex h-10 w-full items-center justify-center rounded-md px-2 transition-colors"
          :class="groupActive(group) ? 'sidebar-nav-item-active' : 'sidebar-nav-item-idle'"
          @click="expandGroup(group)"
        >
          <component :is="group.icon" class="size-[19px]" />
        </button>
      </template>

      <div v-for="group in cloudPhoneGroups" v-else :key="group.id" class="mb-1">
        <button
          type="button"
          class="sidebar-nav-item flex h-10 w-full items-center rounded-md px-3 text-left text-[12px] font-semibold transition-colors"
          :class="groupActive(group) ? 'sidebar-nav-item-active' : 'sidebar-nav-item-idle'"
          :aria-expanded="openGroupId === group.id"
          :aria-controls="`sidebar-group-${group.id}`"
          @click="toggleGroup(group)"
        >
          <component :is="group.icon" class="mr-3 size-[18px] shrink-0" />
          <span class="min-w-0 flex-1 truncate">{{ group.label }}</span>
          <ChevronDownIcon
            class="sidebar-muted size-4 shrink-0 transition-transform duration-200"
            :class="openGroupId === group.id && 'rotate-180'"
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
          <div :id="`sidebar-group-${group.id}`" class="sidebar-border ml-5 border-l py-1 pl-2">
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
        class="sidebar-section-label mb-2 px-3 text-[10px] font-bold tracking-wider uppercase"
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
        class="sidebar-section-label mb-2 px-3 text-[10px] font-bold tracking-wider uppercase"
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

  <div class="sidebar-border sidebar-muted border-t p-4 text-center text-[10px] font-semibold">
    {{ collapsed ? 'PBX' : 'SWITCH PROJECTION CONSOLE' }}
  </div>
</template>
