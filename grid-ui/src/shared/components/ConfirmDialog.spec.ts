import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConfirmDialog from './ConfirmDialog.vue'

describe('ConfirmDialog', () => {
  it('requires an exact typed confirmation when configured', async () => {
    const wrapper = mount(ConfirmDialog, {
      attachTo: document.body,
      props: {
        open: true,
        title: 'Disable account',
        description: 'This affects account operations.',
        confirmationText: 'Grid Support',
      },
      global: {
        stubs: {
          teleport: true,
          Dialog: { template: '<div><slot /></div>' },
          Description: { template: '<p><slot /></p>' },
          DialogDescription: { template: '<p><slot /></p>' },
          DialogPanel: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<h2><slot /></h2>' },
          TransitionChild: { template: '<div><slot /></div>' },
          TransitionRoot: {
            props: ['show'],
            template: '<div v-if="show"><slot /></div>',
          },
        },
      },
    })

    const confirmation = wrapper.get('input[aria-label="Confirmation text"]')
    const confirm = wrapper.get('button.bg-red-600')
    expect(confirm.attributes('disabled')).toBeDefined()

    await confirmation.setValue('grid support')
    expect(confirmation.attributes('aria-invalid')).toBe('true')
    expect(confirm.attributes('disabled')).toBeDefined()

    await confirmation.setValue('Grid Support')
    expect(confirm.attributes('disabled')).toBeUndefined()
  })
})
