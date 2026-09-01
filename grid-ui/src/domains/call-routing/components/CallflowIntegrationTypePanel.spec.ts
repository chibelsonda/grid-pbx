import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CallflowIntegrationTypePanel from './CallflowIntegrationTypePanel.vue'

const CrudSlideOverStub = {
  emits: ['close'],
  template: `
    <section role="dialog" :aria-label="$attrs.title">
      <button type="button" aria-label="Close integration picker" @click="$emit('close')">
        Close
      </button>
      <slot />
    </section>
  `,
}

function mountPanel() {
  return mount(CallflowIntegrationTypePanel, {
    global: {
      stubs: {
        CrudSlideOver: CrudSlideOverStub,
      },
    },
  })
}

describe('CallflowIntegrationTypePanel', () => {
  it('uses one capability picker for every safely supported integration type', async () => {
    const wrapper = mountPanel()

    const pivot = wrapper.get('button[aria-label="Add Pivot profile"]')
    const webhook = wrapper.get('button[aria-label="Add Webhook profile"]')
    const globalCarrier = wrapper.get('button[aria-label="Add Global carrier profile"]')
    const accountCarrier = wrapper.get('button[aria-label="Add Account carrier profile"]')

    await pivot.trigger('click')
    await webhook.trigger('click')
    await globalCarrier.trigger('click')
    await accountCarrier.trigger('click')

    expect(wrapper.emitted('select')).toEqual([
      ['pivot'],
      ['webhook'],
      ['global_carrier'],
      ['account_carrier'],
    ])
    expect(wrapper.text()).toContain('Global carrier')
    expect(wrapper.text()).toContain('Account carrier')
    expect(wrapper.text()).toContain('An active, valid profile enables only its matching action')

    expect(wrapper.findAll('button:disabled')).toHaveLength(0)
  })

  it('closes without selecting an integration', async () => {
    const wrapper = mountPanel()

    await wrapper.get('button[aria-label="Close integration picker"]').trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(wrapper.emitted('select')).toBeUndefined()
  })
})
