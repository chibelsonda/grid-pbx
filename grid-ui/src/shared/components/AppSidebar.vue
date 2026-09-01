<script setup lang="ts">
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import type { SidebarBrandDisplay } from '@/app/stores/uiStore'
import SidebarNavigation from '@/shared/components/SidebarNavigation.vue'

defineProps<{
  collapsed: boolean
  mobileOpen: boolean
  themeId: string
  organizationLogoUrl?: string | null
  organizationName?: string | null
  brandDisplay: SidebarBrandDisplay
}>()
const emit = defineEmits<{ collapse: []; closeMobile: [] }>()
</script>

<template>
  <aside
    class="app-sidebar fixed inset-y-0 left-0 z-40 hidden flex-col shadow-[7px_0_60px_rgb(0_0_0/5%)] transition-[width] duration-300 lg:flex"
    :class="collapsed ? 'w-20' : 'w-[280px]'"
    :data-theme="themeId"
  >
    <SidebarNavigation
      :collapsed="collapsed"
      :logo-url="organizationLogoUrl"
      :organization-name="organizationName"
      :brand-display="brandDisplay"
      @collapse="emit('collapse')"
    />
  </aside>

  <TransitionRoot :show="mobileOpen" as="template">
    <Dialog class="relative z-50 lg:hidden" @close="emit('closeMobile')">
      <TransitionChild
        as="template"
        enter="ease-out duration-200"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-150"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-950/30" />
      </TransitionChild>
      <div class="fixed inset-0 overflow-hidden">
        <TransitionChild
          as="template"
          enter="transform transition ease-out duration-300"
          enter-from="-translate-x-full"
          enter-to="translate-x-0"
          leave="transform transition ease-in duration-200"
          leave-from="translate-x-0"
          leave-to="-translate-x-full"
        >
          <DialogPanel
            class="app-sidebar fixed inset-y-0 left-0 flex w-[280px] flex-col shadow-2xl"
            :data-theme="themeId"
          >
            <SidebarNavigation
              :collapsed="false"
              :logo-url="organizationLogoUrl"
              :organization-name="organizationName"
              :brand-display="brandDisplay"
              mobile
              @close="emit('closeMobile')"
            />
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
