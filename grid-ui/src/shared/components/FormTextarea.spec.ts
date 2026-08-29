import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormTextarea from './FormTextarea.vue'

describe('FormTextarea', () => {
  it('forwards native attributes and applies the shared invalid state', async () => {
    const wrapper = mount(FormTextarea, {
      props: {
        modelValue: '',
        label: 'Blocked caller numbers',
        error: ['Enter at least one valid number.'],
      },
      attrs: {
        rows: '8',
        placeholder: '+15550001000',
      },
    })
    const textarea = wrapper.get('textarea')

    expect(textarea.attributes('rows')).toBe('8')
    expect(textarea.attributes('placeholder')).toBe('+15550001000')
    expect(textarea.attributes('aria-invalid')).toBe('true')
    expect(textarea.classes()).toContain('!border-red-400')

    await textarea.setValue('+15550001001')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['+15550001001'])
  })
})
