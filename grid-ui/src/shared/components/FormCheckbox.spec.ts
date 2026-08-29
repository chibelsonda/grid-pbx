import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormCheckbox from './FormCheckbox.vue'

describe('FormCheckbox', () => {
  it('updates a boolean and displays field-local validation', async () => {
    const wrapper = mount(FormCheckbox, {
      props: { modelValue: false, label: 'Enable routing', error: 'Choose a value.' },
    })

    const input = wrapper.get('input')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-label')).toBe('Enable routing')
    expect(wrapper.text()).toContain('Choose a value.')
    await input.setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
  })

  it('adds and removes values in an array model', async () => {
    const wrapper = mount(FormCheckbox, {
      props: { modelValue: ['one'], value: 'two', label: 'Second choice' },
    })

    await wrapper.get('input').setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['one', 'two']])
  })

  it('supports a stable programmatic name independently of visible help text', () => {
    const wrapper = mount(FormCheckbox, {
      props: {
        modelValue: [],
        value: 'weekday',
        label: 'Weekdays',
        description: 'Weekly recurrence',
        ariaLabel: 'Use Temporal Rule Weekdays',
      },
    })

    expect(wrapper.get('input').attributes('aria-label')).toBe('Use Temporal Rule Weekdays')
  })
})
