import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DescendantOnboardingPanel from './DescendantOnboardingPanel.vue'

describe('DescendantOnboardingPanel', () => {
  it('keeps confirmation and access acknowledgement errors inline', async () => {
    const wrapper = mount(DescendantOnboardingPanel, {
      props: {
        data: {
          candidates: [
            {
              reference: 'opaque-reference',
              name: 'Acme Child',
              realm: 'acme.example.test',
              descendants_count: 0,
              eligible: true,
              blocked_reason: null,
            },
          ],
          target_organization: { id: 'organization-public-id', name: 'GridPBX' },
          access_inheritance: { member_count: 2, acknowledgement_required: true },
          reference_expires_at: '2026-08-30T10:10:00Z',
        },
        loading: false,
        saving: false,
        error: null,
        fieldErrors: {},
      },
      global: {
        stubs: { CrudSlideOver: { template: '<div><slot /></div>' } },
      },
    })

    await wrapper.get('[role="radio"]').trigger('click')
    await wrapper.get('form').trigger('submit')

    const confirmation = wrapper.get('input[aria-label="Descendant account name"]')
    expect(confirmation.attributes('aria-invalid')).toBe('true')
    expect(confirmation.classes()).toContain('!border-red-400')
    expect(wrapper.text()).toContain('Enter the descendant account name.')
    expect(wrapper.text()).toContain('Acknowledge the inherited organization access.')
  })
})
