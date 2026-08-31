import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PhoneNumberDetailPanel from './PhoneNumberDetailPanel.vue'
import type { PhoneNumber } from '../types/phoneNumber'

const record: PhoneNumber = {
  id: '2baf74c0-70dc-486f-a345-e910034e032c',
  number: '+15551234567',
  state: 'port_in',
  used_by: 'callflow',
  carrier_name: 'Test Carrier',
  features: ['inbound_cnam', 'e911'],
  cnam: { display_name: 'GridPBX', inbound_lookup: true },
  e911: {
    status: 'PROVISIONED',
    caller_name: 'GridPBX Reception',
    street_address: '100 Main Street',
    extended_address: 'Suite 200',
    locality: 'San Francisco',
    region: 'CA',
    postal_code: '94105',
    notification_contact_emails: ['ops@example.test'],
  },
  porting: {
    active: true,
    requested_port_date: '2026-09-15',
    service_provider: 'Example Carrier',
  },
  capabilities: {
    available_features: ['cnam', 'e911', 'port'],
    cnam: {
      available: true,
      writable: false,
      reason:
        'Switch reports CNAM as selectable, but the installed notifier workflow does not confirm carrier completion. Mutation remains disabled pending approved quote, charge-confirmation, audit, and reconciliation policy.',
    },
    e911: {
      available: true,
      writable: false,
      reason:
        'Switch reports E911 as selectable, but GridPBX has not confirmed provider readiness or emergency-caller-ID safeguards. Mutation remains disabled pending approved emergency-service, billing, confirmation, audit, and reconciliation policy.',
    },
    porting: { available: true, writable: false, reason: 'Porting policy required.' },
    purchasing: { available: false, writable: false, reason: 'Carrier required.' },
    release: { available: false, writable: false, reason: 'Carrier required.' },
  },
  assigned_callflow: null,
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T09:00:00+08:00',
}

describe('PhoneNumberDetailPanel', () => {
  it('renders allowlisted schema-backed feature details and explicit mutation gates', () => {
    const wrapper = mount(PhoneNumberDetailPanel, {
      props: { record, loading: false, error: null },
      global: {
        stubs: {
          Teleport: true,
          TransitionRoot: { template: '<div><slot /></div>' },
          TransitionChild: { template: '<div><slot /></div>' },
          Dialog: { template: '<div><slot /></div>' },
          DialogPanel: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<h2><slot /></h2>' },
        },
      },
    })

    expect(wrapper.text()).toContain('100 Main Street, Suite 200')
    expect(wrapper.text()).toContain('ops@example.test')
    expect(wrapper.text()).toContain('Example Carrier')
    expect(wrapper.text()).toContain('Caller name (CNAM)')
    expect(wrapper.text()).toContain('Policy gated')
    expect(wrapper.text()).toContain('does not confirm carrier completion')
    expect(wrapper.text()).toContain('has not confirmed provider readiness')
    expect(wrapper.text()).not.toContain('private-provider-id')
    expect(wrapper.findAll('[role="tab"]')).toHaveLength(0)
  })
})
