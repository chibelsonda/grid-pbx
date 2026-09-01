import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import BasicAdvancedFormTabs from './BasicAdvancedFormTabs.vue'
import BasicAdvancedTabSelector from './BasicAdvancedTabSelector.vue'

describe('BasicAdvancedTabSelector', () => {
  it('reuses the horizontal Device-style tab bar and changes the selected section', async () => {
    const wrapper = mount(BasicAdvancedTabSelector, {
      props: { modelValue: 0 },
    })

    const tabList = wrapper.get('[role="tablist"]')
    const tabs = wrapper.findAll('[role="tab"]')

    expect(tabList.attributes('aria-label')).toBe('Form sections')
    expect(tabList.classes()).toContain('overflow-x-auto')
    expect(tabs.map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(tabs[0]!.classes()).toContain('border-brand-500')
    expect(tabs[0]!.attributes('aria-selected')).toBe('true')

    await tabs[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
    await wrapper.setProps({ modelValue: 1 })

    expect(tabs[0]!.attributes('aria-selected')).toBe('false')
    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(tabs[1]!.classes()).toContain('border-brand-500')
  })

  it('forwards sticky positioning and a custom accessible name', () => {
    const wrapper = mount(BasicAdvancedTabSelector, {
      props: { sticky: true, ariaLabel: 'Device form sections' },
    })

    const tabList = wrapper.get('[role="tablist"]')
    expect(tabList.attributes('aria-label')).toBe('Device form sections')
    expect(tabList.classes()).toContain('sticky')
  })
})

describe('BasicAdvancedFormTabs', () => {
  it('keeps the visual selection aligned after a programmatic return to Basic', async () => {
    const wrapper = mount(BasicAdvancedFormTabs, {
      props: { modelValue: 1 },
      slots: { basic: 'Basic fields', advanced: 'Advanced fields' },
    })
    const tabs = wrapper.findAll('[role="tab"]')

    expect(tabs[1]!.classes()).toContain('border-brand-500')
    await wrapper.setProps({ modelValue: 0 })

    expect(tabs[0]!.attributes('aria-selected')).toBe('true')
    expect(tabs[0]!.classes()).toContain('border-brand-500')
    expect(tabs[1]!.classes()).toContain('border-transparent')
  })
})
