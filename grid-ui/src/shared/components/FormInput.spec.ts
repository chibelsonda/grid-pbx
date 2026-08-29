import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormInput from './FormInput.vue'

describe('FormInput', () => {
  it('associates its label, description, and error with the native input', async () => {
    const wrapper = mount(FormInput, {
      props: {
        modelValue: '',
        label: 'Route name',
        description: 'Shown throughout GridPBX.',
        error: 'Enter a route name.',
      },
    })
    const input = wrapper.get('input')
    const label = wrapper.get('label')

    expect(label.attributes('for')).toBe(input.attributes('id'))
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toContain(`${input.attributes('id')}-description`)
    expect(input.attributes('aria-describedby')).toContain(`${input.attributes('id')}-error`)
    expect(input.classes()).toContain('!border-red-400')

    await input.setValue('Reception')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Reception'])
  })

  it('supports the component v-model number modifier', async () => {
    const wrapper = mount(FormInput, {
      props: {
        modelValue: 10,
        label: 'Timeout',
        modelModifiers: { number: true },
      },
      attrs: { type: 'number' },
    })

    await wrapper.get('input').setValue('25')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([25])
  })

  it('supports a hidden label and leading adornment without losing its accessible name', () => {
    const wrapper = mount(FormInput, {
      props: { modelValue: '', label: 'Search devices', hideLabel: true },
      slots: { leading: '<span data-test="leading">icon</span>' },
    })

    expect(wrapper.get('label').classes()).toContain('sr-only')
    expect(wrapper.get('input').attributes('aria-label')).toBe('Search devices')
    expect(wrapper.get('input').classes()).toContain('pl-9')
    expect(wrapper.get('[data-test="leading"]').text()).toBe('icon')
  })
})
