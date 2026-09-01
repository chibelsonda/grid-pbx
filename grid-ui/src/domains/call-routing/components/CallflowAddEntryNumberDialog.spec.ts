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
})
