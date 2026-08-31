<script setup lang="ts">
import type { SidebarItem } from '@/shared/navigation/sidebarNavigation'

withDefaults(
  defineProps<{
    item: SidebarItem
    active: boolean
    collapsed?: boolean
    child?: boolean
  }>(),
  { collapsed: false, child: false },
)

defineEmits<{ select: [] }>()
</script>

<template>
  <RouterLink
    :to="item.to"
    :title="collapsed ? item.label : undefined"
    :aria-label="collapsed ? item.label : undefined"
    class="mb-1 flex h-10 items-center rounded-md text-[13px] font-medium transition-colors"
    :class="[
      collapsed ? 'justify-center px-2' : child ? 'px-2.5' : 'px-3',
      active ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50',
    ]"
    @click="$emit('select')"
  >
    <component
      :is="item.icon"
      class="size-[19px] shrink-0"
      :class="!collapsed && (child ? 'mr-2.5 size-[17px]' : 'mr-3')"
    />
    <span v-if="!collapsed" class="min-w-0 truncate">{{ item.label }}</span>
  </RouterLink>
</template>
