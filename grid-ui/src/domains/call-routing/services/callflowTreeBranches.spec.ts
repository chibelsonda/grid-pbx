import { describe, expect, it } from 'vitest'
import type { CallflowNode } from '../types/callRouting'
import {
  availableCallflowBranches,
  canAddCallflowChild,
  orderedCallflowChildren,
  supportsCapturedNumberBranches,
} from './callflowTreeBranches'

describe('availableCallflowBranches', () => {
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
