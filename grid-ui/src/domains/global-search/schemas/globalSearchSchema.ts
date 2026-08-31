import { z } from 'zod'
import { globalSearchTypes } from '../types/globalSearch'

export const globalSearchResultSchema = z
  .object({
    id: z.uuid(),
    type: z.enum(globalSearchTypes),
    title: z.string(),
    subtitle: z.string(),
    matched_field: z.string(),
  })
  .strict()

export const globalSearchResponseSchema = z
  .object({
    query: z.string(),
    groups: z.array(
      z
        .object({
          type: z.enum(globalSearchTypes),
          label: z.string(),
          results: z.array(globalSearchResultSchema).max(5),
        })
        .strict(),
    ),
    total: z.number().int().nonnegative(),
  })
  .strict()
