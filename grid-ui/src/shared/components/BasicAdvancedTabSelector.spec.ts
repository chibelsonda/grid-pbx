import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import BasicAdvancedFormTabs from './BasicAdvancedFormTabs.vue'
import BasicAdvancedTabSelector from './BasicAdvancedTabSelector.vue'

describe('BasicAdvancedTabSelector', () => {
  it('renders a theme-aligned segmented control and changes the selected section', async () => {
    const wrapper = mount(BasicAdvancedTabSelector, {
      props: { modelValue: 0 },
    })

    const tabList = wrapper.get('[role="tablist"]')
    const tabs = wrapper.findAll('[role="tab"]')

    expect(tabList.attributes('aria-label')).toBe('Form sections')
    expect(tabList.classes()).toContain('bg-slate-100')
    expect(tabList.classes()).toContain('rounded-lg')
    expect(tabList.classes()).toContain('w-fit')
    expect(tabList.classes()).toContain('p-0.5')
    expect(tabList.classes()).toContain('gap-0.5')
    expect(tabList.classes()).not.toContain('p-1')
    expect(tabs.map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.findAll('svg')).toHaveLength(0)
    expect(tabs[0]!.classes()).not.toContain('flex-1')
    expect(tabs[0]!.classes()).toContain('min-w-28')
    expect(tabs[0]!.classes()).toContain('h-8')
    expect(tabs[0]!.classes()).not.toContain('h-9')
    expect(tabs[0]!.classes()).toContain('bg-white')
    expect(tabs[0]!.classes()).toContain('text-brand-600')
    expect(tabs[0]!.attributes('aria-selected')).toBe('true')

    await tabs[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
    await wrapper.setProps({ modelValue: 1 })

    expect(tabs[0]!.attributes('aria-selected')).toBe('false')
    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(tabs[1]!.classes()).toContain('bg-white')
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

    expect(tabs[1]!.classes()).toContain('bg-white')
    await wrapper.setProps({ modelValue: 0 })

    expect(tabs[0]!.attributes('aria-selected')).toBe('true')
    expect(tabs[0]!.classes()).toContain('bg-white')
    expect(tabs[1]!.classes()).toContain('border-transparent')
  })
})
