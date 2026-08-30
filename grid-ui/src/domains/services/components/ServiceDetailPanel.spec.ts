import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ServiceDetailPanel from './ServiceDetailPanel.vue'
import type { ServiceOverview } from '../types/service'

describe('ServiceDetailPanel', () => {
  it('renders safe read-only billing projections without Switch identifiers', () => {
    const overview: ServiceOverview = {
      id: 'summary-public-id',
      standing: { acceptable: true, reason: null },
      reseller: { is_reseller: false, billing_account: null, billing_account_projected: true },
      billing_cycle: { next_at: null, period: 1, unit: 'month' },
      billing_impact: { invoice_count: 1, due_today: 2.5, recurring_amount: 9.99 },
      billing: {
        id: 'billing-public-id',
        ledger_total: '-44.56040000',
        ledger_source_count: 1,
        transaction_count: 1,
        availability: { ledgers: true, ledger_total: true, transactions: true },
        ledger_summaries: [
          {
            id: 'ledger-public-id',
            source_service: 'per-minute-voip',
            amount: '-54.74040000',
            usage_quantity: '14520.00000000',
            usage_type: 'voice',
            usage_unit: 'sec',
          },
        ],
        transactions: [
          {
            id: 'transaction-public-id',
            amount: '10.18000000',
            type: 'credit',
            reason: 'database_rollup',
            description: 'Monthly rollup',
            code: 9999,
            created_at: null,
          },
        ],
        last_synced_at: null,
      },
      plans: [],
      quantities: [],
      limits: null,
      last_synced_at: null,
      sync_status: 'healthy',
    }

    const wrapper = mount(ServiceDetailPanel, {
      props: { overview },
      global: { stubs: { CrudSlideOver: { template: '<div><slot /></div>' } } },
    })

    expect(wrapper.text()).toContain('Switch billing activity')
    expect(wrapper.text()).toContain('Per Minute Voip')
    expect(wrapper.text()).toContain('Monthly rollup')
    expect(wrapper.text()).toContain('10.18')
    expect(wrapper.text()).not.toContain('transaction-public-id')
    expect(wrapper.text()).not.toContain('ledger-public-id')
  })

  it('explains missing version-specific billing endpoints without offering a mutation', () => {
    const overview = {
      id: 'summary-public-id',
      standing: { acceptable: true, reason: null },
      reseller: { is_reseller: false, billing_account: null, billing_account_projected: true },
      billing_cycle: { next_at: null, period: 1, unit: 'month' },
      billing_impact: { invoice_count: 0, due_today: 0, recurring_amount: 0 },
      billing: {
        id: 'billing-public-id',
        ledger_total: null,
        ledger_source_count: 0,
        transaction_count: 0,
        availability: { ledgers: false, ledger_total: false, transactions: true },
        ledger_summaries: [],
        transactions: [],
        last_synced_at: null,
      },
      plans: [],
      quantities: [],
      limits: null,
      last_synced_at: null,
      sync_status: 'healthy' as const,
    }

    const wrapper = mount(ServiceDetailPanel, {
      props: { overview },
      global: { stubs: { CrudSlideOver: { template: '<div><slot /></div>' } } },
    })

    expect(wrapper.text()).toContain('did not expose every read-only billing endpoint')
    expect(wrapper.text()).toContain('no write fallback is attempted')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
