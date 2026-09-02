import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormTabBar from './FormTabBar.vue'

describe('FormTabBar', () => {
  it('renders the shared horizontal form layout and updates the selected section', async () => {
    const wrapper = mount(FormTabBar, {
      props: {
        modelValue: 0,
        tabs: [
          { key: 'basic', label: 'Basic' },
          { key: 'options', label: 'Options' },
        ],
        ariaLabel: 'Test form sections',
      },
      attrs: { class: 'mb-5' },
    })

    const tabList = wrapper.get('[role="tablist"]')
    const tabs = wrapper.findAll('[role="tab"]')

    expect(tabList.attributes('aria-label')).toBe('Test form sections')
    expect(tabList.classes()).toContain('overflow-x-auto')
    expect(tabList.classes()).toContain('border-b')
    expect(tabList.classes()).toContain('bg-slate-50/70')
    expect(tabList.classes()).toContain('mb-5')
    expect(tabs.map((tab) => tab.text())).toEqual(['Basic', 'Options'])
    expect(tabs[0]!.attributes('aria-selected')).toBe('true')

    await tabs[1]!.trigger('click')
    await wrapper.setProps({ modelValue: 1 })

    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(tabs[1]!.classes()).toContain('border-brand-500')
    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])

    await wrapper.setProps({ modelValue: 0 })

    expect(tabs[0]!.attributes('aria-selected')).toBe('true')
    expect(tabs[0]!.classes()).toContain('border-brand-500')
    expect(tabs[1]!.classes()).toContain('border-transparent')
  })

  it('reuses the Device tab row inside an existing form surface', () => {
    const wrapper = mount(FormTabBar, {
      props: {
        tabs: [{ key: 'basic', label: 'Basic' }],
        embedded: true,
      },
      slots: { default: '<div data-testid="tab-content">Device fields</div>' },
    })

    const tabList = wrapper.get('[role="tablist"]')
    expect(tabList.classes()).toContain('border-b')
    expect(tabList.classes()).toContain('bg-slate-50/70')
    expect(tabList.classes()).not.toContain('card-surface')
    expect(wrapper.get('[data-testid="tab-content"]').text()).toBe('Device fields')
  })
})
