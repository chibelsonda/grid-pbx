import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConferenceFormPanel from './ConferenceFormPanel.vue'

describe('ConferenceFormPanel', () => {
  it('keeps validation inline and marks every invalid text control', async () => {
    const wrapper = mount(ConferenceFormPanel, {
      props: {
        record: null,
        options: { owners: [], media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    await wrapper.get('input[aria-label="Member numbers"]').setValue('not-a-number')
    await wrapper.get('form').trigger('submit')

    const name = wrapper.get('input[aria-label="Name"]')
    const memberNumbers = wrapper.get('input[aria-label="Member numbers"]')
    expect(name.attributes('aria-invalid')).toBe('true')
    expect(name.classes()).toContain('!border-red-400')
    expect(memberNumbers.attributes('aria-invalid')).toBe('true')
    expect(memberNumbers.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter a conference name.')
    expect(wrapper.text()).not.toContain('Check the highlighted fields and try again.')
  })

  it('separates safe Switch references into the Advanced tab', async () => {
    const wrapper = mount(ConferenceFormPanel, {
      props: {
        record: null,
        options: { owners: [], media: [] },
        saving: false,
        error: null,
        fieldErrors: {},
        canManage: true,
      },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          ConfirmDialog: true,
        },
      },
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Basic', 'Advanced'])
    expect(wrapper.find('input[aria-label="Profile name"]').isVisible()).toBe(false)
    expect(wrapper.find('input[aria-label="General conference numbers"]').isVisible()).toBe(false)

    await wrapper.findAll('[role="tab"]')[1]!.trigger('click')

    expect(wrapper.get('input[aria-label="Profile name"]')).toBeTruthy()
    expect(wrapper.get('input[aria-label="General conference numbers"]')).toBeTruthy()
    expect(wrapper.text()).toContain('Named conference profiles and control sets')
  })
})
