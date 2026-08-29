<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronDownIcon, PhoneArrowUpRightIcon } from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type {
  ExtensionCallerIdNumberOption,
  ExtensionRestrictionOption,
  ExtensionUpdate,
} from '../types/extension'

type Model = Pick<ExtensionUpdate, 'caller_id' | 'call_forward' | 'call_restriction'>

const props = defineProps<{
  fieldErrors: Record<string, string[]>
  phoneNumbers: ExtensionCallerIdNumberOption[]
  restrictions: ExtensionRestrictionOption[]
  unresolvedNumbers: { external: string | null; emergency: string | null }
}>()
const settings = defineModel<Model>({ required: true })

function error(path: string): string | null {
  return props.fieldErrors[path]?.[0] ?? null
}

function phoneOptions(emergency: boolean) {
  return [
    { value: null, label: 'Use account caller ID' },
    ...props.phoneNumbers
      .filter((number) => !emergency || number.e911_enabled)
      .map((number) => ({
        value: number.id,
        label: `${number.number}${number.display_name ? ` — ${number.display_name}` : ''}`,
      })),
  ]
}

function selectNumber(scope: 'external' | 'emergency', value: unknown): void {
  settings.value.caller_id[scope].phone_number_id = typeof value === 'string' ? value : null
  settings.value.caller_id[scope].preserve_number = false
}

function restrictionAction(key: string): 'inherit' | 'deny' {
  return settings.value.call_restriction[key]?.action ?? 'inherit'
}

function setRestrictionAction(key: string, value: unknown): void {
  settings.value.call_restriction[key] = {
    action: value === 'deny' ? 'deny' : 'inherit',
  }
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-indigo-50 text-indigo-600">
        <PhoneArrowUpRightIcon class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Caller ID and forwarding</h2>
        <p class="text-[10px] leading-4 text-slate-500">
          Guided public Switch fields; asserted identity remains Switch-managed.
        </p>
      </div>
    </header>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
      <label class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Internal caller-ID name</span>
        <input
          v-model="settings.caller_id.internal.name"
          maxlength="35"
          class="field-control"
          :class="validationControlClass(error('caller_id.internal.name'))"
          :aria-invalid="Boolean(error('caller_id.internal.name'))"
        />
        <span v-if="error('caller_id.internal.name')" class="text-[10px] text-danger">{{
          error('caller_id.internal.name')
        }}</span>
      </label>
      <label class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Internal caller-ID number</span>
        <input
          v-model="settings.caller_id.internal.number"
          maxlength="35"
          class="field-control"
          :class="validationControlClass(error('caller_id.internal.number'))"
          :aria-invalid="Boolean(error('caller_id.internal.number'))"
        />
        <span v-if="error('caller_id.internal.number')" class="text-[10px] text-danger">{{
          error('caller_id.internal.number')
        }}</span>
      </label>

      <template v-for="scope in (['external', 'emergency'] as const)" :key="scope">
        <label class="grid gap-2">
          <span class="text-xs font-semibold capitalize text-slate-600">{{ scope }} caller-ID name</span>
          <input
            v-model="settings.caller_id[scope].name"
            maxlength="35"
            class="field-control"
            :class="validationControlClass(error(`caller_id.${scope}.name`))"
            :aria-invalid="Boolean(error(`caller_id.${scope}.name`))"
          />
          <span v-if="error(`caller_id.${scope}.name`)" class="text-[10px] text-danger">{{
            error(`caller_id.${scope}.name`)
          }}</span>
        </label>
        <div class="grid gap-2">
          <span class="text-xs font-semibold capitalize text-slate-600">{{ scope }} caller-ID number</span>
          <FormListbox
            :model-value="settings.caller_id[scope].phone_number_id"
            :options="phoneOptions(scope === 'emergency')"
            :invalid="Boolean(error(`caller_id.${scope}.phone_number_id`))"
            @update:model-value="selectNumber(scope, $event)"
          />
          <span v-if="error(`caller_id.${scope}.phone_number_id`)" class="text-[10px] text-danger">{{
            error(`caller_id.${scope}.phone_number_id`)
          }}</span>
          <div
            v-if="settings.caller_id[scope].preserve_number"
            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] leading-4 text-amber-800"
          >
            The current number {{ unresolvedNumbers[scope] }} is not projected locally.
            <ToggleSwitch
              v-model="settings.caller_id[scope].preserve_number"
              class="mt-2"
              label="Preserve unresolved number"
            />
          </div>
        </div>
      </template>

      <ToggleSwitch
        v-model="settings.call_forward.enabled"
        label="Enable call forwarding"
        description="Forward calls to another extension or public number"
        class="rounded-md border border-slate-200 p-3 sm:col-span-2"
      />
      <label class="grid gap-2 sm:col-span-2">
        <span class="text-xs font-semibold text-slate-600">Forwarding destination</span>
        <input
          v-model="settings.call_forward.number"
          maxlength="35"
          inputmode="tel"
          class="field-control"
          :disabled="!settings.call_forward.enabled"
          :class="validationControlClass(error('call_forward.number'))"
          :aria-invalid="Boolean(error('call_forward.number'))"
        />
        <span v-if="error('call_forward.number')" class="text-[10px] text-danger">{{
          error('call_forward.number')
        }}</span>
      </label>

      <Disclosure v-slot="{ open }" as="div" class="sm:col-span-2">
        <DisclosureButton
          class="flex w-full items-center justify-between rounded-md border border-slate-200 px-4 py-3 text-left text-xs font-semibold text-slate-700"
        >
          Forwarding behavior
          <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
        </DisclosureButton>
        <DisclosurePanel class="grid gap-3 border-x border-b border-slate-200 p-4 sm:grid-cols-2">
          <ToggleSwitch v-model="settings.call_forward.direct_calls_only" label="Direct calls only" />
          <ToggleSwitch v-model="settings.call_forward.failover" label="Use as failover" />
          <ToggleSwitch v-model="settings.call_forward.ignore_early_media" label="Ignore early media" />
          <ToggleSwitch v-model="settings.call_forward.keep_caller_id" label="Keep caller ID" />
          <ToggleSwitch v-model="settings.call_forward.require_keypress" label="Require keypress" />
          <ToggleSwitch v-model="settings.call_forward.substitute" label="Replace the user endpoint" />
        </DisclosurePanel>
      </Disclosure>
    </div>
  </article>

  <article class="card-surface overflow-hidden">
    <header class="border-b border-slate-200 px-5 py-4">
      <h2 class="text-sm font-semibold text-slate-700">Call restrictions</h2>
      <p class="mt-1 text-[10px] leading-4 text-slate-500">
        Classifications are discovered from the connected Switch deployment.
      </p>
    </header>
    <div class="divide-y divide-slate-200">
      <div
        v-for="restriction in restrictions"
        :key="restriction.key"
        class="grid items-center gap-3 px-5 py-3 sm:grid-cols-[1fr_15rem]"
      >
        <div>
          <p class="text-xs font-semibold text-slate-700">{{ restriction.label }}</p>
          <p class="text-[10px] text-slate-500">{{ restriction.key }}</p>
        </div>
        <FormListbox
          :model-value="restrictionAction(restriction.key)"
          :invalid="Boolean(error(`call_restriction.${restriction.key}.action`))"
          :options="[
            { value: 'inherit', label: 'Inherit account policy' },
            { value: 'deny', label: 'Deny' },
          ]"
          @update:model-value="setRestrictionAction(restriction.key, $event)"
        />
      </div>
    </div>
  </article>
</template>
