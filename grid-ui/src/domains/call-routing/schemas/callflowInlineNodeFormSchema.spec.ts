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
      missed_call_alert: {
        recipients: [
          { type: 'email', id: 'alerts@example.com' },
          { type: 'user', id: '1c79efef-613a-49d0-8acf-dd6a4fe9ee7f' },
        ],
        skip_module: false,
      },
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
})
