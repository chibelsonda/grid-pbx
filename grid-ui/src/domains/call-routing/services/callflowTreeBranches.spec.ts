import { describe, expect, it } from 'vitest'
import type { CallflowNode } from '../types/callRouting'
import {
  availableCallflowBranches,
  callflowDropCapability,
  callflowNodeDropDecision,
  callflowPalettePlacement,
  canAddCallflowChild,
  orderedCallflowChildren,
  supportsCapturedNumberBranches,
} from './callflowTreeBranches'

describe('callflowNodeDropDecision', () => {
  const responseAction = {
    id: 'response',
    module: 'response',
    label: 'Response',
    description: 'Return a SIP response.',
    status: 'guided' as const,
  }

  it('allows Response to be dropped on an empty Set CAV continuation', () => {
    expect(
      callflowNodeDropDecision({
        node: {
          module: 'set_variables',
          target: null,
          reference_status: 'not_applicable',
          children: {},
        },
        path: ['3', '_'],
        editable: true,
        moving: false,
        dragSourcePath: null,
        paletteAction: responseAction,
      }),
    ).toEqual({ state: 'allowed', effect: 'copy', reason: null })
  })

  it('allows Response on occupied Set CAV through an explicit replacement placement', () => {
    const node: CallflowNode = {
      module: 'set_variables',
      target: null,
      reference_status: 'not_applicable',
      children: {
        _: {
          module: 'device',
          target: null,
          reference_status: 'resolved',
          children: {},
        },
      },
    }

    expect(
      callflowNodeDropDecision({
        node,
        path: ['3', '_'],
        editable: true,
        moving: false,
        dragSourcePath: null,
        paletteAction: responseAction,
      }),
    ).toEqual({ state: 'allowed', effect: 'copy', reason: null })
    expect(callflowPalettePlacement(node, responseAction)).toBe('replace')
  })

  it('inserts a non-terminal inline action before an occupied continuation', () => {
    const node: CallflowNode = {
      module: 'set_variables',
      target: null,
      reference_status: 'not_applicable',
      children: {
        _: { module: 'device', target: null, reference_status: 'resolved', children: {} },
      },
    }

    expect(
      callflowPalettePlacement(node, {
        id: 'tts',
        module: 'tts',
        label: 'TTS',
        description: 'Speak text.',
        status: 'guided',
      }),
    ).toBe('insert_before')
  })

  it('uses the API capability reason for terminal palette destinations', () => {
    const node: CallflowNode = {
      module: 'response',
      target: null,
      reference_status: 'not_applicable',
      drop_capability: {
        accepts_children: false,
        default_branch_available: false,
        branch_mode: 'terminal',
        reason: 'This Switch action is terminal and cannot accept another action.',
      },
      children: {},
    }

    expect(
      callflowNodeDropDecision({
        node,
        path: ['_'],
        editable: true,
        moving: false,
        dragSourcePath: null,
        paletteAction: {
          id: 'tts',
          module: 'tts',
          label: 'TTS',
          description: 'Speak text.',
          status: 'guided',
        },
      }),
    ).toEqual({
      state: 'disallowed',
      effect: null,
      reason: 'This Switch action is terminal and cannot accept another action.',
    })
  })

  it('prevents a subtree from being moved into itself before any API request', () => {
    const node: CallflowNode = {
      module: 'user',
      target: null,
      reference_status: 'resolved',
      children: {},
    }

    expect(
      callflowNodeDropDecision({
        node,
        path: ['1', '_'],
        editable: true,
        moving: false,
        dragSourcePath: ['1'],
        paletteAction: null,
      }),
    ).toEqual({
      state: 'disallowed',
      effect: null,
      reason: 'A callflow action cannot be moved into its own subtree.',
    })
  })

  it('falls back to current-schema terminal semantics before an API refresh', () => {
    expect(
      callflowDropCapability({
        module: 'hangup',
        target: null,
        reference_status: 'not_applicable',
        children: {},
      }),
    ).toMatchObject({
      accepts_children: false,
      branch_mode: 'terminal',
    })
  })
})

