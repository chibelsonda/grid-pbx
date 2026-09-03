import { DOMWrapper, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormSelect from './FormSelect.vue'

describe('FormSelect', () => {
  it('renders slot options through a Headless UI listbox and updates the model', async () => {
    const wrapper = mount(FormSelect, {
      props: { modelValue: 'weekly' },
      slots: {
        default: '<option value="daily">Daily</option><option value="weekly">Weekly</option>',
      },
      attachTo: document.body,
    })

    expect(wrapper.get('button').text()).toContain('Weekly')
    await wrapper.get('button').trigger('click')
    const options = Array.from(document.body.querySelectorAll<HTMLElement>('[role="option"]')).map(
      (option) => new DOMWrapper(option),
    )
    expect(options.map((option) => option.text())).toEqual(['Daily', 'Weekly'])
    await options[0]?.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['daily'])
    wrapper.unmount()
  })

  it('teleports and viewport-positions options so overflow containers cannot clip them', async () => {
    const wrapper = mount(FormSelect, {
      props: { modelValue: '' },
      slots: {
        default:
          '<option value="">Select a brand</option><option value="cisco">Cisco</option><option value="grandstream">Grandstream</option>',
      },
      attachTo: document.body,
    })

    await wrapper.get('button').trigger('click')
    const menu = document.body.querySelector<HTMLElement>('[role="listbox"]')

    expect(menu).not.toBeNull()
    expect(wrapper.element.contains(menu)).toBe(false)
    expect(menu?.classList.contains('fixed')).toBe(true)
    expect(menu?.style.visibility).toBe('visible')
    expect(menu?.style.maxHeight).toMatch(/px$/)
    wrapper.unmount()
  })

  it('marks the interactive control invalid and gives it the shared error border', () => {
    const wrapper = mount(FormSelect, {
      props: { modelValue: '' },
      attrs: { 'aria-invalid': 'true' },
      slots: { default: '<option value="">Select one</option>' },
    })

    const button = wrapper.get('button')

    expect(button.attributes('aria-invalid')).toBe('true')
    expect(button.classes()).toContain('!border-red-400')
  })

  it('keeps the hover border consistent with text inputs', () => {
    const wrapper = mount(FormSelect, {
      props: { modelValue: '' },
      slots: { default: '<option value="">Select one</option>' },
    })

    expect(wrapper.get('button').classes()).toContain('hover:border-slate-300')
    expect(wrapper.get('button').classes()).not.toContain('hover:border-slate-400')
  })
})
