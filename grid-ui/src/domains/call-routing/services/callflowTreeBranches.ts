import type { CallflowNode, CallflowTreeBranchKey } from '../types/callRouting'

export type CallflowBranchOption = {
  value: CallflowTreeBranchKey
  label: string
  description: string
}

export function availableCallflowBranches(node: CallflowNode): CallflowBranchOption[] {
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

  return candidates.filter(({ value }) => !Object.hasOwn(node.children, value))
}
