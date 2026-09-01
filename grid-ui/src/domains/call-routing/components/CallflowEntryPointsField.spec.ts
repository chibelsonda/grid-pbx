import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CallflowEntryPointsField from './CallflowEntryPointsField.vue'

describe('CallflowEntryPointsField', () => {
  it('adds and removes a valid internal extension number', async () => {
    const wrapper = mount(CallflowEntryPointsField, {
      props: {
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
      },
    })

    await wrapper.get('input[aria-label="Internal extension number"]').setValue('2999')
    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('update:extensionNumbers')).toEqual([[['2999']]])

    await wrapper.setProps({ extensionNumbers: ['2999'] })
    await wrapper.get('[aria-label="Remove extension number 2999"]').trigger('click')

    expect(wrapper.emitted('update:extensionNumbers')?.at(-1)).toEqual([[]])
  })

  it('rejects invalid and preserved extension numbers before submission', async () => {
    const wrapper = mount(CallflowEntryPointsField, {
      props: {
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
        preservedNumbers: ['2001'],
      },
    })
    const input = wrapper.get('input[aria-label="Internal extension number"]')

    await input.setValue('2A99')
    await wrapper.get('button').trigger('click')
    expect(wrapper.text()).toContain('Use 2 to 15 digits')

    await input.setValue('2001')
    await wrapper.get('button').trigger('click')
    expect(wrapper.text()).toContain('already configured')
    expect(wrapper.emitted('update:extensionNumbers')).toBeUndefined()
  })
})
