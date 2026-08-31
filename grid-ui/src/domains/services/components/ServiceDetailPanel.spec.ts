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
      reconciliation: {
        status: 'healthy',
        checks: [
          {
            code: 'latest_service_sync',
            label: 'Latest service synchronization',
            status: 'passed',
            message: 'The latest synchronization completed successfully.',
            guidance: 'No recovery action is required.',
            expected_count: null,
            actual_count: null,
          },
        ],
        sync_history: [
          {
            id: 'public-run-reference',
            status: 'succeeded',
            processed_count: 8,
            failure_category: null,
            message: null,
            guidance: null,
            started_at: null,
            finished_at: null,
            created_at: null,
          },
        ],
      },
      plans: [],
      quantities: [],
      limits: null,
      last_synced_at: null,
      sync_status: 'healthy',
    }

    const wrapper = mount(ServiceDetailPanel, {
      props: { accountId: 'account-public-id', overview },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          SandboxPaymentPanel: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Switch billing activity')
    expect(wrapper.text()).toContain('Per Minute Voip')
    expect(wrapper.text()).toContain('Monthly rollup')
    expect(wrapper.text()).toContain('10.18')
    expect(wrapper.text()).toContain('Billing reconciliation')
    expect(wrapper.text()).toContain('8 processed')
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
      reconciliation: {
        status: 'error' as const,
        checks: [
          {
            code: 'ledger_projection_count',
            label: 'Ledger row count',
            status: 'failed' as const,
            message: 'The stored summary expects 2 rows, but 0 active rows are projected.',
            guidance: 'Run the read-only synchronization again.',
            expected_count: 2,
            actual_count: 0,
          },
        ],
        sync_history: [
          {
            id: 'safe-public-run-id',
            status: 'failed' as const,
            processed_count: 0,
            failure_category: 'authentication' as const,
            message: 'Switch authentication prevented the billing synchronization.',
            guidance: 'Ask an administrator to verify server-side credentials.',
            started_at: null,
            finished_at: null,
            created_at: null,
          },
        ],
      },
      plans: [],
      quantities: [],
      limits: null,
      last_synced_at: null,
      sync_status: 'healthy' as const,
    }

    const wrapper = mount(ServiceDetailPanel, {
      props: { accountId: 'account-public-id', overview },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          SandboxPaymentPanel: true,
        },
      },
    })

    expect(wrapper.text()).toContain('did not expose every read-only billing endpoint')
    expect(wrapper.text()).toContain('no write fallback is attempted')
    expect(wrapper.text()).toContain('Ledger row count')
    expect(wrapper.text()).toContain('Expected 2 · Projected 0')
    expect(wrapper.text()).toContain('Switch authentication prevented')
    expect(wrapper.text()).not.toContain('SQLSTATE')
  })
})
