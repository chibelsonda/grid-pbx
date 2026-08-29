import { z } from 'zod'
import {
  callflowDestinationTypes,
  callflowMenuBranchKeys,
  type CallflowDestinationType,
  type CallflowEditor,
} from '../types/callRouting'

const unique = <T>(values: T[]): boolean => new Set(values).size === values.length

export function createCallflowFormSchema(editor: CallflowEditor) {
  const destinationIds = new Map<CallflowDestinationType, Set<string>>(
    callflowDestinationTypes.map((type) => [
      type,
      new Set(editor.destinations[type].map(({ id }) => id)),
    ]),
  )
  const availablePhoneNumberIds = new Set(
    editor.phone_numbers.filter(({ available }) => available).map(({ id }) => id),
  )
  const availableTemporalRuleIds = new Set(editor.temporal_rules.map(({ id }) => id))

  return z
    .object({
      name: z.string().trim().min(1, 'Enter a route name.').max(128),
      destination_type: z.enum(callflowDestinationTypes),
      destination_id: z.string(),
      temporal_rule_ids: z
        .array(z.uuid('Select a valid Temporal Rule.'))
        .max(50, 'Select no more than 50 Temporal Rules.')
        .refine(unique, 'Select each Temporal Rule once.')
        .default([]),
      temporal_rule_routes: z
        .array(
          z.object({
            rule_id: z.uuid('Select a valid Temporal Rule.'),
            destination_type: z.enum(callflowDestinationTypes),
            destination_id: z.string(),
          }),
        )
        .max(50, 'Configure no more than 50 Temporal Rule routes.')
        .default([]),
      manage_fallback: z.boolean(),
      fallback_enabled: z.boolean(),
      fallback_destination_type: z.enum(callflowDestinationTypes),
      fallback_destination_id: z.string(),
      manage_menu_branches: z.boolean(),
      menu_branches: z
        .array(
          z.object({
            key: z.enum(callflowMenuBranchKeys),
            destination_type: z.enum(callflowDestinationTypes),
            destination_id: z.string(),
          }),
        )
        .max(callflowMenuBranchKeys.length),
      manage_temporal_match: z.boolean(),
      temporal_match_enabled: z.boolean(),
      temporal_match_destination_type: z.enum(callflowDestinationTypes),
      temporal_match_destination_id: z.string(),
      phone_number_ids: z
        .array(z.uuid('Select a valid phone number.'))
        .max(25, 'Select no more than 25 phone numbers.')
        .refine(unique, 'Select each phone number once.'),
    })
    .strict()
    .superRefine((input, context) => {
      if (
        input.destination_type !== 'temporal_rules' &&
        !destinationIds.get(input.destination_type)?.has(input.destination_id)
      ) {
        context.addIssue({
          code: 'custom',
          path: ['destination_id'],
          message: 'Select an available destination.',
        })
      }

      if (input.destination_type === 'temporal_rules') {
        if (input.temporal_rule_ids.length === 0) {
          context.addIssue({
            code: 'custom',
            path: ['temporal_rule_ids'],
            message: 'Select at least one Temporal Rule.',
          })
        }

        if (input.temporal_rule_ids.some((id) => !availableTemporalRuleIds.has(id))) {
          context.addIssue({
            code: 'custom',
            path: ['temporal_rule_ids'],
            message: 'One or more Temporal Rules are unavailable.',
          })
        }

        const routeRuleIds = input.temporal_rule_routes.map(({ rule_id }) => rule_id)

        if (
          !unique(routeRuleIds) ||
          routeRuleIds.length !== input.temporal_rule_ids.length ||
          input.temporal_rule_ids.some((id) => !routeRuleIds.includes(id))
        ) {
          context.addIssue({
            code: 'custom',
            path: ['temporal_rule_routes'],
            message: 'Configure exactly one match destination for each selected Temporal Rule.',
          })
        }

        input.temporal_rule_routes.forEach((route, index) => {
          if (
            route.destination_type === 'temporal_rules' ||
            !destinationIds.get(route.destination_type)?.has(route.destination_id)
          ) {
            context.addIssue({
              code: 'custom',
              path: ['temporal_rule_routes', index, 'destination_id'],
              message: 'Select an available match destination.',
            })
          }

          const current = editor.direct_temporal_routes.find(
            ({ rule_id }) => rule_id === route.rule_id,
          )

          if (current?.editable === false) {
            context.addIssue({
              code: 'custom',
              path: ['temporal_rule_routes', index, 'destination_id'],
              message:
                current.blocked_reason ??
                'This Temporal Rule branch is read-only and will be preserved.',
            })
          }
        })
      }

      if (
        input.manage_fallback &&
        input.fallback_enabled &&
        !destinationIds.get(input.fallback_destination_type)?.has(input.fallback_destination_id)
      ) {
        context.addIssue({
          code: 'custom',
          path: ['fallback_destination_id'],
          message: 'Select an available fallback destination.',
        })
      }

      if (
        input.manage_menu_branches &&
        input.menu_branches.some(
          ({ key }, index) =>
            input.menu_branches.findIndex((branch) => branch.key === key) !== index,
        )
      ) {
        context.addIssue({
          code: 'custom',
          path: ['menu_branches'],
          message: 'Route each Menu key only once.',
        })
      }

      if (input.manage_menu_branches && input.destination_type !== 'menu') {
        if (input.menu_branches.length > 0) {
          context.addIssue({
            code: 'custom',
            path: ['menu_branches'],
            message: 'Menu key routes require a Menu / IVR root destination.',
          })
        }
      } else if (input.manage_menu_branches) {
        input.menu_branches.forEach((branch, index) => {
          if (!destinationIds.get(branch.destination_type)?.has(branch.destination_id)) {
            context.addIssue({
              code: 'custom',
              path: ['menu_branches', index, 'destination_id'],
              message: 'Select an available key destination.',
            })
          }

          const editorBranch = editor.menu_branches.branches.find(({ key }) => key === branch.key)

          if (!editorBranch?.editable) {
            context.addIssue({
              code: 'custom',
              path: ['menu_branches', index, 'key'],
              message: `Menu key ${branch.key} is read-only and will be preserved.`,
            })
          }
        })
      }

      if (
        input.manage_temporal_match &&
        input.destination_type === 'temporal_rule_set' &&
        input.temporal_match_enabled &&
        !destinationIds
          .get(input.temporal_match_destination_type)
          ?.has(input.temporal_match_destination_id)
      ) {
        context.addIssue({
          code: 'custom',
          path: ['temporal_match_destination_id'],
          message: 'Select an available schedule match destination.',
        })
      }

      if (
        input.destination_type === 'temporal_rule_set' &&
        !editor.temporal_rule_sets[input.destination_id]?.some(({ resolved }) => resolved)
      ) {
        context.addIssue({
          code: 'custom',
          path: ['destination_id'],
          message: 'Select a schedule with at least one projected rule.',
        })
      }

      if (editor.mode === 'create' && input.phone_number_ids.length === 0) {
        context.addIssue({
          code: 'custom',
          path: ['phone_number_ids'],
          message: 'Select at least one phone number.',
        })
      }

      if (input.phone_number_ids.some((id) => !availablePhoneNumberIds.has(id))) {
        context.addIssue({
          code: 'custom',
          path: ['phone_number_ids'],
          message: 'One or more selected phone numbers are unavailable.',
        })
      }
    })
    .transform(({ fallback_enabled, temporal_match_enabled, ...input }) => ({
      ...input,
      destination_id: input.destination_type === 'temporal_rules' ? null : input.destination_id,
      temporal_rule_ids: input.destination_type === 'temporal_rules' ? input.temporal_rule_ids : [],
      temporal_rule_routes:
        input.destination_type === 'temporal_rules' ? input.temporal_rule_routes : [],
      fallback_destination_type:
        input.manage_fallback && fallback_enabled ? input.fallback_destination_type : null,
      fallback_destination_id:
        input.manage_fallback && fallback_enabled ? input.fallback_destination_id : null,
      manage_menu_branches: input.manage_menu_branches && input.destination_type === 'menu',
      menu_branches:
        input.manage_menu_branches && input.destination_type === 'menu' ? input.menu_branches : [],
      manage_temporal_match:
        input.manage_temporal_match && input.destination_type === 'temporal_rule_set',
      temporal_match_destination_type:
        input.manage_temporal_match &&
        input.destination_type === 'temporal_rule_set' &&
        temporal_match_enabled
          ? input.temporal_match_destination_type
          : null,
      temporal_match_destination_id:
        input.manage_temporal_match &&
        input.destination_type === 'temporal_rule_set' &&
        temporal_match_enabled
          ? input.temporal_match_destination_id
          : null,
    }))
}
