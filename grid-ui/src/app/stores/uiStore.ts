import { defineStore } from 'pinia'
import { z } from 'zod'
import { findShellTheme, type ShellThemeRegion } from '@/app/theme/themeCatalog'
import type { AppNotificationInput, AppNotificationState } from '@/shared/types/appNotification'

const themeStorageKey = 'gridpbx.shell-theme.v1'
const sidebarStorageKey = 'gridpbx.sidebar-collapsed.v1'
const sidebarBrandDisplayStorageKey = 'gridpbx.sidebar-brand-display.v1'
const defaultThemeId = 'light'
let notificationSequence = 0

export const sidebarBrandDisplaySchema = z.enum(['logo-and-name', 'logo-only'])
export type SidebarBrandDisplay = z.infer<typeof sidebarBrandDisplaySchema>

type StoredThemePreferences = {
  header: string
  sidebar: string
}

function readThemePreferences(): StoredThemePreferences {
  if (typeof window === 'undefined') {
    return { header: defaultThemeId, sidebar: defaultThemeId }
  }

  try {
    const stored = JSON.parse(
      window.localStorage.getItem(themeStorageKey) ?? '{}',
    ) as Partial<StoredThemePreferences>

    return {
      header: findShellTheme('header', stored.header ?? defaultThemeId).id,
      sidebar: findShellTheme('sidebar', stored.sidebar ?? defaultThemeId).id,
    }
  } catch {
    return { header: defaultThemeId, sidebar: defaultThemeId }
  }
}

function readSidebarCollapsed(): boolean {
  return typeof window !== 'undefined' && window.localStorage.getItem(sidebarStorageKey) === 'true'
}

function readSidebarBrandDisplay(): SidebarBrandDisplay {
  if (typeof window === 'undefined') return 'logo-and-name'

  const result = sidebarBrandDisplaySchema.safeParse(
    window.localStorage.getItem(sidebarBrandDisplayStorageKey),
  )

  return result.success ? result.data : 'logo-and-name'
}

export const useUiStore = defineStore('ui', {
  state: () => {
    const storedThemes = readThemePreferences()

    return {
      sidebarCollapsed: readSidebarCollapsed(),
      sidebarBrandDisplay: readSidebarBrandDisplay(),
      mobileSidebarOpen: false,
      themePanelOpen: false,
      headerTheme: storedThemes.header,
      sidebarTheme: storedThemes.sidebar,
      notification: null as AppNotificationState | null,
    }
  },
  actions: {
    initializeTheme(): void {
      this.applyTheme('header')
      this.applyTheme('sidebar')
    },
    openThemePanel(): void {
      this.themePanelOpen = true
    },
    closeThemePanel(): void {
      this.themePanelOpen = false
    },
    setTheme(region: ShellThemeRegion, themeId: string): void {
      const theme = findShellTheme(region, themeId)

      if (region === 'header') this.headerTheme = theme.id
      else this.sidebarTheme = theme.id

      this.applyTheme(region)
      this.persistThemes()
    },
    resetTheme(region?: ShellThemeRegion): void {
      if (region === undefined || region === 'header') this.headerTheme = defaultThemeId
      if (region === undefined || region === 'sidebar') this.sidebarTheme = defaultThemeId

      if (region) this.applyTheme(region)
      else {
        this.applyTheme('header')
        this.applyTheme('sidebar')
      }

      this.persistThemes()
    },
    applyTheme(region: ShellThemeRegion): void {
      if (typeof document === 'undefined') return

      const theme = findShellTheme(
        region,
        region === 'header' ? this.headerTheme : this.sidebarTheme,
      )

      Object.entries(theme.tokens).forEach(([token, value]) => {
        const cssToken = token.replace(/[A-Z]/g, (character) => `-${character.toLowerCase()}`)
        document.documentElement.style.setProperty(`--app-${region}-${cssToken}`, value)
      })
    },
    persistThemes(): void {
      if (typeof window === 'undefined') return

      window.localStorage.setItem(
        themeStorageKey,
        JSON.stringify({ header: this.headerTheme, sidebar: this.sidebarTheme }),
      )
    },
    toggleSidebar(): void {
      this.setSidebarCollapsed(!this.sidebarCollapsed)
    },
    setSidebarCollapsed(collapsed: boolean): void {
      this.sidebarCollapsed = collapsed
      if (typeof window !== 'undefined') {
        window.localStorage.setItem(sidebarStorageKey, String(collapsed))
      }
    },
    setSidebarBrandDisplay(display: SidebarBrandDisplay): void {
      this.sidebarBrandDisplay = display
      if (typeof window !== 'undefined') {
        window.localStorage.setItem(sidebarBrandDisplayStorageKey, display)
      }
    },
    toggleMobileSidebar(): void {
      this.mobileSidebarOpen = !this.mobileSidebarOpen
    },
    closeMobileSidebar(): void {
      this.mobileSidebarOpen = false
    },
    notify(input: AppNotificationInput): void {
      this.notification = {
        id: ++notificationSequence,
        title: input.title,
        message: input.message,
        tone: input.tone ?? 'info',
      }
    },
    dismissNotification(id?: number): void {
      if (id !== undefined && this.notification?.id !== id) return

      this.notification = null
    },
  },
})
