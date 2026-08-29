import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DeviceHotdeskPanel from './DeviceHotdeskPanel.vue'

describe('DeviceHotdeskPanel', () => {
  it('shows projected public users, unresolved counts, and emits sign out', async () => {
    const wrapper = mount(DeviceHotdeskPanel, {
      props: {
        candidates: [
          { id: '66216cb4-ae32-4096-87e9-c4644591aeb2', display_name: 'Alice', extension: '1001' },
          { id: '2ec6914e-91aa-4b09-bbe7-7bf81631ebf7', display_name: 'Bob', extension: '1002' },
        ],
        memberships: {
          users: [
            {
              id: '66216cb4-ae32-4096-87e9-c4644591aeb2',
              display_name: 'Alice',
              extension: '1001',
            },
          ],
          unresolved_count: 1,
        },
        loading: false,
        canManage: true,
      },
    })

    expect(wrapper.text()).toContain('Alice')
    expect(wrapper.text()).toContain('1 additional Switch user')
    expect(wrapper.html()).not.toContain('switch-user-id')

    await wrapper.get('[data-testid="hotdesk-sign-out"]').trigger('click')
    expect(wrapper.emitted('signOut')).toEqual([['66216cb4-ae32-4096-87e9-c4644591aeb2']])
  })
})
