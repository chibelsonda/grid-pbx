import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { paymentApi } from '../api/paymentApi'
import SandboxPaymentPanel from './SandboxPaymentPanel.vue'
import type { PaymentCapability } from '../types/payment'

vi.mock('../api/paymentApi', () => ({
  paymentApi: {
    capabilities: vi.fn(),
    attempts: vi.fn(),
    attempt: vi.fn(),
    profiles: vi.fn(),
    webhookHealth: vi.fn(),
    retryWebhook: vi.fn(),
    sandboxCharge: vi.fn(),
    sandboxVoid: vi.fn(),
    sandboxRefund: vi.fn(),
    createSandboxCustomerProfile: vi.fn(),
  },
}))

const capability = (available: boolean): PaymentCapability => ({
  enabled: available,
  provider: 'authorize_net',
  environment: 'sandbox',
  configured: true,
  capture_strategy: 'hosted_or_tokenized',
  server_accepts_card_data: false,
  client: {
    available,
    accept_ui_url: available ? 'https://jstest.authorize.net/v3/AcceptUI.js' : null,
    api_login_id: available ? 'public-login-id' : null,
    public_client_key: available ? 'public-client-key' : null,
    sandbox_max_charge_minor: available ? 100 : null,
    sandbox_max_refund_minor: null,
  },
  mutations: {
    attach_payment_method: false,
    charge: available,
    void: false,
    refund: false,
  },
  webhooks: {
    enabled: false,
    configured: false,
    accepting: false,
  },
})

