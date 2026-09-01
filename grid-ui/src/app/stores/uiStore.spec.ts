import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useUiStore } from './uiStore'

describe('uiStore theme preferences', () => {
  beforeEach(() => {
    window.localStorage.clear()
    document.documentElement.removeAttribute('style')
    setActivePinia(createPinia())
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
})
