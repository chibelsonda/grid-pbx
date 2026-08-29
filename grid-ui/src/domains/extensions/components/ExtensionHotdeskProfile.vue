<script setup lang="ts">
import { KeyIcon } from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { ExtensionHotdeskInput } from '../types/extension'

withDefaults(
  defineProps<{
    fieldErrors: Record<string, string[]>
    pinConfigured?: boolean
    editing?: boolean
  }>(),
  { pinConfigured: false, editing: false },
)
const hotdesk = defineModel<ExtensionHotdeskInput>({ required: true })

function removeConfiguredPin(): void {
  hotdesk.value.pin = null
  hotdesk.value.clear_pin = true
}

function keepConfiguredPin(): void {
  hotdesk.value.clear_pin = false
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-amber-50 text-amber-600">
        <KeyIcon class="size-5" />
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold text-slate-700">Hotdesk profile</h2>
        <p class="text-[10px] text-slate-400">
          Let this user sign in to compatible shared devices by dial-pad ID.
        </p>
      </div>
      <ToggleSwitch
        v-model="hotdesk.enabled"
        label="Enabled"
        :invalid="Boolean(fieldErrors['hotdesk.enabled'])"
      />
    </header>

    <div v-if="hotdesk.enabled" class="grid gap-4 p-5 sm:grid-cols-2">
      <FormInput
        v-model="hotdesk.id"
        label="Hotdesk ID"
        inputmode="tel"
        maxlength="15"
        autocomplete="off"
        placeholder="1001"
        description="Use 4–15 digits or the *, #, and + dial-pad characters. The ID must be unique in the account."
        :error="fieldErrors['hotdesk.id']"
      />

      <ToggleSwitch
        v-model="hotdesk.keep_logged_in_elsewhere"
        label="Keep logged in elsewhere"
        description="Allow this user to stay signed in on multiple shared devices"
        class="rounded-md border border-slate-200 px-3 py-2.5"
        :class="validationControlClass(fieldErrors['hotdesk.keep_logged_in_elsewhere'])"
        :invalid="Boolean(fieldErrors['hotdesk.keep_logged_in_elsewhere'])"
      />

      <ToggleSwitch
        v-model="hotdesk.require_pin"
        label="Require a PIN"
        description="Ask for the PIN before changing this user's hotdesk state"
        class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
        :class="validationControlClass(fieldErrors['hotdesk.require_pin'])"
        :invalid="Boolean(fieldErrors['hotdesk.require_pin'])"
      />

      <FormInput
        v-if="hotdesk.require_pin"
        v-model="hotdesk.pin"
        :label="editing ? 'New hotdesk PIN' : 'Hotdesk PIN'"
        class="sm:col-span-2"
        type="password"
        inputmode="numeric"
        maxlength="15"
        autocomplete="new-password"
        placeholder="4–15 digits"
        :description="editing && pinConfigured ? 'Leave blank to keep the configured PIN.' : null"
        :error="fieldErrors['hotdesk.pin']"
      />

      <div
        v-if="editing && pinConfigured"
        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-3 text-xs sm:col-span-2"
        :class="validationControlClass(fieldErrors['hotdesk.clear_pin'])"
      >
        <div>
          <p class="font-semibold text-slate-600">
            {{ hotdesk.clear_pin ? 'PIN will be removed' : 'A hotdesk PIN is configured' }}
          </p>
          <p class="mt-0.5 text-[10px] text-slate-400">
            GridPBX never returns the PIN after it is saved.
          </p>
        </div>
        <button
          v-if="hotdesk.clear_pin"
          type="button"
          class="font-semibold text-brand-600 hover:text-brand-700"
          @click="keepConfiguredPin"
        >
          Keep configured PIN
        </button>
        <button
          v-else
          type="button"
          class="font-semibold text-danger hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="hotdesk.require_pin"
          @click="removeConfiguredPin"
        >
          Remove configured PIN
        </button>
        <span v-if="fieldErrors['hotdesk.clear_pin']" class="w-full text-[10px] text-danger">{{
          fieldErrors['hotdesk.clear_pin'][0]
        }}</span>
      </div>
    </div>
  </article>
</template>
