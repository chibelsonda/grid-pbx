import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ServiceDetailPanel from './ServiceDetailPanel.vue'
import type { ServiceOverview } from '../types/service'

describe('ServiceDetailPanel', () => {
  it('renders a compact billing handoff without duplicating billing records', () => {
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
      documents: {
        invoices: {
          available: true,
          authoritative: true,
          source: 'legacy_gridpbx_mysql',
          reported_count: 1,
          items: [
            {
              id: '00000000-0000-4000-8000-000000000020',
              number: 'INV-100',
              status: 'open',
              currency: null,
              total: '150.50',
              amount_paid: '50.25',
              amount_due: '100.25',
              issued_at: '2026-08-01',
              due_at: '2026-08-31',
              document_available: false,
            },
          ],
          guidance: 'Invoice summaries are read from the confirmed legacy billing authority.',
        },
        receipts: {
          available: false,
          authoritative: false,
          source: 'unconfigured',
          items: [],
          guidance: 'A provider receipt contract has not been approved.',
        },
        payment_confirmations: {
          available: true,
          authoritative: false,
          source: 'gridpbx_payment_attempts',
          guidance: 'These records do not replace an invoice or provider-issued receipt.',
          items: [
            {
              id: 'payment-attempt-public-id',
              source_attempt_id: null,
              provider: 'authorize_net',
              operation: 'charge',
              amount: '1.00000000',
              currency: 'USD',
              status: 'succeeded',
              completed_at: null,
            },
          ],
        },
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
      props: { overview },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
        },
      },
    })

    expect(wrapper.text()).toContain('Billing workspace')
    expect(wrapper.text()).toContain('Recurring9.99')
    expect(wrapper.text()).toContain('Due today2.5')
    expect(wrapper.text()).toContain('Invoice groups1')
    expect(wrapper.text()).toContain('Open billing workspace')
    expect(wrapper.get('a').attributes('href')).toBe('/billing')
    expect(wrapper.text()).not.toContain('Switch billing activity')
    expect(wrapper.text()).not.toContain('Billing documents')
    expect(wrapper.text()).not.toContain('INV-100')
    expect(wrapper.text()).not.toContain('Charge confirmed')
    expect(wrapper.findAll('[role="tab"]')).toHaveLength(0)
  })

  it('links attention states to Billing without exposing diagnostic details', () => {
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
      documents: {
        invoices: {
          available: false as const,
          authoritative: false as const,
          source: 'unconfigured' as const,
          reported_count: 0,
          items: [],
          guidance: 'Configure an approved invoice source before documents are shown.',
        },
        receipts: {
          available: false as const,
          authoritative: false as const,
          source: 'unconfigured' as const,
          items: [],
          guidance: 'A provider receipt contract has not been approved.',
        },
        payment_confirmations: {
          available: true as const,
          authoritative: false as const,
          source: 'gridpbx_payment_attempts' as const,
          items: [],
          guidance: 'These records do not replace an invoice or provider-issued receipt.',
        },
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
      props: { overview },
      global: {
        stubs: {
          CrudSlideOver: { template: '<div><slot /></div>' },
          RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
        },
      },
    })

    expect(wrapper.text()).toContain('Requires attention')
    expect(wrapper.text()).toContain('Open billing workspace')
    expect(wrapper.text()).not.toContain('Ledger row count')
    expect(wrapper.text()).not.toContain('Switch authentication prevented')
    expect(wrapper.text()).not.toContain('SQLSTATE')
  })
})
