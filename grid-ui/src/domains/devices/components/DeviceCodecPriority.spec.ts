import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DeviceCodecPriority from './DeviceCodecPriority.vue'

describe('DeviceCodecPriority', () => {
  it('appends a newly enabled codec after the current priorities', async () => {
    const wrapper = mount(DeviceCodecPriority, {
      props: {
        modelValue: ['PCMU'],
        label: 'Audio codec priority',
        description: 'First priority to last.',
        options: ['PCMU', 'PCMA', 'OPUS'],
      },
    })

    await wrapper.get('button[aria-label="Add OPUS to Audio codec priority"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['PCMU', 'OPUS']])
  })

  it('reorders the selected codecs without changing their values', async () => {
    const wrapper = mount(DeviceCodecPriority, {
      props: {
        modelValue: ['PCMU', 'PCMA'],
        label: 'Audio codec priority',
        description: 'First priority to last.',
        options: ['PCMU', 'PCMA'],
      },
    })

    await wrapper.get('button[aria-label="Move PCMA up"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['PCMA', 'PCMU']])
  })

  it('shows the field error on the ordered control boundary', () => {
    const wrapper = mount(DeviceCodecPriority, {
      props: {
        modelValue: [],
        label: 'Video codec priority',
        description: 'First priority to last.',
        options: ['H264'],
        error: 'Select a supported codec.',
      },
    })

    expect(wrapper.get('[aria-invalid="true"]').classes()).toContain('border-danger')
    expect(wrapper.text()).toContain('Select a supported codec.')
  })
})
