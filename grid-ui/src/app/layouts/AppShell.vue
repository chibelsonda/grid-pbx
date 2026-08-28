<script setup lang="ts">
import { onMounted } from 'vue'
import AppHeader from '@/shared/components/AppHeader.vue'
import AppSidebar from '@/shared/components/AppSidebar.vue'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useUiStore } from '@/app/stores/uiStore'

const ui = useUiStore()
const accounts = useAccountStore()

onMounted(() => accounts.load())
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
      <AppHeader :sidebar-collapsed="ui.sidebarCollapsed" @toggle-mobile="ui.toggleMobileSidebar" />
      <main class="pt-[60px]">
        <RouterView />
      </main>
    </div>
  </div>
</template>
