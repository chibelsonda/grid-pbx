<script setup lang="ts">
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import type {
  CallflowDestinationType,
  CallflowEditor,
  CallflowTemporalRuleRouteInput,
} from '../types/callRouting'

const props = defineProps<{
  routes: CallflowTemporalRuleRouteInput[]
  editor: CallflowEditor
  errors: Record<string, string[]>
}>()
const emit = defineEmits<{
  'update:routes': [routes: CallflowTemporalRuleRouteInput[]]
}>()

function fieldError(index: number, field: string): string | null {
  return props.errors[`temporal_rule_routes.${index}.${field}`]?.[0] ?? null
}

function ruleLabel(ruleId: string): string {
  return props.editor.temporal_rules.find(({ id }) => id === ruleId)?.label ?? 'Temporal Rule'
}

function currentDefinition(ruleId: string) {
  return props.editor.direct_temporal_routes.find(({ rule_id }) => rule_id === ruleId) ?? null
}

function destinationTypeOptions(): ListboxOptionValue[] {
  return props.editor.destination_types
    .filter(({ value }) => value !== 'temporal_rules')
    .map(({ value, label }) => ({
      value,
      label,
      disabled: props.editor.destinations[value].length === 0,
    }))
}

function destinationOptions(type: CallflowDestinationType): ListboxOptionValue[] {
  return props.editor.destinations[type].map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  }))
}

function replace(index: number, patch: Partial<CallflowTemporalRuleRouteInput>): void {
  emit(
    'update:routes',
    props.routes.map((route, routeIndex) =>
      routeIndex === index ? { ...route, ...patch } : route,
    ),
  )
}

function setDestinationType(index: number, value: ListboxValue): void {
  if (typeof value !== 'string') return
  const type = value as CallflowDestinationType
  replace(index, {
    destination_type: type,
    destination_id: props.editor.destinations[type][0]?.id ?? '',
  })
}

function setDestination(index: number, value: ListboxValue): void {
  if (typeof value === 'string') replace(index, { destination_id: value })
}
</script>

<template>
  <div class="grid gap-4">
    <div
      v-for="(route, index) in routes"
      :key="route.rule_id"
      class="grid gap-3 rounded-md border border-slate-200 bg-slate-50/60 p-4"
    >
      <div>
        <p class="text-xs font-semibold text-slate-700">
          {{ index + 1 }}. {{ ruleLabel(route.rule_id) }}
        </p>
        <p class="mt-1 text-[10px] text-slate-500">
          Calls matching this rule follow the destination below. Evaluation order is controlled
          above.
        </p>
      </div>

      <div
        v-if="currentDefinition(route.rule_id)?.editable === false"
        class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] leading-4 text-amber-800"
      >
        {{ currentDefinition(route.rule_id)?.blocked_reason }}
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Match destination type</span>
          <FormListbox
            :model-value="route.destination_type"
            :options="destinationTypeOptions()"
            :aria-label="`${ruleLabel(route.rule_id)} destination type`"
            :disabled="currentDefinition(route.rule_id)?.editable === false"
            :invalid="Boolean(fieldError(index, 'destination_type'))"
            @update:model-value="setDestinationType(index, $event)"
          />
          <span v-if="fieldError(index, 'destination_type')" class="text-[10px] text-danger">
            {{ fieldError(index, 'destination_type') }}
          </span>
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Match destination</span>
          <FormListbox
            :model-value="route.destination_id"
            :options="destinationOptions(route.destination_type)"
            :aria-label="`${ruleLabel(route.rule_id)} destination`"
            :disabled="currentDefinition(route.rule_id)?.editable === false"
            placeholder="Select a destination"
            :invalid="Boolean(fieldError(index, 'destination_id'))"
            @update:model-value="setDestination(index, $event)"
          />
          <span v-if="fieldError(index, 'destination_id')" class="text-[10px] text-danger">
            {{ fieldError(index, 'destination_id') }}
          </span>
        </label>
      </div>
    </div>

    <p v-if="errors.temporal_rule_routes?.[0]" class="text-[10px] text-danger">
      {{ errors.temporal_rule_routes[0] }}
    </p>
  </div>
</template>
