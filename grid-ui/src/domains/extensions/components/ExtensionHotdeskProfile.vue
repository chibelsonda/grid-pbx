<script setup lang="ts">
import { KeyIcon } from '@heroicons/vue/24/outline'
import type { ExtensionHotdeskInput } from '../types/extension'

const props = withDefaults(
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
      <ToggleSwitch v-model="hotdesk.enabled" label="Enabled" />
    </header>

    <div v-if="hotdesk.enabled" class="grid gap-4 p-5 sm:grid-cols-2">
      <label class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Hotdesk ID</span>
        <input
          v-model="hotdesk.id"
          inputmode="tel"
          maxlength="15"
          autocomplete="off"
          placeholder="1001"
          class="field-control"
          :aria-invalid="Boolean(fieldErrors['hotdesk.id'])"
          :class="
            fieldErrors['hotdesk.id']
              ? '!border-red-400 focus:!border-red-500 focus:!ring-red-100'
              : ''
          "
        />
        <span v-if="fieldErrors['hotdesk.id']" class="text-[10px] text-danger">{{
          fieldErrors['hotdesk.id'][0]
        }}</span>
        <span v-else class="text-[10px] text-slate-400">
          Use 4–15 digits or the *, #, and + dial-pad characters. The ID must be unique in the
          account.
        </span>
      </label>

      <ToggleSwitch
        v-model="hotdesk.keep_logged_in_elsewhere"
        label="Keep logged in elsewhere"
        description="Allow this user to stay signed in on multiple shared devices"
        class="rounded-md border border-slate-200 px-3 py-2.5"
      />

      <ToggleSwitch
        v-model="hotdesk.require_pin"
        label="Require a PIN"
        description="Ask for the PIN before changing this user's hotdesk state"
        class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
      />

      <label v-if="hotdesk.require_pin" class="grid gap-2 sm:col-span-2">
        <span class="text-xs font-semibold text-slate-600">
          {{ editing ? 'New hotdesk PIN' : 'Hotdesk PIN' }}
          <span v-if="editing && pinConfigured" class="font-normal text-slate-400">
            (leave blank to keep the configured PIN)
          </span>
        </span>
        <input
          v-model="hotdesk.pin"
          type="password"
          inputmode="numeric"
          maxlength="15"
          autocomplete="new-password"
          placeholder="4–15 digits"
          class="field-control"
          :aria-invalid="Boolean(fieldErrors['hotdesk.pin'])"
          :class="
            fieldErrors['hotdesk.pin']
              ? '!border-red-400 focus:!border-red-500 focus:!ring-red-100'
              : ''
          "
        />
        <span v-if="fieldErrors['hotdesk.pin']" class="text-[10px] text-danger">{{
          fieldErrors['hotdesk.pin'][0]
        }}</span>
      </label>

      <div
        v-if="editing && pinConfigured"
        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-3 text-xs sm:col-span-2"
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