describe('SandboxPaymentPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(paymentApi.attempts).mockResolvedValue([])
    vi.mocked(paymentApi.profiles).mockResolvedValue([])
    vi.mocked(paymentApi.webhookHealth).mockResolvedValue({
      summary: {
        received: 0,
        processing: 0,
        processed: 0,
        ignored: 0,
        retry_pending: 0,
        failed: 0,
        total: 0,
        requiring_attention: 0,
      },
      recovery_available: false,
      deliveries: [],
    })
  })

  afterEach(() => {
    document.querySelectorAll('[data-grid-pbx-accept-ui]').forEach((element) => element.remove())
    delete window.gridPbxAuthorizeNetResponseHandler
  })

  it('does not load the provider script while mutations are disabled', async () => {
    vi.mocked(paymentApi.capabilities).mockResolvedValue(capability(false))

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      global: { plugins: [createPinia()] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Sandbox charging is disabled')
    expect(document.querySelector('[data-grid-pbx-accept-ui]')).toBeNull()
  })

  it('loads the hosted form after its button and submits only the returned opaque token', async () => {
    vi.mocked(paymentApi.capabilities).mockResolvedValue(capability(true))
    vi.mocked(paymentApi.sandboxCharge).mockResolvedValue({
      id: '00000000-0000-4000-8000-000000000002',
      source_attempt_id: null,
      provider: 'authorize_net',
      operation: 'charge',
      amount: '1.00000000',
      currency: 'USD',
      status: 'succeeded',
      safe_error_code: null,
      provider_status: null,
      reconciled_at: null,
      completed_at: null,
      created_at: null,
    })
    vi.spyOn(crypto, 'randomUUID').mockReturnValue('00000000-0000-4000-8000-000000000001')

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      attachTo: document.body,
      global: { plugins: [createPinia()] },
    })
    await flushPromises()

    const script = document.querySelector<HTMLScriptElement>('[data-grid-pbx-accept-ui]')
    const button = wrapper.get('button.AcceptUI')
    expect(script?.src).toBe('https://jstest.authorize.net/v3/AcceptUI.js')
    expect(button.attributes('data-apiloginid')).toBe('public-login-id')
    expect(button.attributes('data-clientkey')).toBe('public-client-key')
    expect(button.attributes('data-billingaddressoptions')).toBe(
      JSON.stringify({ show: true, required: false }),
    )
    expect(button.attributes('data-paymentoptions')).toBe(
      JSON.stringify({ showCreditCard: true, showBankAccount: false }),
    )

    script?.dispatchEvent(new Event('load'))
    window.gridPbxAuthorizeNetResponseHandler?.({
      messages: { resultCode: 'Ok' },
      opaqueData: {
        dataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT',
        dataValue: 'one-time-provider-token',
      },
    })
    await flushPromises()

    expect(paymentApi.sandboxCharge).toHaveBeenCalledWith(
      'account-public-id',
      100,
      {
        dataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT',
        dataValue: 'one-time-provider-token',
      },
      '00000000-0000-4000-8000-000000000001',
    )
    expect(wrapper.text()).not.toContain('one-time-provider-token')
    wrapper.unmount()
  })

  it('shows only the provider error code when hosted tokenization fails', async () => {
    vi.mocked(paymentApi.capabilities).mockResolvedValue(capability(true))

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      global: { plugins: [createPinia()] },
    })
    await flushPromises()

    window.gridPbxAuthorizeNetResponseHandler?.({
      messages: {
        resultCode: 'Error',
        message: [{ code: 'E_WC_TEST' }],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('E_WC_TEST')
    wrapper.unmount()
  })

  it('shows only independently enabled controls for a successful stored charge', async () => {
    const enabled = capability(false)
    enabled.mutations = {
      attach_payment_method: true,
      charge: false,
      void: true,
      refund: true,
    }
    enabled.client.sandbox_max_refund_minor = 100
    vi.mocked(paymentApi.capabilities).mockResolvedValue(enabled)
    vi.mocked(paymentApi.attempts).mockResolvedValue([
      {
        id: '00000000-0000-4000-8000-000000000002',
        source_attempt_id: null,
        provider: 'authorize_net',
        operation: 'charge',
        amount: '1.00000000',
        currency: 'USD',
        status: 'succeeded',
        safe_error_code: null,
        provider_status: null,
        reconciled_at: null,
        completed_at: null,
        created_at: null,
      },
    ])

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      global: {
        plugins: [createPinia()],
        stubs: { Teleport: true },
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Sandbox charging is disabled')
    expect(wrapper.text()).toContain('Void unsettled charge')
    expect(wrapper.text()).toContain('Save payment profile')
    expect(wrapper.text()).toContain('Refund $1.00')
    expect(document.querySelector('[data-grid-pbx-accept-ui]')).toBeNull()
    wrapper.unmount()
  })

  it('shows safe webhook health and disables recovery when provider verification is unavailable', async () => {
    vi.mocked(paymentApi.capabilities).mockResolvedValue(capability(false))
    vi.mocked(paymentApi.webhookHealth).mockResolvedValue({
      summary: {
        received: 0,
        processing: 0,
        processed: 0,
        ignored: 0,
        retry_pending: 0,
        failed: 1,
        total: 1,
        requiring_attention: 1,
      },
      recovery_available: false,
      deliveries: [
        {
          id: '00000000-0000-4000-8000-000000000010',
          payment_attempt_id: '00000000-0000-4000-8000-000000000002',
          provider: 'authorize_net',
          event_type: 'net.authorize.payment.authcapture.created',
          status: 'failed',
          processing_attempts: 5,
          safe_error_code: 'reconciliation_exhausted',
          can_retry: true,
          recovery_guidance:
            'Automatic retries were exhausted. Verify provider connectivity, then retry.',
          event_occurred_at: null,
          received_at: null,
          processed_at: null,
        },
      ],
    })

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      global: { plugins: [createPinia()] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Webhook reconciliation health')
    expect(wrapper.text()).toContain('Manual recovery is unavailable')
    expect(wrapper.get('button[disabled]').text()).toContain('Retry')
    expect(wrapper.text()).not.toMatch(/private-provider|transaction-key|signature-key/i)
    wrapper.unmount()
  })

  it('hydrates the safe profile inventory and suppresses duplicate profile creation after reload', async () => {
    const enabled = capability(false)
    enabled.mutations.attach_payment_method = true
    vi.mocked(paymentApi.capabilities).mockResolvedValue(enabled)
    vi.mocked(paymentApi.attempts).mockResolvedValue([
      {
        id: '00000000-0000-4000-8000-000000000002',
        source_attempt_id: null,
        provider: 'authorize_net',
        operation: 'charge',
        amount: '1.00000000',
        currency: 'USD',
        status: 'succeeded',
        safe_error_code: null,
        provider_status: null,
        reconciled_at: null,
        completed_at: null,
        created_at: null,
      },
    ])
    vi.mocked(paymentApi.profiles).mockResolvedValue([
      {
        id: '00000000-0000-4000-8000-000000000008',
        provider: 'authorize_net',
        status: 'active',
        masked_account: 'XXXX1111',
        account_type: 'Visa',
        created_at: null,
        updated_at: null,
      },
    ])

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      global: { plugins: [createPinia()] },
    })
    await flushPromises()

    expect(paymentApi.profiles).toHaveBeenCalledWith('account-public-id')
    expect(wrapper.text()).toContain('Saved payment profiles')
    expect(wrapper.text()).toContain('XXXX1111')
    expect(wrapper.text()).toContain('Visa')
    expect(wrapper.text()).not.toContain('Save payment profile')
    expect(wrapper.text()).not.toMatch(/customer_profile_id|payment_profile_id|provider_reference/i)
    wrapper.unmount()
  })

  it('expands a recent attempt into a sanitized immutable timeline', async () => {
    const attemptId = '00000000-0000-4000-8000-000000000002'
    vi.mocked(paymentApi.capabilities).mockResolvedValue(capability(false))
    vi.mocked(paymentApi.attempts).mockResolvedValue([
      {
        id: attemptId,
        source_attempt_id: null,
        provider: 'authorize_net',
        operation: 'charge',
        amount: '1.00000000',
        currency: 'USD',
        status: 'succeeded',
        safe_error_code: null,
        provider_status: null,
        reconciled_at: null,
        completed_at: null,
        created_at: null,
      },
    ])
    vi.mocked(paymentApi.attempt).mockResolvedValue({
      id: attemptId,
      source_attempt_id: null,
      provider: 'authorize_net',
      operation: 'charge',
      amount: '1.00000000',
      currency: 'USD',
      status: 'succeeded',
      safe_error_code: null,
      provider_status: 'settled',
      reconciled_at: null,
      completed_at: null,
      created_at: null,
      events: [
        {
          id: '00000000-0000-4000-8000-000000000011',
          event_type: 'webhook_reconciled',
          status: 'succeeded',
          summary: 'The provider status was reconciled from a signed webhook.',
          safe_error_code: null,
          provider_status: 'settled',
          created_at: null,
        },
      ],
    })

    const wrapper = mount(SandboxPaymentPanel, {
      props: { accountId: 'account-public-id' },
      global: { plugins: [createPinia()] },
    })
    await flushPromises()

    await wrapper.get(`button[aria-expanded="false"]`).trigger('click')
    await flushPromises()

    expect(paymentApi.attempt).toHaveBeenCalledWith('account-public-id', attemptId)
    expect(wrapper.text()).toContain('The provider status was reconciled from a signed webhook.')
    expect(wrapper.text()).not.toMatch(/provider_reference|request_ip|signature_key|safe_context/i)
    wrapper.unmount()
  })
})
