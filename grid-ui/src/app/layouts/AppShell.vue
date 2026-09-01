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
const accountChangeMessage = ref('')
let accountChangeAlertTimer: ReturnType<typeof window.setTimeout> | null = null

function dismissAccountChangeAlert(): void {
  accountChangeMessage.value = ''
  if (accountChangeAlertTimer === null) return

  window.clearTimeout(accountChangeAlertTimer)
  accountChangeAlertTimer = null
}

function showAccountChangeAlert(accountName: string): void {
  dismissAccountChangeAlert()
  accountChangeMessage.value = `Now viewing ${accountName}.`
  accountChangeAlertTimer = window.setTimeout(dismissAccountChangeAlert, 4_000)
}

ui.initializeTheme()
onMounted(() => accounts.load())
watch(
  () => accounts.selected?.id ?? null,
  async (accountId, previousAccountId) => {
    void accounts.loadOrganizationLogo()

    if (!accountId || !previousAccountId || accountId === previousAccountId) return

    showAccountChangeAlert(accounts.selected?.name ?? 'the selected account')

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
  () => route.fullPath,
  () => {
    pageMinimumHeight.value = null
  },
)
onBeforeUnmount(() => {
  dismissAccountChangeAlert()
  accounts.releaseOrganizationLogo()
})
</script>

<template>
  <div class="min-h-screen bg-canvas">
    <AppSidebar
      :collapsed="ui.sidebarCollapsed"
      :mobile-open="ui.mobileSidebarOpen"
      :theme-id="ui.sidebarTheme"
      :organization-logo-url="accounts.organizationLogoUrl"
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
      :show="Boolean(accountChangeMessage)"
      title="Account changed"
      :message="accountChangeMessage"
      @dismiss="dismissAccountChangeAlert"
    />
  </div>
</template>
