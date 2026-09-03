import {
  callflowPriorityBranchKeys,
  type CallflowDropCapability,
  callflowInlineModules,
  type CallflowNode,
  type CallflowNodePlacement,
  type CallflowTreeBranchKey,
} from '../types/callRouting'
import { findCallflowAction, type CallflowAction } from '../catalog/callflowActionCatalog'

export type CallflowBranchOption = {
  value: CallflowTreeBranchKey
  label: string
  description: string
}

export type CallflowChildEntry = [key: string, node: CallflowNode]

const conditionalBranchOrder = ['match', 'nomatch', '_'] as const
const terminalModules = new Set([
  'dead_air',
  'disa',
  'group_pickup',
  'hangup',
  'offnet',
  'pivot',
  'receive_fax',
  'resources',
  'response',
])

export type CallflowNodeDropDecision = {
  state: 'idle' | 'allowed' | 'disallowed'
  effect: 'copy' | 'move' | null
  reason: string | null
}

export type CallflowNodeDropContext = {
  node: CallflowNode
  path: string[]
  editable: boolean
  moving: boolean
  dragSourcePath: string[] | null
  paletteAction: CallflowAction | null
}

/**
 * JSON object key order is not a callflow layout contract. MySQL may place the
 * wildcard key first, while Switch's editor presents result branches before the
 * fallback. Keep the visual order deterministic and aligned with that workflow.
 */
export function orderedCallflowChildren(node: CallflowNode): CallflowChildEntry[] {
  const entries = Object.entries(node.children)
  const preferred: readonly string[] | null =
    node.module === 'check_cid' || node.module === 'cidlistmatch'
      ? conditionalBranchOrder
      : node.module === 'temporal_route'
        ? (['rule_set', '_'] as const)
        : null

  if (!preferred) return entries

  const rank = new Map<string, number>(preferred.map((key, index) => [key, index]))

  return entries
    .map((entry, index) => ({ entry, index }))
    .sort((left, right) => {
      const leftRank = rank.get(left.entry[0]) ?? preferred.length
      const rightRank = rank.get(right.entry[0]) ?? preferred.length

      return leftRank - rightRank || left.index - right.index
    })
    .map(({ entry }) => entry)
}

export function availableCallflowBranches(node: CallflowNode): CallflowBranchOption[] {
  const capability = callflowDropCapability(node)

  if (!capability.accepts_children) {
    return []
  }

  if (
    (node.module === 'check_cid' && node.settings?.use_absolute_mode === true) ||
    (node.module === 'branch_variable' && node.settings?.supported_variable !== true)
  ) {
    return []
  }

  const candidates: CallflowBranchOption[] = [
    { value: '_', label: 'Next step', description: 'Default continuation branch' },
  ]

  if (node.module === 'menu') {
    candidates.push(
      { value: 'timeout', label: 'Timeout', description: 'No key was entered' },
      ...['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'].map((value) => ({
        value: value as CallflowTreeBranchKey,
        label: value === '*' ? 'Star key' : `Key ${value}`,
        description: 'Menu keypad branch',
      })),
    )
  }

  if (node.module === 'temporal_route') {
    candidates.push({
      value: 'rule_set',
      label: 'Schedule matches',
      description: 'Temporal Rule Set match branch',
    })
  }

  if (
    (node.module === 'check_cid' && node.settings?.use_absolute_mode !== true) ||
    node.module === 'cidlistmatch'
  ) {
    candidates.push(
      { value: 'match', label: 'Caller ID matches', description: 'Matched caller ID branch' },
      {
        value: 'nomatch',
        label: 'Caller ID does not match',
        description: 'Unmatched caller ID branch',
      },
    )
  }

  if (node.module === 'branch_variable' && node.settings?.supported_variable === true) {
    candidates.push(
      ...callflowPriorityBranchKeys.map((value) => ({
        value,
        label: `Priority ${value}`,
        description: 'Call Priority value branch',
      })),
    )
  }

  return candidates.filter(({ value }) => !Object.hasOwn(node.children, value))
}

export function supportsCapturedNumberBranches(node: CallflowNode): boolean {
  return node.module === 'branch_bnumber' && node.settings?.hunt !== true
}

export function canAddCallflowChild(node: CallflowNode): boolean {
  return callflowDropCapability(node).accepts_children
}

export function callflowDropCapability(node: CallflowNode): CallflowDropCapability {
  if (node.drop_capability) {
    return node.drop_capability
  }

  if (terminalModules.has(node.module)) {
    return {
      accepts_children: false,
      default_branch_available: false,
      branch_mode: 'terminal',
      reason: 'This Switch action is terminal and cannot accept another action.',
    }
  }

  if (node.reference_status === 'unresolved') {
    return {
      accepts_children: false,
      default_branch_available: false,
      branch_mode: fallbackBranchMode(node),
      reason: 'Resolve this action reference before attaching another action.',
    }
  }

  if (fallbackBranchMode(node) === 'locked') {
    return {
      accepts_children: false,
      default_branch_available: false,
      branch_mode: 'locked',
      reason: 'This conditional action has preserved branches that cannot be edited.',
    }
  }

  const defaultBranchAvailable = !Object.hasOwn(node.children, '_')
  const hasSpecialBranch = hasFallbackSpecialBranch(node)
  const acceptsChildren = defaultBranchAvailable || hasSpecialBranch

  return {
    accepts_children: acceptsChildren,
    default_branch_available: defaultBranchAvailable,
    branch_mode: fallbackBranchMode(node),
    reason: acceptsChildren ? null : occupiedBranchReason(node),
  }
}

