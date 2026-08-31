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
})

describe('SandboxPaymentPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(paymentApi.attempts).mockResolvedValue([])
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
})
