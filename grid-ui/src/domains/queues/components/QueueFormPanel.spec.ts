import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import QueueFormPanel from './QueueFormPanel.vue'

describe('QueueFormPanel', () => {
  it('keeps client validation inline and marks invalid controls', async () => {
    const wrapper = mount(QueueFormPanel, {
      props: {
        record: null,
        options: {
          agents: [],
          media: [],
          capabilities: {
            configuration_available: true,
            live_agent_controls_available: false,
            agent_statistics_available: false,
            statistics_available: false,
          },
        },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        components: { ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.find('input[aria-label="Agent ring timeout"]').isVisible()).toBe(false)
    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    expect(wrapper.findAll('[role="tab"]')[1]!.attributes('aria-selected')).toBe('true')

    await wrapper.get('input[type="number"]').setValue(0)
    await wrapper.get('form').trigger('submit')

    expect(wrapper.findAll('[role="tab"]')[0]!.attributes('aria-selected')).toBe('true')
    const name = wrapper.get('input[aria-label="Name"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a queue name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })

  it('opens Advanced when an API error belongs to an Advanced field', () => {
    const wrapper = mount(QueueFormPanel, {
      props: {
        record: null,
        options: {
          agents: [],
          media: [],
          capabilities: {
            configuration_available: true,
            live_agent_controls_available: false,
            agent_statistics_available: false,
            statistics_available: false,
          },
        },
        saving: false,
        error: null,
        fieldErrors: {
          agent_ring_timeout: ['Use a ring timeout between 1 and 300 seconds.'],
        },
        canManage: true,
      },
      global: {
        components: { ToggleSwitch },
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    const tabs = wrapper.find('[aria-label="Form sections"]').findAll('[role="tab"]')
    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.get('input[aria-label="Agent ring timeout"]').attributes('aria-invalid')).toBe(
      'true',
    )
  })
})
