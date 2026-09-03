import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CrudSlideOver from './CrudSlideOver.vue'
import SlideOver from './SlideOver.vue'

describe('CrudSlideOver', () => {
  it('supports an extra-wide desktop panel for dense forms', () => {
    const wrapper = mount(CrudSlideOver, {
      props: { title: 'Create extension', width: 'extra-wide' },
      global: {
        stubs: {
          Dialog: { template: '<div><slot /></div>' },
          DialogPanel: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<div><slot /></div>' },
          TransitionChild: { template: '<div><slot /></div>' },
          TransitionRoot: { template: '<div><slot /></div>' },
        },
      },
    })

    const panel = wrapper.get('[data-testid="slide-over-panel"]')
    const closeButton = wrapper.get('button[aria-label="Close panel"]')
    expect(wrapper.findComponent(SlideOver).exists()).toBe(true)
    expect(panel.attributes('data-width')).toBe('extra-wide')
    expect(panel.classes()).toContain('max-w-7xl')
    expect(closeButton.classes()).not.toContain('border')
    expect(closeButton.classes()).not.toContain('bg-white')
    expect(closeButton.classes()).not.toContain('shadow-sm')
    expect(closeButton.classes()).toContain('rounded-full')
    expect(closeButton.classes()).toContain('hover:bg-brand-50')
    expect(closeButton.get('svg').classes()).toContain('size-6')
    expect(closeButton.get('svg').classes()).toContain('group-hover:scale-110')
  })

  it('renders reusable form content inline when embedded in a workspace', () => {
    const wrapper = mount(CrudSlideOver, {
      props: { title: 'Create call route', embedded: true },
      slots: { default: '<p>Draft route form</p>' },
    })

    expect(wrapper.get('[data-testid="embedded-crud-panel"]').text()).toContain('Create call route')
    expect(wrapper.get('[data-testid="embedded-crud-content"]').text()).toContain(
      'Draft route form',
    )
    expect(wrapper.find('[data-testid="slide-over-panel"]').exists()).toBe(false)
    expect(wrapper.get('button[aria-label="Close panel"]').classes()).not.toContain('border')
    expect(wrapper.get('button[aria-label="Close panel"]').classes()).toContain('hover:bg-brand-50')
    expect(wrapper.get('button[aria-label="Close panel"] svg').classes()).toContain('size-6')
    expect(wrapper.get('button[aria-label="Close panel"] svg').classes()).toContain(
      'group-hover:scale-110',
    )
  })
})
