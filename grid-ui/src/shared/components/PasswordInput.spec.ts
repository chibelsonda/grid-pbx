import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PasswordInput from './PasswordInput.vue'

describe('PasswordInput', () => {
  it('shows and hides the password without changing its value or accessible label', async () => {
    const wrapper = mount(PasswordInput, {
      props: {
        modelValue: 'secret-password',
        label: 'Password',
        name: 'password',
      },
    })
    const input = wrapper.get('input[name="password"]')
    const toggle = wrapper.get('button[aria-label="Show password"]')

    expect(input.attributes('type')).toBe('password')
    expect((input.element as HTMLInputElement).value).toBe('secret-password')
    expect(toggle.attributes('aria-pressed')).toBe('false')

    await toggle.trigger('click')

    expect(input.attributes('type')).toBe('text')
    expect((input.element as HTMLInputElement).value).toBe('secret-password')
    expect(wrapper.get('button[aria-label="Hide password"]').attributes('aria-pressed')).toBe(
      'true',
    )
  })
})
