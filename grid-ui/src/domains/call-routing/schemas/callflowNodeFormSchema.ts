import { z } from 'zod'
import { isCallflowTreeBranchKey, type CallflowTreeBranchKey } from '../types/callRouting'

export function createCallflowNodeFormSchema(
  destinationIds: string[],
  branchKeys: CallflowTreeBranchKey[],
  branchRequired: boolean,
) {
  const destinations = new Set(destinationIds)
  const branches = new Set(branchKeys)

  return z
    .object({
      branch: z
        .custom<CallflowTreeBranchKey>(isCallflowTreeBranchKey, 'Choose a supported callflow branch.')
        .nullable(),
      destination_id: z.string(),
    })
    .strict()
    .superRefine((input, context) => {
      if (branchRequired && (input.branch === null || !branches.has(input.branch))) {
        context.addIssue({
          code: 'custom',
          path: ['branch'],
          message: 'Select an available empty branch.',
        })
      }

      if (!destinations.has(input.destination_id)) {
        context.addIssue({
          code: 'custom',
          path: ['destination_id'],
          message: 'Select an available destination.',
        })
      }
    })
}
