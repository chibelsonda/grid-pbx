import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToggleSwitch from './ToggleSwitch.vue'

describe('ToggleSwitch', () => {
  it('forwards invalid state to the interactive switch', () => {
    const wrapper = mount(ToggleSwitch, {
      props: {
        modelValue: false,
        label: 'Require password update',
        invalid: true,
      },
    })

    expect(wrapper.get('[role="switch"]').attributes('aria-invalid')).toBe('true')
  })
})
