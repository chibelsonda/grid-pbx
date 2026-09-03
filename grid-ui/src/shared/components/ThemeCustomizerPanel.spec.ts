import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { useUiStore } from '@/app/stores/uiStore'
import SlideOver from './SlideOver.vue'
import ThemeCustomizerPanel from './ThemeCustomizerPanel.vue'

describe('ThemeCustomizerPanel', () => {
  it('renders an unboxed close icon and closes the panel', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const ui = useUiStore()
    ui.openThemePanel()
    const wrapper = mount(ThemeCustomizerPanel, {
      global: {
        plugins: [pinia],
        stubs: {
          Dialog: { template: '<div><slot /></div>' },
          DialogPanel: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          Disclosure: { template: '<div><slot :open="false" /></div>' },
          DisclosureButton: { template: '<button><slot /></button>' },
          DisclosurePanel: { template: '<div><slot /></div>' },
          TransitionChild: { template: '<div><slot /></div>' },
          TransitionRoot: { template: '<div><slot /></div>' },
        },
      },
    })
    const closeButton = wrapper.get('button[aria-label="Close theme customizer"]')

    expect(wrapper.findComponent(SlideOver).exists()).toBe(true)
    expect(wrapper.get('[data-testid="slide-over-panel"]').attributes('data-width')).toBe('narrow')
    expect(closeButton.classes()).not.toContain('border')
    expect(closeButton.classes()).not.toContain('bg-white')
    expect(closeButton.classes()).not.toContain('shadow-sm')
    expect(closeButton.classes()).toContain('rounded-full')
    expect(closeButton.classes()).toContain('hover:bg-brand-50')
    expect(closeButton.get('svg').classes()).toContain('size-6')
    expect(closeButton.get('svg').classes()).toContain('group-hover:scale-110')

    await closeButton.trigger('click')

    expect(ui.themePanelOpen).toBe(false)
  })
})
