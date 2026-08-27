<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
</script>

<template>
  <div class="min-h-screen bg-canvas">
    <AppSidebar
      :collapsed="ui.sidebarCollapsed"
      :mobile-open="ui.mobileSidebarOpen"
      @collapse="ui.toggleSidebar"
      @close-mobile="ui.closeMobileSidebar"
    />

    <div
      class="min-h-screen transition-[padding] duration-300"
      :class="ui.sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-[280px]'"
    >
      <AppHeader @toggle-mobile="ui.toggleMobileSidebar" />
      <main class="pt-[60px]">
        <RouterView />
      </main>
    </div>
  </div>
</template>
