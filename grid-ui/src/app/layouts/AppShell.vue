<script setup lang="ts">
import { onMounted } from 'vue'
import { Cog6ToothIcon } from '@heroicons/vue/24/outline'
import AppHeader from '@/shared/components/AppHeader.vue'
import AppSidebar from '@/shared/components/AppSidebar.vue'
import ThemeCustomizerPanel from '@/shared/components/ThemeCustomizerPanel.vue'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useUiStore } from '@/app/stores/uiStore'

const ui = useUiStore()
const accounts = useAccountStore()

ui.initializeTheme()
onMounted(() => accounts.load())
</script>

<template>
  <div class="min-h-screen bg-canvas">
    <AppSidebar
      :collapsed="ui.sidebarCollapsed"
      :mobile-open="ui.mobileSidebarOpen"
      :theme-id="ui.sidebarTheme"
      @collapse="ui.toggleSidebar"
      @close-mobile="ui.closeMobileSidebar"
    />

    <div
      class="min-h-screen transition-[padding] duration-300"
      :class="ui.sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-[280px]'"
    >
      <AppHeader
        :sidebar-collapsed="ui.sidebarCollapsed"
        :theme-id="ui.headerTheme"
        @toggle-mobile="ui.toggleMobileSidebar"
      />
      <main class="pt-[60px]">
        <RouterView />
      </main>
    </div>

    <button
      v-if="!ui.themePanelOpen"
      type="button"
      class="fixed right-0 bottom-8 z-40 grid size-12 place-items-center rounded-l-xl bg-brand-500 text-white shadow-xl transition hover:w-14 hover:bg-brand-600 focus-visible:w-14"
      aria-label="Customize theme"
      title="Customize theme"
      @click="ui.openThemePanel"
    >
      <Cog6ToothIcon class="size-6 transition-transform duration-300 hover:rotate-45" />
    </button>
    <ThemeCustomizerPanel />
  </div>
</template>
