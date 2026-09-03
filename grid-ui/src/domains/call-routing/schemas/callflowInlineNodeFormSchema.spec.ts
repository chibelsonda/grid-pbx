import { describe, expect, it } from 'vitest'
import {
  callflowDtmfDigits,
  createCallflowInlineNodeFormSchema,
} from './callflowInlineNodeFormSchema'

describe('callflow inline node form schema', () => {
  it('validates current Switch defaults for every supported inline module', () => {
    const fixtures = {
      sleep: { duration: 0, unit: 's', skip_module: false },
      tts: {
        text: 'Welcome to GridPBX.',
        voice: 'female',
        language: null,
        engine: null,
        endless_playback: false,
        terminators: [...callflowDtmfDigits],
        skip_module: false,
      },
      collect_dtmf: {
        collection_name: null,
        interdigit_timeout: 2000,
        max_digits: 1,
        terminators: ['#'],
        timeout: 5000,
        skip_module: false,
      },
      record_call: {
        action: 'start',
        format: null,
        label: null,
        record_min_sec: null,
        record_on_answer: false,
        record_on_bridge: false,
        record_sample_rate: null,
        should_follow_transfer: true,
        time_limit: 3600,
        skip_module: false,
      },
      record_caller: { format: null, time_limit: 3600, skip_module: false },
      send_dtmf: { digits: '1234#', duration_ms: 2000, skip_module: false },
      flush_dtmf: { collection_name: 'default', skip_module: false },
      dead_air: { skip_module: false },
      language: { language: 'en-US', skip_module: false },
      response: { code: 486, message: null, skip_module: false },
      hangup: { skip_module: false },
      set_variable: {
        variable: 'call_priority',
        value: '6',
        channel: 'a',
        skip_module: false,
      },
      branch_variable: {
        variable: 'call_priority',
        scope: 'custom_channel_vars',
        skip_module: false,
      },
      missed_call_alert: {
        recipients: [
          { type: 'email', id: 'alerts@example.com' },
          { type: 'user', id: '1c79efef-613a-49d0-8acf-dd6a4fe9ee7f' },
        ],
        skip_module: false,
      },
      set_cid: { caller_id_name: 'Support', caller_id_number: '+15551234567', skip_module: false },
      prepend_cid: {
        action: 'prepend',
        apply_to: 'original',
        caller_id_name_prefix: 'Sales ',
        caller_id_number_prefix: '9',
        skip_module: false,
      },
      set_alert_info: { alert_info: 'Bellcore-dr2', skip_module: false },
      check_cid: {
        regex: '^\\+1555',
        use_absolute_mode: false,
        external_caller_id_name: null,
        external_caller_id_number: null,
        user_id: null,
        skip_module: false,
      },
      cidlistmatch: {
        caller_id_list_id: 'dded4533-55cb-4b40-acb6-b02248532c09',
        skip_module: false,
      },
      temporal_route: { action: 'disable', rules: [], skip_module: false },
      ring_group_toggle: {
        action: 'login',
        callflow_id: '73574264-0951-41c4-a2ec-a0ab7e027c1c',
        skip_module: false,
      },
      acdc_queue: {
        action: 'logout',
        queue_id: '11111111-1111-4111-8111-111111111111',
        skip_module: false,
      },
      hotdesk: { action: 'logout', skip_module: false },
      do_not_disturb: { action: 'toggle', skip_module: false },
      call_forward: { action: 'update', skip_module: false },
      pivot: {
        endpoint_id: 'customer-ivr',
        method: 'post',
        req_format: 'twiml',
        skip_module: false,
      },
      webhook: {
        endpoint_id: '11111111-1111-4111-8111-111111111111',
        http_verb: 'post',
        retries: 2,
        custom_data: { source: 'support', priority: 4 },
        skip_module: false,
      },
      disa: {
        access_policy_id: '11111111-1111-4111-8111-111111111111',
        skip_module: false,
      },
      page_group: {
        audio: 'one-way',
        endpoints: [
          {
            device_id: '11111111-1111-4111-8111-111111111111',
            delay: 0,
            timeout: 20,
          },
        ],
        skip_module: false,
      },
      ring_group: {
        strategy: 'simultaneous',
        endpoints: [
          {
            device_id: '11111111-1111-4111-8111-111111111111',
            delay: 0,
            timeout: 20,
          },
        ],
        repeats: 1,
        ignore_forward: true,
        fail_on_single_reject: false,
        ringback_media_id: null,
        ringtone_internal: null,
        ringtone_external: null,
        skip_module: false,
      },
      conference: { service_mode: true, skip_module: false },
      voicemail: { action: 'check', skip_module: false },
    } as const

    for (const [module, data] of Object.entries(fixtures)) {
      expect(
        createCallflowInlineNodeFormSchema(module as keyof typeof fixtures, ['_'], true).safeParse({
          branch: '_',
          data,
        }).success,
      ).toBe(true)
    }
  })

  it('rejects unsafe recording storage fields and schema bounds', () => {
    const schema = createCallflowInlineNodeFormSchema('record_call', ['_'], true)
    const result = schema.safeParse({
      branch: '_',
      data: {
        action: 'start',
        format: 'mp3',
        label: null,
        record_min_sec: null,
        record_on_answer: false,
        record_on_bridge: false,
        record_sample_rate: null,
        should_follow_transfer: true,
        time_limit: 4,
        skip_module: false,
        url: 'https://attacker.invalid/upload',
      },
    })

    expect(result.success).toBe(false)
  })

  it('requires TTS text and an available branch when creating', () => {
    const result = createCallflowInlineNodeFormSchema('tts', ['_'], true).safeParse({
      branch: 'timeout',
      data: {
        text: '',
        voice: 'female',
        language: null,
        engine: null,
        endless_playback: false,
        terminators: ['#'],
        skip_module: false,
      },
    })

    expect(result.success).toBe(false)
    expect(result.success ? [] : result.error.issues.map(({ path }) => path.join('.'))).toEqual(
      expect.arrayContaining(['branch', 'data.text']),
    )
  })

  it('accepts a branchless edit and the newer DTMF terminator array', () => {
    const schema = createCallflowInlineNodeFormSchema('collect_dtmf', [], false)

    expect(
      schema.safeParse({
        branch: null,
        data: {
          collection_name: 'account_code',
          interdigit_timeout: 1500,
          max_digits: 8,
          terminators: ['#', '*'],
          timeout: 5000,
          skip_module: false,
        },
      }).success,
    ).toBe(true)
  })

  it('rejects invalid language and missed-call recipient identifiers', () => {
    const language = createCallflowInlineNodeFormSchema('language', ['_'], true).safeParse({
      branch: '_',
      data: { language: 'english', skip_module: false },
    })
    const alert = createCallflowInlineNodeFormSchema('missed_call_alert', ['_'], true).safeParse({
      branch: '_',
      data: { recipients: [{ type: 'user', id: 'switch-user-secret' }], skip_module: false },
    })

    expect(language.success).toBe(false)
    expect(alert.success).toBe(false)
  })

  it('bounds Response to the Switch code range and rejects server-owned media', () => {
    const schema = createCallflowInlineNodeFormSchema('response', ['_'], true)

    expect(
      schema.safeParse({
        branch: '_',
        data: { code: 486, message: 'Busy here', skip_module: false },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        branch: '_',
        data: { code: 200, message: 'OK', skip_module: false },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        branch: '_',
        data: { code: 99, message: null, skip_module: false },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        branch: '_',
        data: { code: 486, message: null, media: 'private-media-id', skip_module: false },
      }).success,
    ).toBe(false)
  })

  it('keeps Hangup limited to the current Switch schema', () => {
    const schema = createCallflowInlineNodeFormSchema('hangup', ['_'], true)

    expect(schema.safeParse({ branch: '_', data: { skip_module: false } }).success).toBe(true)
    expect(
      schema.safeParse({
        branch: '_',
        data: { cause: 'NORMAL_CLEARING', skip_module: false },
      }).success,
    ).toBe(false)
  })

  it('allows only Switch call priority with a bounded value', () => {
    const schema = createCallflowInlineNodeFormSchema('set_variable', ['_'], true)
    const valid = {
      branch: '_',
      data: { variable: 'call_priority', value: '255', channel: 'both', skip_module: false },
    } as const

    expect(schema.safeParse(valid).success).toBe(true)
    expect(
      schema.safeParse({
        ...valid,
        data: { ...valid.data, variable: 'sip_h_X-Unsafe' },
      }).success,
    ).toBe(false)
    expect(schema.safeParse({ ...valid, data: { ...valid.data, value: '256' } }).success).toBe(
      false,
    )
  })

  it('allows only the Switch Call Priority branch scope', () => {
    const schema = createCallflowInlineNodeFormSchema('branch_variable', ['42'], true)
    const valid = {
      branch: '42',
      data: {
        variable: 'call_priority',
        scope: 'custom_channel_vars',
        skip_module: false,
      },
    } as const

    expect(schema.safeParse(valid).success).toBe(true)
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, variable: 'x_secret' } }).success,
    ).toBe(false)
    expect(schema.safeParse({ ...valid, data: { ...valid.data, scope: 'account' } }).success).toBe(
      false,
    )
    expect(schema.safeParse({ ...valid, branch: '256' }).success).toBe(false)
  })

  it('validates Branch BNumber modes, safe hunt filters, and exact capture branches', () => {
    const schema = createCallflowInlineNodeFormSchema('branch_bnumber', ['_'], true, true, ['2000'])
    const branchMode = {
      branch: '1000',
      data: { hunt: false, hunt_allow: null, hunt_deny: null, skip_module: false },
    }

    expect(schema.safeParse(branchMode).success).toBe(true)
    expect(schema.safeParse({ ...branchMode, branch: '2000' }).success).toBe(false)
    expect(schema.safeParse({ ...branchMode, branch: 'sales' }).success).toBe(false)
    expect(
      schema.safeParse({
        branch: '_',
        data: {
          hunt: true,
          hunt_allow: '^1\\d{3}$',
          hunt_deny: '^1900$',
          skip_module: false,
        },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        branch: '_',
        data: { hunt: true, hunt_allow: '(?R)', hunt_deny: null, skip_module: false },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        branch: '_',
        data: { hunt: false, hunt_allow: '^1', hunt_deny: null, skip_module: false },
      }).success,
    ).toBe(false)
  })

  it('validates Set CAV rows, duplicate names, and control characters', () => {
    const schema = createCallflowInlineNodeFormSchema('set_variables', ['_'], true)
    const valid = {
      branch: '_',
      data: {
        custom_application_variables: [
          { key: 'account_code', value: 'support' },
          { key: 'priority-1', value: '42' },
        ],
        export: true,
        skip_module: false,
      },
    }

    expect(schema.safeParse(valid).success).toBe(true)
    expect(
      schema.safeParse({
        ...valid,
        data: {
          ...valid.data,
          custom_application_variables: [
            { key: 'account_code', value: 'support' },
            { key: 'account_code', value: 'sales' },
          ],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...valid,
        data: {
          ...valid.data,
          custom_application_variables: [{ key: 'bad key', value: 'support' }],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...valid,
        data: {
          ...valid.data,
          custom_application_variables: [{ key: 'valid', value: 'line\nbreak' }],
        },
      }).success,
    ).toBe(false)
  })

  it('validates Manual Presence identifiers and current statuses', () => {
    const schema = createCallflowInlineNodeFormSchema('manual_presence', ['_'], true)

    expect(
      schema.safeParse({
        branch: '_',
        data: { presence_id: '1001@example.com', status: 'busy', skip_module: false },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        branch: '_',
        data: { presence_id: 'bad id', status: 'busy', skip_module: false },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        branch: '_',
        data: { presence_id: '1001', status: 'unknown', skip_module: false },
      }).success,
    ).toBe(false)
  })

  it('accepts only installed resource-free Do Not Disturb actions', () => {
    const schema = createCallflowInlineNodeFormSchema('do_not_disturb', ['_'], true)
    const base = { branch: '_', data: { action: 'toggle', skip_module: false } }

    for (const action of ['activate', 'deactivate', 'toggle']) {
      expect(schema.safeParse({ ...base, data: { ...base.data, action } }).success).toBe(true)
    }
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          ringback_media_id: '22222222-2222-4222-8222-222222222222',
          ringtone_internal: 'internal-ring',
          ringtone_external: 'external-ring',
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...base, data: { action: 'enable', skip_module: false } }).success,
    ).toBe(false)
    expect(schema.safeParse({ ...base, data: { ...base.data, id: 'raw-user-id' } }).success).toBe(
      false,
    )
  })

  it('accepts only the installed resource-free Call Forwarding actions', () => {
    const schema = createCallflowInlineNodeFormSchema('call_forward', ['_'], true)
    const base = { branch: '_', data: { action: 'update', skip_module: false } }

    for (const action of ['activate', 'deactivate', 'update']) {
      expect(schema.safeParse({ ...base, data: { ...base.data, action } }).success).toBe(true)
    }
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, number: '+15551234567' },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...base, data: { action: 'toggle', skip_module: false } }).success,
    ).toBe(false)
  })

  it('accepts only a public Pivot endpoint alias and supported protocol choices', () => {
    const schema = createCallflowInlineNodeFormSchema('pivot', ['_'], true)
    const valid = {
      branch: '_',
      data: {
        endpoint_id: 'customer-ivr',
        method: 'post',
        req_format: 'twiml',
        skip_module: false,
      },
    } as const

    expect(schema.safeParse(valid).success).toBe(true)
    expect(
      schema.safeParse({
        ...valid,
        data: { ...valid.data, endpoint_id: 'https://private.example/pivot' },
      }).success,
    ).toBe(false)
    expect(schema.safeParse({ ...valid, data: { ...valid.data, method: 'put' } }).success).toBe(
      false,
    )
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, voice_url: 'https://attacker.test' } })
        .success,
    ).toBe(false)
  })

  it('accepts Dynamic CID only with a public account phone-number UUID', () => {
    const schema = createCallflowInlineNodeFormSchema('dynamic_cid', ['_'], true)
    const valid = {
      branch: '_',
      data: {
        action: 'static',
        phone_number_id: '11111111-1111-4111-8111-111111111111',
        caller_id_name: 'Support',
        skip_module: false,
      },
    } as const

    expect(schema.safeParse(valid).success).toBe(true)
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, phone_number_id: '+15551234567' } })
        .success,
    ).toBe(false)
    expect(schema.safeParse({ ...valid, data: { ...valid.data, action: 'manual' } }).success).toBe(
      false,
    )
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, caller_id_number: '+15551234567' } })
        .success,
    ).toBe(false)
  })

  it('accepts only a public Webhook profile and bounded schema data', () => {
    const schema = createCallflowInlineNodeFormSchema('webhook', ['_'], true)
    const valid = {
      branch: '_',
      data: {
        endpoint_id: '11111111-1111-4111-8111-111111111111',
        http_verb: 'post',
        retries: 2,
        custom_data: { source: 'support', priority: 4 },
        skip_module: false,
      },
    } as const

    expect(schema.safeParse(valid).success).toBe(true)
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, endpoint_id: 'https://private.test' } })
        .success,
    ).toBe(false)
    expect(schema.safeParse({ ...valid, data: { ...valid.data, retries: 6 } }).success).toBe(false)
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, uri: 'https://attacker.test' } }).success,
    ).toBe(false)
  })

  it.each(['offnet', 'resources'] as const)(
    'accepts only a public %s routing profile UUID',
    (module) => {
      const schema = createCallflowInlineNodeFormSchema(module, ['_'], true)
      const valid = {
        branch: '_',
        data: {
          route_profile_id: '11111111-1111-4111-8111-111111111111',
          skip_module: false,
        },
      } as const

      expect(schema.safeParse(valid).success).toBe(true)
      expect(
        schema.safeParse({
          ...valid,
          data: { ...valid.data, route_profile_id: 'raw-switch-account-id' },
        }).success,
      ).toBe(false)
      expect(
        schema.safeParse({
          ...valid,
          data: { ...valid.data, hunt_account_id: 'raw-switch-account-id' },
        }).success,
      ).toBe(false)
    },
  )

  it('accepts only public Queue UUIDs for installed ACDC Queue actions', () => {
    const schema = createCallflowInlineNodeFormSchema('acdc_queue', ['_'], true)
    const base = {
      branch: '_',
      data: {
        action: 'login',
        queue_id: '11111111-1111-4111-8111-111111111111',
        skip_module: false,
      },
    }

    expect(schema.safeParse(base).success).toBe(true)
    expect(schema.safeParse({ ...base, data: { ...base.data, action: 'toggle' } }).success).toBe(
      false,
    )
    expect(
      schema.safeParse({ ...base, data: { ...base.data, queue_id: 'raw-switch-queue' } }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...base, data: { ...base.data, id: 'raw-switch-queue' } }).success,
    ).toBe(false)
  })

  it('requires one public Group Pickup target', () => {
    const schema = createCallflowInlineNodeFormSchema('group_pickup', ['_'], true)

    expect(
      schema.safeParse({
        branch: '_',
        data: {
          target_type: 'group',
          target_id: '11111111-1111-4111-8111-111111111111',
          skip_module: false,
        },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        branch: '_',
        data: { target_type: 'group', target_id: 'switch-group-secret', skip_module: false },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        branch: '_',
        data: {
          target_type: 'queue',
          target_id: '11111111-1111-4111-8111-111111111111',
          skip_module: false,
        },
      }).success,
    ).toBe(false)
  })

  it('requires a public Receive Fax owner and a schema-supported T.38 mode', () => {
    const schema = createCallflowInlineNodeFormSchema('receive_fax', ['_'], true)
    const base = {
      branch: '_',
      data: {
        owner_id: '11111111-1111-4111-8111-111111111111',
        fax_option: 'auto',
        skip_module: false,
      },
    }

    expect(schema.safeParse(base).success).toBe(true)
    expect(schema.safeParse({ ...base, data: { ...base.data, fax_option: true } }).success).toBe(
      true,
    )
    expect(
      schema.safeParse({ ...base, data: { ...base.data, owner_id: 'switch-user-secret' } }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...base, data: { ...base.data, fax_option: 'unsupported' } }).success,
    ).toBe(false)
  })

  it('requires bounded public Page Group endpoints and timing', () => {
    const schema = createCallflowInlineNodeFormSchema('page_group', ['_'], true)
    const deviceId = '11111111-1111-4111-8111-111111111111'
    const base = {
      branch: '_',
      data: {
        audio: 'one-way',
        endpoints: [{ device_id: deviceId, delay: 0, timeout: 20 }],
        skip_module: false,
      },
    } as const

    expect(schema.safeParse(base).success).toBe(true)
    expect(schema.safeParse({ ...base, data: { ...base.data, audio: 'two-way' } }).success).toBe(
      true,
    )
    expect(schema.safeParse({ ...base, data: { ...base.data, endpoints: [] } }).success).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [
            { device_id: deviceId, delay: 0, timeout: 20 },
            { device_id: deviceId, delay: 1, timeout: 30 },
          ],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [{ extension_id: 'switch-user', delay: 0, timeout: 20 }],
        },
      }).success,
    ).toBe(false)
  })

  it('requires bounded public Ring Group endpoints and attempt timing', () => {
    const schema = createCallflowInlineNodeFormSchema('ring_group', ['_'], true)
    const deviceId = '11111111-1111-4111-8111-111111111111'
    const base = {
      branch: '_',
      data: {
        strategy: 'simultaneous',
        endpoints: [{ device_id: deviceId, delay: 5, timeout: 20 }],
        repeats: 2,
        ignore_forward: true,
        fail_on_single_reject: false,
        ringback_media_id: null,
        ringtone_internal: null,
        ringtone_external: null,
        skip_module: false,
      },
    } as const

    expect(schema.safeParse(base).success).toBe(true)
    expect(
      schema.safeParse({ ...base, data: { ...base.data, ignore_forward: 'true' } }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...base, data: { ...base.data, fail_on_single_reject: 1 } }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [
            {
              device_id: deviceId,
              extension_id: '22222222-2222-4222-8222-222222222222',
              delay: 5,
              timeout: 20,
            },
          ],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({ ...base, data: { ...base.data, ringback_media_id: 'raw-media' } }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, ringtone_internal: 'safe\r\nX-Injected: true' },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, ringtone_external: 'x'.repeat(257) },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [
            {
              extension_id: '22222222-2222-4222-8222-222222222222',
              delay: 5,
              timeout: 20,
            },
          ],
        },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [
            {
              group_id: '33333333-3333-4333-8333-333333333333',
              delay: 5,
              timeout: 20,
            },
          ],
        },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          strategy: 'weighted_random',
          endpoints: [{ device_id: deviceId, delay: 0, timeout: 20, weight: 75 }],
        },
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({ ...base, data: { ...base.data, strategy: 'weighted_random' } }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [{ device_id: deviceId, delay: 5, timeout: 20, weight: 75 }],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          strategy: 'single',
          endpoints: [{ device_id: deviceId, delay: 1, timeout: 20 }],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          strategy: 'single',
          endpoints: [
            { device_id: deviceId, delay: 0, timeout: 50 },
            {
              device_id: '22222222-2222-4222-8222-222222222222',
              delay: 0,
              timeout: 50,
            },
            {
              device_id: '33333333-3333-4333-8333-333333333333',
              delay: 0,
              timeout: 50,
            },
          ],
        },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: {
          ...base.data,
          endpoints: [{ device_id: 'switch-device-id', delay: 0, timeout: 20 }],
        },
      }).success,
    ).toBe(false)
  })

  it('keeps Conference Service resource-free', () => {
    const schema = createCallflowInlineNodeFormSchema('conference', ['_'], true)
    const base = {
      branch: '_',
      data: { service_mode: true, skip_module: false },
    } as const

    expect(schema.safeParse(base).success).toBe(true)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, id: 'raw-conference-id' },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, service_mode: false },
      }).success,
    ).toBe(false)
  })

  it('keeps Check Voicemail resource-free and disables auto-login flags', () => {
    const schema = createCallflowInlineNodeFormSchema('voicemail', ['_'], true)
    const base = {
      branch: '_',
      data: { action: 'check', skip_module: false },
    } as const

    expect(schema.safeParse(base).success).toBe(true)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, id: 'raw-voicemail-id' },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, action: 'compose' },
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        ...base,
        data: { ...base.data, single_mailbox_login: true },
      }).success,
    ).toBe(false)
  })

  it('rejects header injection in Alert-Info', () => {
    const result = createCallflowInlineNodeFormSchema('set_alert_info', ['_'], true).safeParse({
      branch: '_',
      data: { alert_info: 'Bellcore-dr2\r\nX-Injected: yes', skip_module: false },
    })

    expect(result.success).toBe(false)
  })

  it('rejects unsafe Check CID patterns and partial identity overrides', () => {
    const schema = createCallflowInlineNodeFormSchema('check_cid', ['_'], true)
    const base = {
      branch: '_',
      data: {
        regex: '^\\+1555',
        use_absolute_mode: false,
        external_caller_id_name: null,
        external_caller_id_number: null,
        user_id: null,
        skip_module: false,
      },
    } as const

    expect(schema.safeParse({ ...base, data: { ...base.data, regex: '(?R)' } }).success).toBe(false)
    const partial = schema.safeParse({
      ...base,
      data: { ...base.data, external_caller_id_name: 'Support' },
    })
    expect(partial.success).toBe(false)
    expect(partial.success ? [] : partial.error.issues.map(({ path }) => path.join('.'))).toEqual(
      expect.arrayContaining(['data.external_caller_id_number', 'data.user_id']),
    )
  })

  it('requires a public Caller-ID List UUID', () => {
    const schema = createCallflowInlineNodeFormSchema('cidlistmatch', ['_'], true)

    expect(
      schema.safeParse({
        branch: '_',
        data: { caller_id_list_id: 'switch-list-secret', skip_module: false },
      }).success,
    ).toBe(false)
  })
})
