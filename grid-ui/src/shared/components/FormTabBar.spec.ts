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
    expect(tabList.classes()).toContain('mb-5')
    expect(tabs.map((tab) => tab.text())).toEqual(['Basic', 'Options'])
    expect(tabs[0]!.attributes('aria-selected')).toBe('true')

    await tabs[1]!.trigger('click')
    await wrapper.setProps({ modelValue: 1 })

    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
  })
})
