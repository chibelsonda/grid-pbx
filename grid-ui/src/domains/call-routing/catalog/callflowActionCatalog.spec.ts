import { describe, expect, it } from 'vitest'
import {
  callflowActionCatalog,
  callflowNodeLabel,
  findCallflowAction,
  findCallflowActionById,
} from './callflowActionCatalog'

describe('callflowActionCatalog', () => {
  it('mirrors the installed Switch palette taxonomy before schema extensions', () => {
    expect(callflowActionCatalog.slice(0, 9).map((category) => category.label)).toEqual([
      'Basic',
      'Advanced',
      'Time of Day',
      'Ring Group Toggle',
      'Hotdesking',
      'Do Not Disturb',
      'Caller-ID',
      'Call Recording',
      'Call Forwarding',
    ])

    expect(callflowActionCatalog[0]?.actions.map((action) => action.label)).toEqual([
      'Media',
      'Ring Group',
      'Conference',
      'User',
      'Voicemail',
      'Menu',
    ])
  })

  it('resolves shared-module action variants to their Switch labels', () => {
    expect(findCallflowAction('record_call', 'start')?.label).toBe('Start Call Recording')
    expect(findCallflowAction('record_call', 'stop')?.label).toBe('Stop Call Recording')
    expect(findCallflowAction('call_forward', 'activate')?.label).toBe(
      'Enable call forwarding',
    )
    expect(findCallflowActionById('record_call[action=stop]')?.preset).toEqual({ action: 'stop' })
  })

  it('uses action-aware labels for persisted nodes', () => {
    expect(
      callflowNodeLabel({
        module: 'record_call',
        target: null,
        reference_status: 'not_applicable',
        settings: { action: 'start' },
      }),
    ).toBe('Start Call Recording')
    expect(
      callflowNodeLabel({
        module: 'tts',
        target: null,
        reference_status: 'not_applicable',
        settings: null,
      }),
    ).toBe('TTS')
  })
})
