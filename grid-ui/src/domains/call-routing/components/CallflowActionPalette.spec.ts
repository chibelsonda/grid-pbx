import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowActionPalette from './CallflowActionPalette.vue'

describe('CallflowActionPalette', () => {
  it('searches schema-backed actions and identifies capability-gated modules', async () => {
    const wrapper = mount(CallflowActionPalette)

    await wrapper.get('input[type="search"]').setValue('webhook')

    expect(wrapper.text()).toContain('1 action')
    expect(wrapper.text()).toContain('Webhook')
    expect(wrapper.text()).toContain('webhook')
    expect(wrapper.text()).toContain('Capability required')
    expect(wrapper.text()).not.toContain('Voicemail')
    expect(wrapper.find('[role="button"][aria-label*="Webhook"]').exists()).toBe(false)
  })

  it('marks the modules supported by the current guided editor honestly', async () => {
    const wrapper = mount(CallflowActionPalette)

    await wrapper.get('input[type="search"]').setValue('temporal_route')

    expect(wrapper.text()).toContain('Temporal Route')
    expect(wrapper.text()).toContain('Guided now')
    expect(wrapper.text()).not.toContain('Capability required')
  })
})
