import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ResellerDiagnosticDetails from './ResellerDiagnosticDetails.vue'

describe('ResellerDiagnosticDetails', () => {
  it('shows safe affected-account context and recovery guidance', () => {
    const wrapper = mount(ResellerDiagnosticDetails, {
      props: {
        guidance: 'Synchronize services before continuing.',
        accounts: [
          {
            id: 'public-account-id',
            name: 'Acme Child',
            realm: 'acme.example.test',
            service_projection_status: 'stale',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('Recovery guidance')
    expect(wrapper.text()).toContain('Synchronize services before continuing.')
    expect(wrapper.text()).toContain('Acme Child')
    expect(wrapper.text()).toContain('acme.example.test')
    expect(wrapper.text()).toContain('stale')
    expect(wrapper.text()).not.toContain('public-account-id')
  })

  it('explains non-account blockers without offering a mutation', () => {
    const wrapper = mount(ResellerDiagnosticDetails, {
      props: {
        guidance: 'Obtain an approved platform policy.',
        accounts: [],
      },
    })

    expect(wrapper.text()).toContain('not tied to a projected account record')
    expect(wrapper.text()).toContain('without attempting a reseller-role mutation')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
