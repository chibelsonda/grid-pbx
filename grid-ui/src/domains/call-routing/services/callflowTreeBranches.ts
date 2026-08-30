import {
  callflowPriorityBranchKeys,
  type CallflowNode,
  type CallflowTreeBranchKey,
} from '../types/callRouting'

export type CallflowBranchOption = {
  value: CallflowTreeBranchKey
  label: string
  description: string
}

export type CallflowChildEntry = [key: string, node: CallflowNode]

const conditionalBranchOrder = ['match', 'nomatch', '_'] as const

/**
 * JSON object key order is not a callflow layout contract. MySQL may place the
 * wildcard key first, while Kazoo's editor presents result branches before the
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
  return availableCallflowBranches(node).length > 0 || supportsCapturedNumberBranches(node)
}
