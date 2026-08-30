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
      hotdesk: { action: 'logout', skip_module: false },
      do_not_disturb: { action: 'toggle', skip_module: false },
      call_forward: { action: 'update', skip_module: false },
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
    if (!result.success) {
      expect(result.error.issues.map(({ path }) => path.join('.'))).toEqual(
        expect.arrayContaining(['branch', 'data.text']),
      )
    }
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

  it('bounds Response to a final SIP error code and rejects server-owned media', () => {
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
        data: { code: 399, message: null, skip_module: false },
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

  it('allows only Kazoo call priority with a bounded value', () => {
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

  it('allows only the Kazoo Call Priority branch scope', () => {
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
    expect(
      schema.safeParse({ ...valid, data: { ...valid.data, scope: 'account' } }).success,
    ).toBe(false)
    expect(schema.safeParse({ ...valid, branch: '256' }).success).toBe(false)
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
    if (!partial.success) {
      expect(partial.error.issues.map(({ path }) => path.join('.'))).toEqual(
        expect.arrayContaining(['data.external_caller_id_number', 'data.user_id']),
      )
    }
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
