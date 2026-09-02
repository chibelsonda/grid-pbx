<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Cog6ToothIcon } from '@heroicons/vue/24/outline'
import AppHeader from '@/shared/components/AppHeader.vue'
import AppNotification from '@/shared/components/AppNotification.vue'
import AppSidebar from '@/shared/components/AppSidebar.vue'
import ThemeCustomizerPanel from '@/shared/components/ThemeCustomizerPanel.vue'
import { useAccountStore } from '@/domains/accounts/stores/accountStore'
import { useUiStore } from '@/app/stores/uiStore'

const ui = useUiStore()
const accounts = useAccountStore()
const route = useRoute()
const pageMinimumHeight = ref<number | null>(null)
let notificationTimer: ReturnType<typeof window.setTimeout> | null = null

function clearNotificationTimer(): void {
  if (notificationTimer === null) return

  window.clearTimeout(notificationTimer)
  notificationTimer = null
}

ui.initializeTheme()
onMounted(() => accounts.load())
watch(
  () => accounts.selected?.id ?? null,
  async (accountId, previousAccountId) => {
    void accounts.loadOrganizationLogo()

    if (!accountId || !previousAccountId || accountId === previousAccountId) return

    ui.notify({
      title: 'Account changed',
      message: `Now viewing ${accounts.selected?.name ?? 'the selected account'}.`,
    })

    const scrollPosition = window.scrollY
    pageMinimumHeight.value = Math.max(
      pageMinimumHeight.value ?? 0,
      Math.ceil(scrollPosition + window.innerHeight + 1),
    )
    await nextTick()
    window.scrollTo({ top: scrollPosition })
  },
)
watch(
  () => ui.notification?.id,
  (notificationId) => {
    clearNotificationTimer()
    if (notificationId === undefined) return

    notificationTimer = window.setTimeout(() => {
      ui.dismissNotification(notificationId)
      notificationTimer = null
    }, 4_000)
  },
)
watch(
  () => route.fullPath,
  () => {
    pageMinimumHeight.value = null
  },
)
onBeforeUnmount(() => {
  clearNotificationTimer()
  accounts.releaseOrganizationLogo()
})
</script>

<template>
  <div class="app-workspace min-h-screen" :data-application-theme="ui.applicationTheme">
    <AppSidebar
      :collapsed="ui.sidebarCollapsed"
      :mobile-open="ui.mobileSidebarOpen"
      :theme-id="ui.sidebarTheme"
      :organization-logo-url="accounts.organizationLogoUrl"
      :organization-name="accounts.selected?.organization.name"
      :brand-display="ui.sidebarBrandDisplay"
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
      <main
        class="pt-[60px]"
        :style="pageMinimumHeight ? { minHeight: `${pageMinimumHeight}px` } : undefined"
      >
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
    <AppNotification
      :show="Boolean(ui.notification)"
      :title="ui.notification?.title ?? ''"
      :message="ui.notification?.message ?? ''"
      :tone="ui.notification?.tone"
      @dismiss="ui.dismissNotification()"
    />
  </div>
</template>
