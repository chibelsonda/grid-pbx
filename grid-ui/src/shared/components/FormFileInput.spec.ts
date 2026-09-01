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

  it('accepts a file dropped on the optional dropzone', async () => {
    const wrapper = mount(FormFileInput, {
      props: {
        modelValue: null,
        label: 'Logo image',
        dropzone: true,
        dropPrompt: 'Drag and drop your logo here',
        required: true,
      },
    })
    const file = new File(['logo'], 'brand.png', { type: 'image/png' })
    const input = wrapper.get('input')

    expect(wrapper.text()).toContain('Drag and drop your logo here')
    expect(input.attributes('required')).toBeDefined()
    expect(input.attributes('aria-required')).toBe('true')
    await wrapper.get('[data-testid="file-dropzone"]').trigger('drop', {
      dataTransfer: { files: { item: () => file } },
    })

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([file])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([file])
    await wrapper.setProps({ modelValue: file })
    expect(input.attributes('required')).toBeUndefined()
    expect(input.attributes('aria-required')).toBe('true')
  })
})
