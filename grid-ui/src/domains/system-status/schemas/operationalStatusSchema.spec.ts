import { describe, expect, it } from 'vitest'
import { operationalStatusSchema } from './operationalStatusSchema'

const payload = {
  observed_at: '2026-08-31T08:00:00+00:00',
  presence: {
    subscription_diagnostics_available: true,
    live_status_available: false,
    commands_available: false,
  },
  parking: {
    summary_available: true,
    active_call_count: 2,
    actions_available: false,
  },
  webhooks: {
    event_catalog_available: true,
    available_event_count: 9,
    configuration_summary_available: true,
    configured_count: 0,
    enabled_count: 0,
    configuration_mutations_available: false,
    delivery_history_available: false,
  },
  messaging: {
    sms_inventory_available: false,
    mms_inventory_available: false,
    message_content_available: false,
    sending_available: false,
  },
  number_porting: {
    inventory_available: true,
    request_details_available: false,
    documents_available: false,
    workflow_mutations_available: false,
  },
  number_management: {
    carrier_configuration_available: true,
    search_available: false,
    purchase_available: false,
    reservation_available: false,
    release_available: false,
  },
  connectivity: {
    summary_available: true,
    configured_pbx_count: 1,
    local_resource_summary_available: true,
    local_resource_count: 0,
    configuration_mutations_available: false,
    resource_mutations_available: false,
    selector_mutations_available: false,
    limit_mutations_available: false,
    failover_mutations_available: false,
  },
}

describe('operationalStatusSchema', () => {
  it('accepts the safe capability and aggregate contract', () => {
    expect(operationalStatusSchema.parse(payload)).toEqual(payload)
  })

  it('rejects raw Switch presence and parked-call fields', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        parking: { ...payload.parking, slots: { '101': { 'Call-ID': 'private-call-id' } } },
      }),
    ).toThrow()
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        presence: { ...payload.presence, subscriptions: [{ contact: 'sip:private@10.0.0.8' }] },
      }),
    ).toThrow()
  })

  it('rejects raw Webhook configuration and delivery fields', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        webhooks: {
          ...payload.webhooks,
          hooks: [{ id: 'raw-hook-id', uri: 'https://private.example.test' }],
        },
      }),
    ).toThrow()
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        webhooks: {
          ...payload.webhooks,
          attempts: [{ req_body: 'private', resp_body: 'private' }],
        },
      }),
    ).toThrow()
  })

  it('rejects raw messaging content and participant fields', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        messaging: {
          ...payload.messaging,
          messages: [
            {
              id: 'raw-message-id',
              body: 'private message body',
              from: '+15550000001',
              to: '+15550000002',
            },
          ],
        },
      }),
    ).toThrow()
  })

  it('rejects raw port request details and documents', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        number_porting: {
          ...payload.number_porting,
          requests: [
            {
              id: 'raw-port-request-id',
              bill: { account_number: 'private-account', pin: 'private-pin' },
              numbers: ['+15550000005'],
              uploads: ['bill.pdf'],
            },
          ],
        },
      }),
    ).toThrow()
  })

  it('rejects carrier configuration, inventory, and charge details', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        number_management: {
          ...payload.number_management,
          usable_carriers: ['local', 'private-provider'],
          available_numbers: ['+15550000006'],
          quotes: [{ amount: 10 }],
        },
      }),
    ).toThrow()
  })

  it('rejects raw Connectivity, Resource, credential, and limit fields', () => {
    expect(() =>
      operationalStatusSchema.parse({
        ...payload,
        connectivity: {
          ...payload.connectivity,
          documents: [{ id: 'raw-connectivity-id', servers: [{ password: 'private' }] }],
          resources: [{ id: 'raw-resource-id', gateways: [] }],
          limits: { allow_postpay: true, max_postpay_amount: 100 },
        },
      }),
    ).toThrow()
  })
})
