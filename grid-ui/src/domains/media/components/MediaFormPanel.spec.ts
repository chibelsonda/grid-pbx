import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MediaFormPanel from './MediaFormPanel.vue'

describe('MediaFormPanel', () => {
  it('separates playback options and returns validation to Basic', async () => {
    const wrapper = mount(MediaFormPanel, {
      props: {
        mode: 'create',
        record: null,
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
        },
      },
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.find('[role="switch"]').isVisible()).toBe(false)

    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')
    expect(wrapper.findAll('[role="tab"]')[1]!.attributes('aria-selected')).toBe('true')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.findAll('[role="tab"]')[0]!.attributes('aria-selected')).toBe('true')
    expect(wrapper.get('input[aria-label="Media name"]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Enter a media name.')
  })
})
