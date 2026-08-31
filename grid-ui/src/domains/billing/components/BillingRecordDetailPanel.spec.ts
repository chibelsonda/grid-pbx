import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import BillingRecordDetailPanel from './BillingRecordDetailPanel.vue'
import type { BillingInvoiceDetail, BillingReceiptDetail, BillingRecord } from '../types/billing'

const mountPanel = (record: BillingRecord) =>
  mount(BillingRecordDetailPanel, {
    props: { record },
    global: {
      stubs: {
        CrudSlideOver: { template: '<section><slot /></section>' },
      },
    },
  })

describe('BillingRecordDetailPanel', () => {
  it('renders an authoritative invoice summary without offering an unsafe download', () => {
    const wrapper = mountPanel({
      kind: 'invoice',
      source: 'legacy_gridpbx_mysql',
      authoritative: true,
      item: {
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
    })

    expect(wrapper.text()).toContain('Authoritative summary')
    expect(wrapper.text()).toContain('Legacy Gridpbx Mysql')
    expect(wrapper.text()).toContain('100.25')
    expect(wrapper.text()).toContain('No authoritative invoice document is available')
    expect(wrapper.find('a[download]').exists()).toBe(false)
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('labels a successful payment operation as a non-authoritative confirmation', () => {
    const wrapper = mountPanel({
      kind: 'payment_confirmation',
      source: 'gridpbx_payment_attempts',
      authoritative: false,
      item: {
        id: 'payment-attempt-public-id',
        source_attempt_id: null,
        provider: 'authorize_net',
        operation: 'charge',
        amount: '1.00',
        currency: 'USD',
        status: 'succeeded',
        completed_at: '2026-08-31T01:00:00Z',
      },
    })

    expect(wrapper.text()).toContain('Payment confirmation')
    expect(wrapper.text()).toContain('Authorize Net')
    expect(wrapper.text()).toContain('USD 1')
    expect(wrapper.text()).toContain('not an invoice, tax document, or provider-issued receipt')
    expect(wrapper.text()).not.toContain('source_attempt_id')
  })

  it('offers download only after account-scoped detail confirms a safe document', async () => {
    const record: BillingRecord = {
      kind: 'invoice',
      source: 'test_authority',
      authoritative: true,
      item: {
        id: '96d7161d-438d-48fc-a69f-03d68f6f4f51',
        number: 'INV-2026-100',
        status: 'open',
        currency: 'USD',
        total: '150.50',
        amount_paid: '50.25',
        amount_due: '100.25',
        issued_at: '2026-08-01',
        due_at: '2026-08-31',
        document_available: true,
      },
    }
    const detail: BillingInvoiceDetail = {
      ...record.item,
      authoritative: true,
      source: 'test_authority',
      line_items: { available: false, items: [] },
      document: { available: true, content_type: 'application/pdf' },
    }
    const wrapper = mount(BillingRecordDetailPanel, {
      props: { record, invoiceDetail: detail },
      global: {
        stubs: { CrudSlideOver: { template: '<section><slot /></section>' } },
      },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.text()).toContain('Download invoice PDF')
    expect(wrapper.emitted('download')).toHaveLength(1)
  })

  it('keeps provider receipts separate and offers only a confirmed safe PDF', async () => {
    const record: BillingRecord = {
      kind: 'receipt',
      source: 'test_authority',
      authoritative: true,
      item: {
        id: '6eb271ad-d3a0-474a-abce-7af6e703de31',
        number: 'RCT-2026-100',
        status: 'settled',
        currency: 'USD',
        amount: '50.25',
        paid_at: '2026-08-15T12:00:00Z',
        document_available: true,
      },
    }
    const detail: BillingReceiptDetail = {
      ...record.item,
      authoritative: true,
      source: 'test_authority',
      document: { available: true, content_type: 'application/pdf' },
    }
    const wrapper = mount(BillingRecordDetailPanel, {
      props: { record, receiptDetail: detail },
      global: {
        stubs: { CrudSlideOver: { template: '<section><slot /></section>' } },
      },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.text()).toContain('Authoritative receipt')
    expect(wrapper.text()).toContain('Download receipt PDF')
    expect(wrapper.emitted('download')).toHaveLength(1)
  })
})
