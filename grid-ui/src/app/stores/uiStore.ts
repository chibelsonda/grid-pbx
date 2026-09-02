import { defineStore } from 'pinia'
import { z } from 'zod'
import {
  findApplicationTheme,
  findShellTheme,
  type ShellThemeRegion,
} from '@/app/theme/themeCatalog'
import type { AppNotificationInput, AppNotificationState } from '@/shared/types/appNotification'

const themeStorageKey = 'gridpbx.shell-theme.v1'
const applicationThemeStorageKey = 'gridpbx.application-theme.v2'
const sidebarStorageKey = 'gridpbx.sidebar-collapsed.v1'
const sidebarBrandDisplayStorageKey = 'gridpbx.sidebar-brand-display.v1'
const defaultApplicationThemeId = 'cloud'
let notificationSequence = 0

export const sidebarBrandDisplaySchema = z.enum(['logo-and-name', 'logo-only'])
export type SidebarBrandDisplay = z.infer<typeof sidebarBrandDisplaySchema>

type StoredThemePreferences = {
  application: string
  header: string
  sidebar: string
  headerOverride: boolean
  sidebarOverride: boolean
}

function readThemePreferences(): StoredThemePreferences {
  if (typeof window === 'undefined') {
    const application = findApplicationTheme(defaultApplicationThemeId)

    return {
      application: application.id,
      header: application.headerTheme,
      sidebar: application.sidebarTheme,
      headerOverride: false,
      sidebarOverride: false,
    }
  }

  try {
    const applicationStored = JSON.parse(
      window.localStorage.getItem(applicationThemeStorageKey) ?? '{}',
    ) as Partial<StoredThemePreferences>

    if (typeof applicationStored.application === 'string') {
      const application = findApplicationTheme(applicationStored.application)
      const headerOverride = applicationStored.headerOverride === true
      const sidebarOverride = applicationStored.sidebarOverride === true

      return {
        application: application.id,
        header: findShellTheme(
          'header',
          headerOverride
            ? (applicationStored.header ?? application.headerTheme)
            : application.headerTheme,
        ).id,
        sidebar: findShellTheme(
          'sidebar',
          sidebarOverride
            ? (applicationStored.sidebar ?? application.sidebarTheme)
            : application.sidebarTheme,
        ).id,
        headerOverride,
        sidebarOverride,
      }
    }

    const legacyStored = JSON.parse(
      window.localStorage.getItem(themeStorageKey) ?? '{}',
    ) as Partial<Pick<StoredThemePreferences, 'header' | 'sidebar'>>
    const application = findApplicationTheme(defaultApplicationThemeId)
    const header = findShellTheme('header', legacyStored.header ?? application.headerTheme).id
    const sidebar = findShellTheme('sidebar', legacyStored.sidebar ?? application.sidebarTheme).id

    return {
      application: application.id,
      header,
      sidebar,
      headerOverride: header !== application.headerTheme,
      sidebarOverride: sidebar !== application.sidebarTheme,
    }
  } catch {
    const application = findApplicationTheme(defaultApplicationThemeId)

    return {
      application: application.id,
      header: application.headerTheme,
      sidebar: application.sidebarTheme,
      headerOverride: false,
      sidebarOverride: false,
    }
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
      applicationTheme: storedThemes.application,
      headerTheme: storedThemes.header,
      sidebarTheme: storedThemes.sidebar,
      headerThemeOverride: storedThemes.headerOverride,
      sidebarThemeOverride: storedThemes.sidebarOverride,
      notification: null as AppNotificationState | null,
    }
  },
  actions: {
    initializeTheme(): void {
      this.applyApplicationTheme()
      this.applyTheme('header')
      this.applyTheme('sidebar')
    },
    openThemePanel(): void {
      this.themePanelOpen = true
    },
    closeThemePanel(): void {
      this.themePanelOpen = false
    },
    setApplicationTheme(themeId: string): void {
      const theme = findApplicationTheme(themeId)

      this.applicationTheme = theme.id
      this.headerTheme = theme.headerTheme
      this.sidebarTheme = theme.sidebarTheme
      this.headerThemeOverride = false
      this.sidebarThemeOverride = false
      this.applyApplicationTheme()
      this.applyTheme('header')
      this.applyTheme('sidebar')
      this.persistThemes()
    },
    setTheme(region: ShellThemeRegion, themeId: string): void {
      const theme = findShellTheme(region, themeId)

      if (region === 'header') {
        this.headerTheme = theme.id
        this.headerThemeOverride = true
      } else {
        this.sidebarTheme = theme.id
        this.sidebarThemeOverride = true
      }

      this.applyTheme(region)
      this.persistThemes()
    },
    resetTheme(region?: ShellThemeRegion): void {
      if (region === undefined) {
        this.setApplicationTheme(defaultApplicationThemeId)

        return
      }

      const application = findApplicationTheme(this.applicationTheme)

      if (region === 'header') {
        this.headerTheme = application.headerTheme
        this.headerThemeOverride = false
      } else {
        this.sidebarTheme = application.sidebarTheme
        this.sidebarThemeOverride = false
      }

      this.applyTheme(region)
      this.persistThemes()
    },
    applyApplicationTheme(): void {
      if (typeof document === 'undefined') return

      const theme = findApplicationTheme(this.applicationTheme)
      const root = document.documentElement
      const tokenMap: Record<string, string> = {
        '--app-canvas': theme.tokens.canvas,
        '--app-surface': theme.tokens.surface,
        '--app-surface-muted': theme.tokens.surfaceMuted,
        '--app-border': theme.tokens.border,
        '--app-foreground': theme.tokens.foreground,
        '--app-muted': theme.tokens.muted,
        '--app-focus': theme.tokens.focus,
        '--color-canvas': theme.tokens.canvas,
        '--color-ink': theme.tokens.foreground,
        '--color-muted': theme.tokens.muted,
        '--color-brand-50': theme.tokens.accent50,
        '--color-brand-100': theme.tokens.accent100,
        '--color-brand-500': theme.tokens.accent500,
        '--color-brand-600': theme.tokens.accent600,
        '--color-brand-700': theme.tokens.accent700,
      }

      Object.entries(tokenMap).forEach(([token, value]) => root.style.setProperty(token, value))
      root.dataset.applicationTheme = theme.id
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
      window.localStorage.setItem(
        applicationThemeStorageKey,
        JSON.stringify({
          application: this.applicationTheme,
          header: this.headerTheme,
          sidebar: this.sidebarTheme,
          headerOverride: this.headerThemeOverride,
          sidebarOverride: this.sidebarThemeOverride,
        }),
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
