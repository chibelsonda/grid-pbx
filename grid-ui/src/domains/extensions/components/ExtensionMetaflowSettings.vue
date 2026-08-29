<script setup lang="ts">
import { BoltIcon } from '@heroicons/vue/24/outline'
import FormListbox, { type ListboxOptionValue } from '@/shared/components/FormListbox.vue'
import MetaflowSettings from '@/shared/switch/metaflows/components/MetaflowSettings.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { MetaflowResources } from '@/shared/switch/metaflows/types'
import type { ExtensionMetaflows } from '../types/extension'

const props = defineProps<{
  current: ExtensionMetaflows
  resources: MetaflowResources
  fieldErrors: Record<string, string[]>
}>()
const metaflows = defineModel<
  Pick<ExtensionMetaflows, 'binding_digit' | 'digit_timeout' | 'listen_on' | 'actions'>
>({ required: true })
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

function setDigitTimeout(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  metaflows.value.digit_timeout = value === '' ? null : Number(value)
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
      <BoltIcon class="size-5 text-brand-500" />
      <div>
        <h2 class="text-sm font-semibold text-slate-700">In-call metaflows</h2>
        <p class="mt-1 text-[10px] leading-4 text-slate-500">
          Configure account-scoped DTMF actions for this Switch user.
        </p>
      </div>
    </header>
    <div class="grid gap-5 p-5">
      <section class="grid gap-4 sm:grid-cols-3">
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Binding digit</span>
          <FormListbox
            v-model="metaflows.binding_digit"
            :options="bindingDigits"
            :invalid="Boolean(error('metaflows.binding_digit'))"
            aria-label="User metaflow binding digit"
          />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Digit timeout (ms)</span>
          <input
            :value="metaflows.digit_timeout ?? ''"
            type="number"
            min="0"
            max="60000"
            class="field-control"
            :class="validationControlClass(error('metaflows.digit_timeout'))"
            :aria-invalid="Boolean(error('metaflows.digit_timeout'))"
            placeholder="Switch default"
            @input="setDigitTimeout"
          />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Listen on</span>
          <FormListbox
            v-model="metaflows.listen_on"
            :options="listenOptions"
            :invalid="Boolean(error('metaflows.listen_on'))"
            aria-label="User metaflow listen on"
          />
        </label>
      </section>

      <p class="text-[10px] leading-4 text-slate-500">
        Current configuration contains {{ current.number_flow_count }} number trigger(s) and
        {{ current.pattern_flow_count }} pattern trigger(s).
      </p>
      <MetaflowSettings
        v-model="metaflows.actions"
        :field-errors="fieldErrors"
        :locked-action-count="current.locked_action_count"
        :media-options="resources.media"
        :callflow-options="resources.callflows"
        :device-options="resources.devices"
        :extension-options="resources.extensions"
      />
    </div>
  </article>
</template>
