import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import GroupFormPanel from './GroupFormPanel.vue'

describe('GroupFormPanel', () => {
  it('keeps client validation inline and marks the name control invalid', async () => {
    const wrapper = mount(GroupFormPanel, {
      props: {
        record: null,
        options: { users: [], devices: [], groups: [], media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
        },
      },
    })

    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a group name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })

  it('emits one remove event after delete confirmation', async () => {
    const wrapper = mount(GroupFormPanel, {
      props: {
        record: {
          id: '1df29d1f-0c2e-465d-a714-fb2edcccbf3f',
          name: 'Support',
          members: [],
          music_on_hold_media: null,
          sync_status: 'healthy',
          last_synced_at: null,
        },
        options: { users: [], devices: [], groups: [], media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
        },
      },
    })

    await wrapper.get('button.text-danger').trigger('click')

    expect(wrapper.emitted('remove')).toHaveLength(1)
  })
})
