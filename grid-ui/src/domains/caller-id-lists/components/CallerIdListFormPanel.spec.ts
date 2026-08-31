import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CallerIdListFormPanel from './CallerIdListFormPanel.vue'

function mountPanel(fieldErrors: Record<string, string[]> = {}) {
  return mount(CallerIdListFormPanel, {
    props: {
      record: null,
      saving: false,
      error: null,
      fieldErrors,
      canManage: true,
    },
    global: {
      stubs: {
        CrudSlideOver: { template: '<div><slot /></div>' },
      },
    },
  })
}

describe('CallerIdListFormPanel', () => {
  it('groups matching fields under Basic and optional list metadata under Advanced', async () => {
    const wrapper = mountPanel()
    const tabs = wrapper.findAll('[role="tab"]')
    const panels = wrapper.findAll('[role="tabpanel"]')

    expect(tabs.map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(panels[0]!.attributes('style') ?? '').not.toContain('display: none')
    expect(panels[1]!.attributes('style') ?? '').toContain('display: none')
    expect(panels[0]!.find('input[aria-label="Name"]').exists()).toBe(true)
    expect(panels[1]!.find('input[aria-label="Description"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No match entries yet. An empty list never matches a caller.')

    await tabs[1]!.trigger('click')

    expect(panels[0]!.attributes('style') ?? '').toContain('display: none')
    expect(panels[1]!.attributes('style') ?? '').not.toContain('display: none')
    expect(panels[1]!.find('input[aria-label="Organization"]').exists()).toBe(true)
  })

  it('returns to Basic when matching validation fails', async () => {
    const wrapper = mountPanel()

    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.findAll('[role="tab"]')[0]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.get('input[aria-label="Name"]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Enter a Caller-ID List name.')
  })

  it('opens Advanced for an advanced-only API error', async () => {
    const wrapper = mountPanel()

    await wrapper.setProps({ fieldErrors: { organization: ['Organization is invalid.'] } })

    expect(wrapper.findAll('[role="tab"]')[1]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.text()).toContain('Organization is invalid.')
  })
})
