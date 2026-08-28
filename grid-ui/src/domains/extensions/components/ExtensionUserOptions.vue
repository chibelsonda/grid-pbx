<script setup lang="ts">
import { LanguageIcon, PhoneIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import type { ExtensionUserConfiguration } from '../types/extension'

defineProps<{ fieldErrors: Record<string, string[]> }>()
const configuration = defineModel<ExtensionUserConfiguration>({ required: true })
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-cyan-50 text-cyan-600">
        <ShieldCheckIcon class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">User calling options</h2>
        <p class="text-[10px] text-slate-400">Public Switch settings applied to this user.</p>
      </div>
    </header>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
      <label class="grid gap-2">
        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
          <LanguageIcon class="size-4" /> Language
        </span>
        <input
          v-model="configuration.language"
          maxlength="32"
          placeholder="Account default"
          class="field-control"
        />
        <span v-if="fieldErrors.language" class="text-[10px] text-danger">{{
          fieldErrors.language[0]
        }}</span>
      </label>

      <label class="grid gap-2">
        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
          <PhoneIcon class="size-4" /> Presence ID
        </span>
        <input
          v-model="configuration.presence_id"
          maxlength="255"
          placeholder="Defaults to extension number"
          class="field-control"
        />
        <span v-if="fieldErrors.presence_id" class="text-[10px] text-danger">{{
          fieldErrors.presence_id[0]
        }}</span>
      </label>

      <ToggleSwitch
        v-model="configuration.call_waiting.enabled"
        label="Call waiting"
        description="Allow another call while the user is busy"
        class="rounded-md border border-slate-200 px-3 py-2.5"
      />
      <ToggleSwitch
        v-model="configuration.do_not_disturb.enabled"
        label="Do not disturb"
        description="Send calls through the unavailable branch"
        class="rounded-md border border-slate-200 px-3 py-2.5"
      />
      <ToggleSwitch
        v-model="configuration.contact_list.exclude"
        label="Hide from directory contacts"
        description="Exclude this user from generated contact lists"
        class="rounded-md border border-slate-200 px-3 py-2.5"
      />

      <label class="grid gap-2">
        <span class="text-xs font-semibold text-slate-600">Outbound caller-ID privacy</span>
        <FormListbox
          v-model="configuration.caller_id_options.outbound_privacy"
          :options="[
            { value: 'none', label: 'Show name and number' },
            { value: 'name', label: 'Hide name' },
            { value: 'number', label: 'Hide number' },
            { value: 'full', label: 'Hide name and number' },
          ]"
        />
        <span
          v-if="fieldErrors['caller_id_options.outbound_privacy']"
          class="text-[10px] text-danger"
          >{{ fieldErrors['caller_id_options.outbound_privacy'][0] }}</span
        >
      </label>
    </div>
  </article>
</template>
