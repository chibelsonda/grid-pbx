import { describe, expect, it, vi } from 'vitest'
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
    expect(wrapper.find('svg').exists()).toBe(true)
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

  it('emits guided actions only when the selected parent can accept a child', async () => {
    const wrapper = mount(CallflowActionPalette, { props: { enabled: true } })

    await wrapper.get('input[type="search"]').setValue('voicemail')
    await wrapper.get('[aria-label="Add Voicemail"]').trigger('click')

    expect(wrapper.emitted('choose')?.[0]?.[0]).toMatchObject({
      module: 'voicemail',
      status: 'guided',
    })
  })

  it('starts a guided palette drag even when a different node must be selected as the target', async () => {
    const setData = vi.fn()
    const wrapper = mount(CallflowActionPalette, { props: { dragEnabled: true } })

    await wrapper.get('input[type="search"]').setValue('tts')
    await wrapper
      .get('[aria-label="Drag Text to speech onto route"]')
      .trigger('dragstart', { dataTransfer: { setData, effectAllowed: 'none' } })

    expect(setData).toHaveBeenCalledWith('text/plain', 'tts')
    expect(wrapper.emitted('action-drag-start')?.[0]?.[0]).toMatchObject({ module: 'tts' })
  })

  it('exposes explicit move and dock controls for the floating palette', async () => {
    const wrapper = mount(CallflowActionPalette, {
      props: { compact: true, movable: true, floating: true },
    })

    await wrapper.get('[aria-label="Move action palette"]').trigger('pointerdown')
    await wrapper.get('[aria-label="Dock action palette"]').trigger('click')

    expect(wrapper.emitted('drag-start')).toHaveLength(1)
    expect(wrapper.emitted('dock')).toHaveLength(1)
  })
})
