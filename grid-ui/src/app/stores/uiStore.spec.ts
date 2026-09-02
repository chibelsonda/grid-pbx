import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useUiStore } from './uiStore'

describe('uiStore theme preferences', () => {
  beforeEach(() => {
    window.localStorage.clear()
    document.documentElement.removeAttribute('style')
    delete document.documentElement.dataset.applicationTheme
    setActivePinia(createPinia())
  })

  it('applies a coordinated application theme across shell and workspace tokens', () => {
    const ui = useUiStore()

    ui.setApplicationTheme('ocean')

    expect(ui.applicationTheme).toBe('ocean')
    expect(ui.headerTheme).toBe('ocean')
    expect(ui.sidebarTheme).toBe('navy')
    expect(ui.headerThemeOverride).toBe(false)
    expect(ui.sidebarThemeOverride).toBe(false)
    expect(document.documentElement.dataset.applicationTheme).toBe('ocean')
    expect(document.documentElement.style.getPropertyValue('--app-canvas')).toBe('#edf7fa')
    expect(document.documentElement.style.getPropertyValue('--color-brand-500')).toBe('#0891b2')
    expect(JSON.parse(window.localStorage.getItem('gridpbx.application-theme.v2') ?? '{}')).toEqual(
      {
        application: 'ocean',
        header: 'ocean',
        sidebar: 'navy',
        headerOverride: false,
        sidebarOverride: false,
      },
    )
  })

  it('applies and persists validated header and sidebar themes', () => {
    const ui = useUiStore()

    ui.setTheme('header', 'midnight')
    ui.setTheme('sidebar', 'emerald')

    expect(ui.headerTheme).toBe('midnight')
    expect(ui.sidebarTheme).toBe('emerald')
    expect(document.documentElement.style.getPropertyValue('--app-header-background')).toBe(
      '#17213a',
    )
    expect(document.documentElement.style.getPropertyValue('--app-sidebar-background')).toBe(
      '#17795b',
    )
    expect(JSON.parse(window.localStorage.getItem('gridpbx.shell-theme.v1') ?? '{}')).toEqual({
      header: 'midnight',
      sidebar: 'emerald',
    })
  })

  it('restores persisted choices and safely falls back from unknown theme ids', () => {
    window.localStorage.setItem(
      'gridpbx.shell-theme.v1',
      JSON.stringify({ header: 'cobalt', sidebar: 'not-a-theme' }),
    )
    setActivePinia(createPinia())

    const ui = useUiStore()
    ui.initializeTheme()

    expect(ui.headerTheme).toBe('cobalt')
    expect(ui.sidebarTheme).toBe('light')
    expect(document.documentElement.style.getPropertyValue('--app-header-background')).toBe(
      '#3157b7',
    )
    expect(document.documentElement.style.getPropertyValue('--app-sidebar-background')).toBe(
      '#ffffff',
    )
  })

  it('can reset one region or both regions to the default', () => {
    const ui = useUiStore()
    ui.setTheme('header', 'rose')
    ui.setTheme('sidebar', 'graphite')

    ui.resetTheme('header')
    expect(ui.headerTheme).toBe('light')
    expect(ui.sidebarTheme).toBe('graphite')

    ui.resetTheme()
    expect(ui.headerTheme).toBe('light')
    expect(ui.sidebarTheme).toBe('light')
  })

  it('tracks advanced overrides and restores the selected application preset', () => {
    const ui = useUiStore()
    ui.setApplicationTheme('teal')
    ui.setTheme('header', 'rose')

    expect(ui.headerTheme).toBe('rose')
    expect(ui.headerThemeOverride).toBe(true)

    ui.resetTheme('header')

    expect(ui.applicationTheme).toBe('teal')
    expect(ui.headerTheme).toBe('teal')
    expect(ui.headerThemeOverride).toBe(false)
  })

  it('migrates legacy independent shell preferences as advanced overrides', () => {
    window.localStorage.setItem(
      'gridpbx.shell-theme.v1',
      JSON.stringify({ header: 'cobalt', sidebar: 'emerald' }),
    )
    setActivePinia(createPinia())

    const ui = useUiStore()

    expect(ui.applicationTheme).toBe('cloud')
    expect(ui.headerTheme).toBe('cobalt')
    expect(ui.sidebarTheme).toBe('emerald')
    expect(ui.headerThemeOverride).toBe(true)
    expect(ui.sidebarThemeOverride).toBe(true)
  })

  it('supports the expanded solid, soft, and gradient theme catalog', () => {
    const ui = useUiStore()

    ui.setTheme('header', 'coral')
    ui.setTheme('sidebar', 'aurora')

    expect(document.documentElement.style.getPropertyValue('--app-header-background')).toBe(
      '#b94f45',
    )
    expect(document.documentElement.style.getPropertyValue('--app-sidebar-background')).toBe(
      'linear-gradient(135deg, #0f766e 0%, #2563eb 100%)',
    )
    expect(ui.headerTheme).toBe('coral')
    expect(ui.sidebarTheme).toBe('aurora')
  })

  it('persists the compact desktop sidebar preference', () => {
    const ui = useUiStore()

    ui.setSidebarCollapsed(true)
    expect(ui.sidebarCollapsed).toBe(true)
    expect(window.localStorage.getItem('gridpbx.sidebar-collapsed.v1')).toBe('true')

    setActivePinia(createPinia())
    expect(useUiStore().sidebarCollapsed).toBe(true)

    useUiStore().toggleSidebar()
    expect(useUiStore().sidebarCollapsed).toBe(false)
    expect(window.localStorage.getItem('gridpbx.sidebar-collapsed.v1')).toBe('false')
  })

  it('persists a validated sidebar branding display preference', () => {
    const ui = useUiStore()

    ui.setSidebarBrandDisplay('logo-only')

    expect(ui.sidebarBrandDisplay).toBe('logo-only')
    expect(window.localStorage.getItem('gridpbx.sidebar-brand-display.v1')).toBe('logo-only')

    setActivePinia(createPinia())
    expect(useUiStore().sidebarBrandDisplay).toBe('logo-only')

    window.localStorage.setItem('gridpbx.sidebar-brand-display.v1', 'untrusted-value')
    setActivePinia(createPinia())
    expect(useUiStore().sidebarBrandDisplay).toBe('logo-and-name')
  })

  it('replaces global notifications and ignores stale dismiss timers', () => {
    const ui = useUiStore()

    ui.notify({ title: 'Saved', message: 'The record was saved.', tone: 'success' })
    const firstId = ui.notification?.id
    ui.notify({ title: 'Failed', message: 'The update failed.', tone: 'error' })

    expect(ui.notification).toMatchObject({
      title: 'Failed',
      message: 'The update failed.',
      tone: 'error',
    })
    ui.dismissNotification(firstId)
    expect(ui.notification?.title).toBe('Failed')

    ui.dismissNotification(ui.notification?.id)
    expect(ui.notification).toBeNull()
  })
})
