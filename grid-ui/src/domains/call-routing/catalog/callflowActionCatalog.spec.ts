import { describe, expect, it } from 'vitest'
import {
  callflowActionCatalog,
  callflowActionDestinationType,
  callflowDestinationModule,
  callflowInlineModuleNeedsEditorCatalog,
  callflowNodeLabel,
  findCallflowAction,
  findCallflowActionById,
  isGuidedInlineCallflowModule,
  searchableCallflowActions,
} from './callflowActionCatalog'

describe('callflowActionCatalog', () => {
  it('maps public destination types back to their guided canvas modules', () => {
    expect(callflowDestinationModule('extension')).toBe('user')
    expect(callflowDestinationModule('media')).toBe('play')
    expect(callflowDestinationModule('queue')).toBe('acdc_member')
    expect(callflowDestinationModule('menu')).toBe('menu')
  })

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
    expect(findCallflowAction('pivot')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('allowlisted egress'),
    })
    expect(findCallflowAction('disa')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('mandatory PIN'),
    })
    expect(findCallflowAction('dynamic_cid')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('anti-spoofing'),
    })
    expect(findCallflowAction('offnet')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('toll-fraud'),
    })
    expect(findCallflowAction('offnet')?.description).toContain('final-destination')
    expect(findCallflowAction('resources')).toMatchObject({
      status: 'restricted',
      description: expect.stringContaining('reseller entitlement'),
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

  it('exposes audited Hotdesk operations as resource-free guided actions', () => {
    expect(findCallflowAction('hotdesk', 'toggle')).toMatchObject({
      label: 'Hot Desk toggle',
      status: 'guided',
      preset: { action: 'toggle' },
      description: expect.stringContaining('current device'),
    })
    expect(callflowInlineModuleNeedsEditorCatalog('hotdesk')).toBe(false)
  })

  it('exposes audited Do Not Disturb operations as resource-free guided actions', () => {
    expect(findCallflowAction('do_not_disturb', 'toggle')).toMatchObject({
      label: 'Toggle Do Not Disturb',
      status: 'guided',
      preset: { action: 'toggle' },
      description: expect.stringContaining('authenticated caller'),
    })
    expect(callflowInlineModuleNeedsEditorCatalog('do_not_disturb')).toBe(false)
  })

  it('keeps audited Call Forwarding actions capability-gated', () => {
    for (const action of ['activate', 'deactivate', 'update']) {
      expect(findCallflowAction('call_forward', action)).toMatchObject({
        status: 'restricted',
        description: expect.stringContaining('arbitrary destination'),
      })
    }
    expect(isGuidedInlineCallflowModule('call_forward', 'activate')).toBe(false)
  })

  it('keeps audited ACDC Agent actions search-only and capability-gated', () => {
    const variants = searchableCallflowActions.filter((action) => action.module === 'acdc_agent')

    expect(variants.map((action) => action.action)).toEqual(['login', 'logout', 'paused', 'resume'])
    expect(variants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          status: 'restricted',
          description: expect.stringContaining('authenticated and audited Queue Agent'),
        }),
      ]),
    )
    expect(callflowActionCatalog.flatMap((category) => category.actions)).not.toContainEqual(
      expect.objectContaining({ module: 'acdc_agent' }),
    )
    expect(isGuidedInlineCallflowModule('acdc_agent', 'login')).toBe(false)
  })

  it('exposes audited ACDC Queue operations as search-only guided actions', () => {
    const variants = searchableCallflowActions.filter((action) => action.module === 'acdc_queue')

    expect(variants.map((action) => action.action)).toEqual(['login', 'logout'])
    expect(variants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          status: 'guided',
          description: expect.stringContaining('authenticated caller owner'),
        }),
      ]),
    )
    expect(callflowActionCatalog.flatMap((category) => category.actions)).not.toContainEqual(
      expect.objectContaining({ module: 'acdc_queue' }),
    )
    expect(isGuidedInlineCallflowModule('acdc_queue', 'login')).toBe(true)
    expect(callflowInlineModuleNeedsEditorCatalog('acdc_queue')).toBe(true)
  })

  it('keeps audited Eavesdrop actions search-only and capability-gated', () => {
    const actions = searchableCallflowActions.filter((action) =>
      ['eavesdrop', 'eavesdrop_feature'].includes(action.module),
    )

    expect(actions).toEqual([
      expect.objectContaining({
        module: 'eavesdrop',
        label: 'Eavesdrop configured target',
        status: 'restricted',
        description: expect.stringContaining('privacy controls'),
      }),
      expect.objectContaining({
        module: 'eavesdrop_feature',
        label: 'Eavesdrop by extension',
        status: 'restricted',
        description: expect.stringContaining('supervisor authorization'),
      }),
    ])
    expect(callflowActionCatalog.flatMap((category) => category.actions)).not.toContainEqual(
      expect.objectContaining({ module: 'eavesdrop' }),
    )
    expect(isGuidedInlineCallflowModule('eavesdrop')).toBe(false)
    expect(isGuidedInlineCallflowModule('eavesdrop_feature')).toBe(false)
  })

  it('classifies every installed palette action with an implemented boundary', () => {
    const actions = callflowActionCatalog.flatMap((category) => category.actions)
    const guided = actions.filter((action) => action.status === 'guided')
    const restricted = actions.filter((action) => action.status === 'restricted')

    expect(actions).toHaveLength(49)
    expect(guided).toHaveLength(40)
    expect(restricted.map((action) => action.id).sort()).toEqual([
      'call_forward[action=activate]',
      'call_forward[action=deactivate]',
      'call_forward[action=update]',
      'disa',
      'dynamic_cid',
      'offnet',
      'pivot',
      'resources',
      'webhook',
    ])
    expect(actions.filter((action) => action.status === 'planned')).toEqual([])

    for (const action of guided) {
      expect(
        callflowActionDestinationType(action.module) !== null ||
          isGuidedInlineCallflowModule(action.module, action.action),
        `${action.id} needs a public destination or inline mutation contract`,
      ).toBe(true)
    }
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
