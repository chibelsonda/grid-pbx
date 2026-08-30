import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { accountApi } from '../api/accountApi'
import type { AccountCallRecording, AccountDetail, AccountRecordingSource } from '../types/account'
import { useAccountStore } from './accountStore'

vi.mock('../api/accountApi', () => ({
  accountApi: {
    list: vi.fn(),
    detail: vi.fn(),
    settingsOptions: vi.fn(),
    update: vi.fn(),
    refresh: vi.fn(),
    updateStatus: vi.fn(),
  },
}))

const recordingSource = (): AccountRecordingSource => ({
  any: {
    enabled: false,
    format: 'mp3',
    record_min_sec: null,
    record_on_answer: true,
    record_on_bridge: false,
    record_sample_rate: null,
    time_limit: null,
  },
  onnet: {
    enabled: false,
    format: 'mp3',
    record_min_sec: null,
    record_on_answer: true,
    record_on_bridge: false,
    record_sample_rate: null,
    time_limit: null,
  },
  offnet: {
    enabled: false,
    format: 'mp3',
    record_min_sec: null,
    record_on_answer: true,
    record_on_bridge: false,
    record_sample_rate: null,
    time_limit: null,
  },
})
const callRecording = (): AccountCallRecording => ({
  account: { any: recordingSource(), inbound: recordingSource(), outbound: recordingSource() },
  endpoint: { any: recordingSource(), inbound: recordingSource(), outbound: recordingSource() },
})

const detail: AccountDetail = {
  id: 'account-public-id',
  name: 'Grid Support',
  realm: 'support.example.test',
  timezone: 'Asia/Manila',
  enabled: true,
  organization: { id: 'organization-public-id', name: 'GridPBX' },
  resource_counts: {
    extensions: 3,
    devices: 4,
    phone_numbers: 2,
    callflows: 5,
    voicemail_boxes: 3,
    queues: 1,
    media: 6,
    recordings: 7,
  },
  configuration_boundaries: {
    identity_defaults: 'safe_fields_available',
    calling_defaults: 'safe_fields_available',
    advanced_routing: 'guided_rules_available',
    enable_disable: 'implemented_confirmed',
    billing_topup: 'provider_required',
  },
  configuration: {
    organization_name: 'Grid Corp',
    language: 'en-US',
    call_waiting_enabled: true,
    do_not_disturb_enabled: false,
    outbound_privacy: 'none',
    show_rate: false,
    ringtone_internal: null,
    ringtone_external: null,
    caller_id: {
      internal: { name: 'Support', number: '1000' },
      external: {
        name: 'Grid Support',
        phone_number_id: '10000000-0000-4000-8000-000000000001',
        number: '+15550001000',
        unresolved: false,
      },
      emergency: {
        name: 'Grid Emergency',
        phone_number_id: '10000000-0000-4000-8000-000000000002',
        number: '+15550001911',
        unresolved: false,
      },
    },
    call_restriction: { international: { action: 'deny' } },
    call_recording: callRecording(),
    dial_plan: {
      system: ['north_america'],
      rules: [
        { pattern: '^([2-9][0-9]{6})$', description: 'Local', prefix: '+1555', suffix: null },
      ],
    },
    formatters: [],
    preflow: { callflow_id: null, name: null, unresolved: false },
    metaflows: {
      binding_digit: '*',
      digit_timeout: 2000,
      listen_on: 'both',
      number_flow_count: 1,
      pattern_flow_count: 0,
      actions: [],
      locked_action_count: 0,
    },
  },
  options: {
    caller_id_numbers: [
      {
        id: '10000000-0000-4000-8000-000000000001',
        number: '+15550001000',
        display_name: 'Grid Support',
        e911_enabled: false,
      },
      {
        id: '10000000-0000-4000-8000-000000000002',
        number: '+15550001911',
        display_name: 'Grid Emergency',
        e911_enabled: true,
      },
    ],
  },
  projection: { status: 'synced', version: 1, last_synced_at: '2026-08-29T00:00:00Z' },
  permissions: { can_manage_settings: true },
}

