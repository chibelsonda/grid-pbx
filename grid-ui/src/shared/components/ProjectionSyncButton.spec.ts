import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ProjectionSyncButton from './ProjectionSyncButton.vue'

describe('ProjectionSyncButton', () => {
  it('uses the shared idle and busy labels', async () => {
    const wrapper = mount(ProjectionSyncButton, { props: { synchronizing: false } })

    expect(wrapper.get('button').text()).toBe('Sync from Switch')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('sync')).toHaveLength(1)

    await wrapper.setProps({ synchronizing: true })
    expect(wrapper.get('button').text()).toBe('Synchronizing…')
    expect(wrapper.get('button').attributes('aria-busy')).toBe('true')
    expect(wrapper.get('button').attributes()).toHaveProperty('disabled')
  })
})
