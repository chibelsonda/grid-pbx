import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormFileInput from './FormFileInput.vue'

describe('FormFileInput', () => {
  it('associates its label and emits the selected file', async () => {
    const wrapper = mount(FormFileInput, {
      props: {
        modelValue: null,
        label: 'Audio file',
        ariaLabel: 'Media audio file',
        error: 'Choose an audio file.',
      },
    })
    const input = wrapper.get('input')
    const file = new File(['audio'], 'greeting.wav', { type: 'audio/wav' })

    expect(wrapper.classes()).toContain('content-start')
    expect(wrapper.get('label').attributes('for')).toBe(input.attributes('id'))
    expect(input.attributes('aria-label')).toBe('Media audio file')
    expect(input.attributes('aria-invalid')).toBe('true')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([file])
  })
})
