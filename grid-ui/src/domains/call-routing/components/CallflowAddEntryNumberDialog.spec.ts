import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CallflowAddEntryNumberDialog from './CallflowAddEntryNumberDialog.vue'

const dialogStub = {
  props: ['open'],
  template: '<div v-if="open"><slot /></div>',
}

describe('CallflowAddEntryNumberDialog', () => {
  it('validates and emits a new internal extension', async () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: ['2001'],
        preservedNumbers: ['*97'],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Use 2 to 15 digits')
    expect(wrapper.emitted('add')).toBeUndefined()

    await wrapper.get('input[placeholder="e.g. 2999"]').setValue('2999')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('add')).toEqual([[{ type: 'extension', value: '2999' }]])
  })

  it('prevents adding an entry already present on the callflow', async () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: ['2999'],
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    await wrapper.get('input[placeholder="e.g. 2999"]').setValue('2999')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.text()).toContain('already configured')
    expect(wrapper.emitted('add')).toBeUndefined()
  })

  it('shows a server field error only beside the affected input', () => {
    const message = 'Extension 1234 is already assigned to another callflow.'
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
        fieldErrors: { extension_numbers: [message] },
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    expect(wrapper.get('input[placeholder="e.g. 2999"]').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text().match(/Extension 1234 is already assigned to another callflow\./g)).toHaveLength(1)
    expect(wrapper.find('[data-testid="form-error-summary"]').exists()).toBe(false)
  })

  it('uses one dialog summary for a non-field failure', () => {
    const wrapper = mount(CallflowAddEntryNumberDialog, {
      props: {
        open: true,
        phoneNumbers: [],
        phoneNumberIds: [],
        extensionNumbers: [],
        error: 'Unable to verify the latest assignments.',
      },
      global: { stubs: { CallflowNodeInfoDialog: dialogStub } },
    })

    expect(wrapper.findAll('[data-testid="form-error-summary"]')).toHaveLength(1)
    expect(wrapper.text().match(/Unable to verify the latest assignments\./g)).toHaveLength(1)
  })
})