describe('account store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads a safe account detail projection by public UUID', async () => {
    vi.mocked(accountApi.detail).mockResolvedValue(detail)
    vi.mocked(accountApi.settingsOptions).mockResolvedValue({
      restrictions: [{ key: 'international', label: 'International', emergency: false }],
      callflows: [],
      metaflow_resources: { media: [], callflows: [], devices: [], extensions: [] },
    })
    const store = useAccountStore()

    await store.loadDetail(detail.id)

    expect(accountApi.detail).toHaveBeenCalledWith(detail.id)
    expect(accountApi.settingsOptions).toHaveBeenCalledWith(detail.id)
    expect(store.detail).toEqual(detail)
    expect(store.detailError).toBeNull()
  })

  it('keeps the projected account available when live restriction options fail', async () => {
    vi.mocked(accountApi.detail).mockResolvedValue(detail)
    vi.mocked(accountApi.settingsOptions).mockRejectedValue(new Error('Switch unavailable'))
    const store = useAccountStore()

    await store.loadDetail(detail.id)

    expect(store.detail).toEqual(detail)
    expect(store.detailError).toBeNull()
    expect(store.settingsOptionsError).toContain('temporarily unavailable')
  })

  it('updates account settings and replaces the detail projection', async () => {
    vi.mocked(accountApi.update).mockResolvedValue({ ...detail, name: 'Grid Operations' })
    const store = useAccountStore()

    const saved = await store.updateSettings(detail.id, {
      name: 'Grid Operations',
      organization_name: 'Grid Corp',
      timezone: 'Asia/Manila',
      language: 'en-US',
      call_waiting_enabled: true,
      do_not_disturb_enabled: false,
      outbound_privacy: 'none',
      show_rate: false,
      ringtone_internal: null,
      ringtone_external: null,
      caller_id: {
        internal: { name: 'Support', number: '1000' },
        external: {
          name: 'Grid Support',
          phone_number_id: '10000000-0000-4000-8000-000000000001',
          preserve_number: false,
        },
        emergency: {
          name: 'Grid Emergency',
          phone_number_id: '10000000-0000-4000-8000-000000000002',
          preserve_number: false,
        },
      },
      call_restriction: { international: { action: 'deny' } },
      call_recording: callRecording(),
      dial_plan: detail.configuration.dial_plan,
      formatters: [],
      preflow: { callflow_id: null, preserve_callflow: false },
      metaflows: { binding_digit: '*', digit_timeout: 2000, listen_on: 'both', actions: [] },
    })

    expect(saved).toBe(true)
    expect(store.detail?.name).toBe('Grid Operations')
    expect(store.fieldErrors).toEqual({})
  })

  it('updates account status through the confirmed operational endpoint', async () => {
    vi.mocked(accountApi.updateStatus).mockResolvedValue({ ...detail, enabled: false })
    const store = useAccountStore()
    store.accounts = [
      {
        id: detail.id,
        name: detail.name,
        realm: detail.realm,
        timezone: detail.timezone,
        enabled: true,
        organization: detail.organization,
        organization_role: 'account_administrator',
        permissions: {
          can_manage_extensions: true,
          can_manage_devices: true,
          can_manage_voicemail: true,
          can_manage_call_routing: true,
          can_manage_media: true,
          can_sync_call_detail_records: true,
          can_view_services: true,
          can_manage_account_settings: true,
          can_onboard_descendants: true,
        },
      },
    ]

    const updated = await store.updateStatus(detail.id, false, detail.name)

    expect(updated).toBe(true)
    expect(accountApi.updateStatus).toHaveBeenCalledWith(detail.id, false, detail.name)
    expect(store.detail?.enabled).toBe(false)
    expect(store.accounts[0]?.enabled).toBe(false)
  })
})
