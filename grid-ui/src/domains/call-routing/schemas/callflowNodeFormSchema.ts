import { z } from 'zod'
import {
  isCallflowCapturedNumberBranchKey,
  isCallflowTreeBranchKey,
  type CallflowTreeBranchKey,
} from '../types/callRouting'

export function createCallflowNodeFormSchema(
  destinationIds: string[],
  branchKeys: CallflowTreeBranchKey[],
  branchRequired: boolean,
  allowCapturedNumberBranch = false,
  occupiedBranchKeys: string[] = [],
) {
  const destinations = new Set(destinationIds)
  const branches = new Set(branchKeys)
  const occupied = new Set(occupiedBranchKeys)

  return z
    .object({
      branch: z
        .custom<CallflowTreeBranchKey>(
          isCallflowTreeBranchKey,
          'Choose a supported callflow branch.',
        )
        .nullable(),
      destination_id: z.string(),
    })
    .strict()
    .superRefine((input, context) => {
      const customCapturedNumber =
        allowCapturedNumberBranch &&
        isCallflowCapturedNumberBranchKey(input.branch) &&
        !occupied.has(input.branch)

      if (
        branchRequired &&
        (input.branch === null || (!branches.has(input.branch) && !customCapturedNumber))
      ) {
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
