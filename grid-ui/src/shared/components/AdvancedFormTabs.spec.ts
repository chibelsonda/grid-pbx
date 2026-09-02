import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AdvancedFormTabs from './AdvancedFormTabs.vue'

const tabs = [
  { key: 'options', label: 'Options' },
  { key: 'recording', label: 'Recording' },
]

describe('AdvancedFormTabs', () => {
  it('keeps the tab row and active form content inside one shared surface', async () => {
    const wrapper = mount(AdvancedFormTabs, {
      props: {
        modelValue: 0,
        tabs,
        ariaLabel: 'Test advanced sections',
      },
      slots: {
        default: '<section data-testid="advanced-fields">Advanced fields</section>',
      },
    })

    const surface = wrapper.get('article')
    const tabList = surface.get('[aria-label="Test advanced sections"]')

    expect(surface.classes()).toContain('card-surface')
    expect(tabList.classes()).not.toContain('card-surface')
    expect(surface.get('[data-testid="advanced-fields"]').text()).toBe('Advanced fields')

    await tabList.findAll('[role="tab"]')[1]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
  })

  it('preserves basic content without showing the advanced surface or tab row', () => {
    const wrapper = mount(AdvancedFormTabs, {
      props: {
        tabs,
        active: false,
      },
      slots: {
        default: '<section data-testid="basic-fields">Basic fields</section>',
      },
    })

    expect(wrapper.get('article').classes()).toContain('contents')
    expect(wrapper.get('[role="tablist"]').attributes('style')).toContain('display: none')
    expect(wrapper.get('[data-testid="basic-fields"]').text()).toBe('Basic fields')
  })
})
