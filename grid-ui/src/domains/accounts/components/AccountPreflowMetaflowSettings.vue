<script setup lang="ts">
import { computed } from 'vue'
import { BoltIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import MetaflowSettings from '@/shared/switch/metaflows/components/MetaflowSettings.vue'
import type { ListboxOptionValue, ListboxValue } from '@/shared/components/FormListbox.vue'
import type {
  AccountCallflowOption,
  AccountMetaflows,
  AccountPreflow,
  AccountPreflowSelection,
} from '../types/account'
import type { MetaflowResources } from '@/shared/switch/metaflows/types'

const props = defineProps<{
  currentPreflow: AccountPreflow
  currentMetaflows: AccountMetaflows
  callflowOptions: AccountCallflowOption[]
  metaflowResources: MetaflowResources
  fieldErrors: Record<string, string[]>
}>()
const preflow = defineModel<AccountPreflowSelection>('preflow', { required: true })
const metaflows = defineModel<
  Pick<AccountMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>
>('metaflows', { required: true })

const callflows = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'No account preflow' },
  ...props.callflowOptions.map((callflow) => ({
    value: callflow.id,
    label: callflow.name,
    description: callflow.description,
  })),
])
const bindingDigits: ListboxOptionValue[] = [
  { value: null, label: 'Use Switch default (*)' },
  ...['*', '#', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'].map((value) => ({
    value,
    label: value,
  })),
]
const listenOptions: ListboxOptionValue[] = [
  { value: null, label: 'Use Switch default' },
  { value: 'both', label: 'Both call legs' },
  { value: 'self', label: 'Originating leg' },
  { value: 'peer', label: 'Peer leg' },
]

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function selectPreflow(value: ListboxValue): void {
  preflow.value.callflow_id = typeof value === 'string' ? value : null
  preflow.value.preserve_callflow = false
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
      <BoltIcon class="size-5 text-brand-500" />
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Preflow and in-call features</h2>
        <p class="mt-1 text-[10px] leading-4 text-slate-500">
          Run one projected callflow before normal routing and configure account-level DTMF
          activation.
        </p>
      </div>
    </header>
    <div class="grid gap-5 p-5">
      <label class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Always run before call routing</span>
        <FormListbox
          :model-value="preflow.callflow_id"
          aria-label="Account preflow"
          :options="callflows"
          :invalid="Boolean(error('preflow.callflow_id'))"
          @update:model-value="selectPreflow"
        />
        <span v-if="error('preflow.callflow_id')" class="text-[10px] text-danger">
          {{ error('preflow.callflow_id') }}
        </span>
      </label>

      <div
        v-if="currentPreflow.unresolved"
        class="rounded-md border border-amber-200 bg-amber-50 p-4"
      >
        <p class="text-[10px] leading-4 text-amber-800">
          The current preflow points to a Callflow that is not in the account projection.
        </p>
        <ToggleSwitch
          v-model="preflow.preserve_callflow"
          class="mt-3"
          label="Keep unresolved preflow"
          description="Turn this off to clear it, or select a projected Callflow above."
        />
      </div>

      <section class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-3">
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Binding digit</span>
          <FormListbox
            v-model="metaflows.binding_digit"
            aria-label="Metaflow binding digit"
            :options="bindingDigits"
            :invalid="Boolean(error('metaflows.binding_digit'))"
          />
          <span v-if="error('metaflows.binding_digit')" class="text-[10px] text-danger">
            {{ error('metaflows.binding_digit') }}
          </span>
        </label>
        <FormInput
          v-model.number="metaflows.digit_timeout"
          label="Digit timeout (ms)"
          type="number"
          min="0"
          max="60000"
          placeholder="Switch default"
          :error="error('metaflows.digit_timeout')"
        />
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Listen on</span>
          <FormListbox
            v-model="metaflows.listen_on"
            aria-label="Metaflow listen on"
            :options="listenOptions"
            :invalid="Boolean(error('metaflows.listen_on'))"
          />
          <span v-if="error('metaflows.listen_on')" class="text-[10px] text-danger">
            {{ error('metaflows.listen_on') }}
          </span>
        </label>
      </section>

      <section class="grid gap-4 border-t border-slate-200 pt-5">
        <p class="text-[10px] leading-4 text-slate-500">
          Existing Switch configuration contains
          {{ currentMetaflows.number_flow_count }} number trigger(s) and
          {{ currentMetaflows.pattern_flow_count }} pattern trigger(s).
        </p>
        <MetaflowSettings
          v-model="metaflows.actions"
          :field-errors="fieldErrors"
          :locked-action-count="currentMetaflows.locked_action_count"
          :media-options="metaflowResources.media"
          :callflow-options="metaflowResources.callflows"
          :device-options="metaflowResources.devices"
          :extension-options="metaflowResources.extensions"
        />
      </section>
    </div>
  </article>
</template>
