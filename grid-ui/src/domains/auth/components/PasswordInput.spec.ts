import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PasswordInput from './PasswordInput.vue'

describe('PasswordInput', () => {
  it('toggles visibility with an accessible button', async () => {
    const wrapper = mount(PasswordInput, {
      props: {
        modelValue: 'Secret-password1!',
        label: 'Password',
        name: 'password',
        autocomplete: 'current-password',
        'onUpdate:modelValue': () => undefined,
      },
    })

    expect(wrapper.get('input').attributes('type')).toBe('password')
    expect(wrapper.get('button').attributes('aria-label')).toBe('Show password')

    await wrapper.get('button').trigger('click')

    expect(wrapper.get('input').attributes('type')).toBe('text')
    expect(wrapper.get('button').attributes('aria-label')).toBe('Hide password')
    expect(wrapper.get('button').attributes('aria-pressed')).toBe('true')
  })
})
