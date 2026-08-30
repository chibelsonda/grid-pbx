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

    expect(wrapper.text()).toContain('Time of Day')
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

  it('disables guided actions when the catalog is read-only', async () => {
    const wrapper = mount(CallflowActionPalette)

    await wrapper.get('input[type="search"]').setValue('hangup')

    expect(
      wrapper.get('[aria-label="Hangup unavailable in read-only mode"]').attributes('disabled'),
    ).toBeDefined()
  })

  it('starts a guided palette drag even when a different node must be selected as the target', async () => {
    const setData = vi.fn()
    const wrapper = mount(CallflowActionPalette, { props: { dragEnabled: true } })

    await wrapper.get('input[type="search"]').setValue('tts')
    await wrapper
      .get('[aria-label="Drag TTS onto route"]')
      .trigger('dragstart', { dataTransfer: { setData, effectAllowed: 'none' } })

    expect(setData).toHaveBeenCalledWith('text/plain', 'tts')
    expect(wrapper.emitted('action-drag-start')?.[0]?.[0]).toMatchObject({ module: 'tts' })
  })

  it('puts the exact shared-module action identity in native drag data', async () => {
    const setData = vi.fn()
    const wrapper = mount(CallflowActionPalette, { props: { dragEnabled: true } })

    await wrapper.get('input[type="search"]').setValue('Stop Call Recording')
    await wrapper
      .get('[aria-label="Drag Stop Call Recording onto route"]')
      .trigger('dragstart', { dataTransfer: { setData, effectAllowed: 'none' } })

    expect(setData).toHaveBeenCalledWith(
      'application/x-gridpbx-callflow-action',
      'record_call[action=stop]',
    )
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

  it('uses the dark callflow surface throughout the compact editor palette', () => {
    const wrapper = mount(CallflowActionPalette, { props: { compact: true } })
    const palette = wrapper.get('[aria-label="Callflow action catalog"]')

    expect(palette.classes()).toContain('bg-callflow-node')
    expect(palette.classes()).toContain('border-slate-700')
    expect(wrapper.get('input[type="search"]').classes()).toContain('!bg-slate-800')
    expect(wrapper.get('h3').classes()).toContain('text-white')
    expect(wrapper.get('button[disabled] > div').classes()).toContain('border-white/10')
  })

  it('keeps only one category open and avoids a nested vertical scroll container', async () => {
    const wrapper = mount(CallflowActionPalette, { props: { compact: true } })
    let categoryButtons = wrapper.findAll('button[aria-expanded]')

    expect(
      categoryButtons.filter((button) => button.attributes('aria-expanded') === 'true'),
    ).toHaveLength(1)
    expect(wrapper.get('[data-callflow-palette-categories]').classes()).not.toContain(
      'overflow-y-auto',
    )

    await categoryButtons[1]!.trigger('click')
    categoryButtons = wrapper.findAll('button[aria-expanded]')

    expect(
      categoryButtons.filter((button) => button.attributes('aria-expanded') === 'true'),
    ).toHaveLength(1)
    expect(categoryButtons[0]!.attributes('aria-expanded')).toBe('false')
    expect(categoryButtons[1]!.attributes('aria-expanded')).toBe('true')
  })

  it('matches the installed Switch category order and legacy action labels', async () => {
    const wrapper = mount(CallflowActionPalette, { props: { compact: true } })
    const labels = wrapper
      .findAll('button[aria-expanded]')
      .map((button) => button.text().replace(/\d+$/, '').trim())

    expect(labels).toEqual([
      'Basic',
      'Advanced',
      'Time of Day',
      'Ring Group Toggle',
      'Hotdesking',
      'Do Not Disturb',
      'Caller-ID',
      'Call Recording',
      'Call Forwarding',
    ])

    expect(wrapper.text()).not.toContain('Schema extensions')

    const advanced = wrapper.findAll('button[aria-expanded]')[1]!
    expect(advanced.text()).toContain('23')
    await advanced.trigger('click')
    expect(wrapper.text()).toContain('Webhook')
    expect(wrapper.text()).not.toContain('Branch Bnumber')

    await wrapper.get('input[type="search"]').setValue('Start Call Recording')
    expect(wrapper.text()).toContain('Start Call Recording')
    expect(wrapper.text()).not.toContain('Record Call')
  })

  it('keeps supported current-schema compatibility actions search-only', async () => {
    const wrapper = mount(CallflowActionPalette, { props: { compact: true, enabled: true } })

    expect(wrapper.text()).not.toContain('Branch Bnumber')
    await wrapper.get('input[type="search"]').setValue('Branch Bnumber')

    expect(wrapper.text()).toContain('Branch Bnumber')
    expect(wrapper.find('[aria-label="Add Branch Bnumber"]').exists()).toBe(true)
  })
})
