import { describe, expect, it } from 'vitest'
import {
  callflowActionCatalog,
  callflowInlineModuleNeedsEditorCatalog,
  callflowNodeLabel,
  findCallflowAction,
  findCallflowActionById,
} from './callflowActionCatalog'

describe('callflowActionCatalog', () => {
  it('mirrors the installed Switch palette taxonomy and action membership', () => {
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

    expect(callflowActionCatalog[1]?.actions.map((action) => action.label)).toEqual([
      'Device',
      'Distinctive Ring',
      'Callflow',
      'Page Group',
      'Set CAV',
      'Missed Call Alert',
      'Manual Presence',
      'TTS',
      'Sleep',
      'Language',
      'Group Pickup',
      'Receive Fax',
      'Pivot',
      'Collect DTMF',
      'DISA',
      'Response',
      'Conference Service',
      'Check Voicemail',
      'Fax Boxes',
      'Global Carrier',
      'Account Carrier',
      'Directory',
      'Webhook',
    ])
  })

  it('resolves shared-module action variants to their Switch labels', () => {
    expect(findCallflowAction('record_call', 'start')?.label).toBe('Start Call Recording')
    expect(findCallflowAction('record_call', 'stop')?.label).toBe('Stop Call Recording')
    expect(findCallflowAction('call_forward', 'activate')?.label).toBe('Enable call forwarding')
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

  it('keeps supported current-schema actions resolvable without adding them to the palette', () => {
    expect(callflowActionCatalog[1]?.actions).toHaveLength(23)
    expect(callflowActionCatalog.flatMap((category) => category.actions)).not.toContainEqual(
      expect.objectContaining({ module: 'branch_bnumber' }),
    )
    expect(findCallflowAction('branch_bnumber')?.label).toBe('Branch Bnumber')
    expect(findCallflowAction('branch_variable')?.label).toBe('Branch by Call Priority')
  })

  it('identifies inline actions that require synchronized editor choices', () => {
    expect(callflowInlineModuleNeedsEditorCatalog('group_pickup')).toBe(true)
    expect(callflowInlineModuleNeedsEditorCatalog('page_group')).toBe(true)
    expect(callflowInlineModuleNeedsEditorCatalog('missed_call_alert')).toBe(true)
    expect(callflowInlineModuleNeedsEditorCatalog('manual_presence')).toBe(false)
    expect(callflowInlineModuleNeedsEditorCatalog('device')).toBe(false)
  })

  it('keeps audited high-risk actions gated and exposes resource-free actions', () => {
    expect(findCallflowAction('pivot')).toMatchObject({ status: 'restricted' })
    expect(findCallflowAction('disa')).toMatchObject({ status: 'restricted' })
    expect(findCallflowAction('dynamic_cid')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('anti-spoofing'),
    })
    expect(findCallflowAction('offnet')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('toll-fraud'),
    })
    expect(findCallflowAction('resources')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('public account mapping'),
    })
    expect(findCallflowAction('webhook')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('signed payloads'),
    })
    expect(findCallflowAction('conference', 'service')).toMatchObject({
      label: 'Conference Service',
      status: 'guided',
      preset: { service_mode: true },
    })
    expect(findCallflowAction('voicemail', 'check')).toMatchObject({
      label: 'Check Voicemail',
      status: 'guided',
      preset: { action: 'check' },
    })
  })

  it('exposes Page Group as a guided device action', () => {
    expect(findCallflowAction('page_group')).toMatchObject({
      status: 'guided',
      description: expect.stringContaining('20 synchronized devices'),
    })
    expect(callflowInlineModuleNeedsEditorCatalog('page_group')).toBe(true)
  })

  it('exposes Ring Group as a guided bounded device action', () => {
    expect(findCallflowAction('ring_group')).toMatchObject({
      status: 'guided',
      description: expect.stringContaining('20 synchronized devices'),
    })
    expect(callflowInlineModuleNeedsEditorCatalog('ring_group')).toBe(true)
  })
})