export function callflowNodeDropDecision(
  context: CallflowNodeDropContext,
): CallflowNodeDropDecision {
  const dragActive = context.paletteAction !== null || context.dragSourcePath !== null

  if (!dragActive) {
    return { state: 'idle', effect: null, reason: null }
  }

  if (!context.editable || context.moving) {
    return disallowed('Callflow editing is not currently available.')
  }

  if (context.node.branch?.kind === 'preserved') {
    return disallowed('Preserved Switch branches cannot be changed.')
  }

  if (findCallflowAction(context.node.module)?.status !== 'guided') {
    return disallowed('This node is not supported by the guided callflow editor.')
  }

  const capability = callflowDropCapability(context.node)

  if (context.paletteAction) {
    if (context.paletteAction.status !== 'guided') {
      return disallowed('This action is not available in the guided editor.')
    }

    const placement = callflowPalettePlacement(context.node, context.paletteAction)

    return placement
      ? { state: 'allowed', effect: 'copy', reason: null }
      : disallowed(capability.reason ?? 'This node cannot accept another action.')
  }

  const sourcePath = context.dragSourcePath

  if (!sourcePath || sourcePath.length === 0) {
    return disallowed('The root callflow action cannot be moved.')
  }

  if (pathStartsWith(context.path, sourcePath)) {
    return disallowed('A callflow action cannot be moved into its own subtree.')
  }

  if (samePath([...context.path, '_'], sourcePath)) {
    return disallowed('This action is already in the destination continuation branch.')
  }

  if (!capability.default_branch_available) {
    return disallowed(
      capability.reason ?? 'The destination continuation branch is already occupied.',
    )
  }

  return { state: 'allowed', effect: 'move', reason: null }
}

export function callflowPalettePlacement(
  node: CallflowNode,
  action: CallflowAction,
): CallflowNodePlacement | null {
  const capability = callflowDropCapability(node)

  if (capability.accepts_children) return 'append'
  if (
    capability.branch_mode !== 'continuation' ||
    node.reference_status === 'unresolved' ||
    !Object.hasOwn(node.children, '_') ||
    !callflowInlineModules.includes(action.module as (typeof callflowInlineModules)[number])
  ) {
    return null
  }

  return terminalModules.has(action.module) ? 'replace' : 'insert_before'
}

function disallowed(reason: string): CallflowNodeDropDecision {
  return { state: 'disallowed', effect: null, reason }
}

function samePath(left: string[], right: string[]): boolean {
  return left.length === right.length && left.every((segment, index) => segment === right[index])
}

function pathStartsWith(path: string[], prefix: string[]): boolean {
  return path.length >= prefix.length && prefix.every((segment, index) => segment === path[index])
}

function fallbackBranchMode(node: CallflowNode): CallflowDropCapability['branch_mode'] {
  if (terminalModules.has(node.module)) return 'terminal'
  if (
    (node.module === 'check_cid' && node.settings?.use_absolute_mode === true) ||
    (node.module === 'branch_variable' && node.settings?.supported_variable !== true)
  ) {
    return 'locked'
  }
  if (node.module === 'menu') return 'menu'
  if (node.module === 'check_cid' || node.module === 'cidlistmatch') return 'condition'
  if (node.module === 'temporal_route') return 'temporal'
  if (node.module === 'branch_variable') return 'priority'
  if (node.module === 'branch_bnumber') return 'captured_number'

  return 'continuation'
}

function hasFallbackSpecialBranch(node: CallflowNode): boolean {
  if (node.module === 'menu') {
    return ['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'].some(
      (branch) => !Object.hasOwn(node.children, branch),
    )
  }

  if (
    (node.module === 'check_cid' && node.settings?.use_absolute_mode !== true) ||
    node.module === 'cidlistmatch'
  ) {
    return ['match', 'nomatch'].some((branch) => !Object.hasOwn(node.children, branch))
  }

  if (node.module === 'temporal_route') {
    return !Object.hasOwn(node.children, 'rule_set')
  }

  if (node.module === 'branch_variable' && node.settings?.supported_variable === true) {
    return callflowPriorityBranchKeys.some((branch) => !Object.hasOwn(node.children, branch))
  }

  return supportsCapturedNumberBranches(node)
}

function occupiedBranchReason(node: CallflowNode): string {
  if (node.module === 'set_variables') {
    // Monster exposes Set CAV without a quantity rule, but serializes every
    // child through the same `_` key. A second child would replace an existing
    // subtree when saved, so only the empty continuation is a safe drop target.
    return 'Set CAV already has a next step. Remove or move it before attaching another action.'
  }

  return 'All editable branches on this Switch action are occupied.'
}