describe('availableCallflowBranches', () => {
  it('offers only empty Kazoo Menu branches at any tree depth', () => {
    const node: CallflowNode = {
      module: 'menu',
      target: null,
      reference_status: 'resolved',
      children: {
        '1': {
          module: 'user',
          target: null,
          reference_status: 'resolved',
          children: {},
        },
      },
    }

    const branches = availableCallflowBranches(node)

    expect(branches.map(({ value }) => value)).toEqual([
      '_',
      'timeout',
      '0',
      '2',
      '3',
      '4',
      '5',
      '6',
      '7',
      '8',
      '9',
      '*',
    ])
    expect(branches.map(({ value }) => value)).not.toContain('#')
  })

  it.each(['check_cid', 'cidlistmatch'])(
    'offers unoccupied caller-ID result branches for %s',
    (module) => {
      const node: CallflowNode = {
        module,
        target: null,
        reference_status: 'not_applicable',
        children: {
          match: {
            module: 'user',
            target: null,
            reference_status: 'not_applicable',
            children: {},
          },
        },
      }

      expect(availableCallflowBranches(node)).toEqual(
        expect.arrayContaining([
          expect.objectContaining({ value: '_', label: 'Next step' }),
          expect.objectContaining({ value: 'nomatch', label: 'Caller ID does not match' }),
        ]),
      )
      expect(availableCallflowBranches(node).map(({ value }) => value)).not.toContain('match')
    },
  )

  it('does not offer fixed result branches for absolute-mode caller ID checks', () => {
    const node: CallflowNode = {
      module: 'check_cid',
      target: null,
      reference_status: 'not_applicable',
      settings: { use_absolute_mode: true },
      children: {},
    }

    expect(availableCallflowBranches(node)).toEqual([])
  })

  it('offers canonical Call Priority branches only for supported Branch Variable nodes', () => {
    const supported: CallflowNode = {
      module: 'branch_variable',
      target: null,
      reference_status: 'not_applicable',
      settings: { supported_variable: true },
      children: {
        '42': {
          module: 'user',
          target: null,
          reference_status: 'not_applicable',
          children: {},
        },
      },
    }

    const branches = availableCallflowBranches(supported)
    expect(branches).toHaveLength(256)
    expect(branches).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ value: '_', label: 'Next step' }),
        expect.objectContaining({ value: '0', label: 'Priority 0' }),
        expect.objectContaining({ value: '255', label: 'Priority 255' }),
      ]),
    )
    expect(branches.map(({ value }) => value)).not.toContain('42')

    expect(
      availableCallflowBranches({
        ...supported,
        settings: { supported_variable: false },
      }),
    ).toEqual([])
  })

  it('allows custom captured-number children only while Branch BNumber hunt mode is off', () => {
    const branching: CallflowNode = {
      module: 'branch_bnumber',
      target: null,
      reference_status: 'not_applicable',
      settings: { hunt: false },
      children: {
        _: { module: 'hangup', target: null, reference_status: 'not_applicable', children: {} },
      },
    }
    const hunting: CallflowNode = { ...branching, settings: { hunt: true } }

    expect(supportsCapturedNumberBranches(branching)).toBe(true)
    expect(canAddCallflowChild(branching)).toBe(true)
    expect(supportsCapturedNumberBranches(hunting)).toBe(false)
    expect(canAddCallflowChild(hunting)).toBe(false)
  })
})

describe('orderedCallflowChildren', () => {
  it('matches Kazoo result-first ordering when persisted JSON puts the fallback first', () => {
    const child = (module: string): CallflowNode => ({
      module,
      target: null,
      reference_status: 'not_applicable',
      children: {},
    })
    const node: CallflowNode = {
      module: 'check_cid',
      target: null,
      reference_status: 'not_applicable',
      children: {
        _: child('missed_call_alert'),
        match: child('tts'),
        nomatch: child('language'),
      },
    }

    expect(orderedCallflowChildren(node).map(([key]) => key)).toEqual(['match', 'nomatch', '_'])
  })

  it('keeps the source order for modules without a defined Switch branch workflow', () => {
    const node: CallflowNode = {
      module: 'device',
      target: null,
      reference_status: 'not_applicable',
      children: {
        second: {
          module: 'voicemail',
          target: null,
          reference_status: 'not_applicable',
          children: {},
        },
        first: {
          module: 'user',
          target: null,
          reference_status: 'not_applicable',
          children: {},
        },
      },
    }

    expect(orderedCallflowChildren(node).map(([key]) => key)).toEqual(['second', 'first'])
  })
})
