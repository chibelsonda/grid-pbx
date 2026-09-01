<script setup lang="ts">
import { LanguageIcon, PhoneIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type { ExtensionUserConfiguration } from '../types/extension'
import type { ListboxOptionValue } from '@/shared/components/FormListbox.vue'

defineProps<{
  fieldErrors: Record<string, string[]>
  languageOptions: ListboxOptionValue[]
  presenceOptions: ListboxOptionValue[]
  section: 'presence-id' | 'options'
}>()
const configuration = defineModel<ExtensionUserConfiguration>({ required: true })
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
      <span
        class="grid size-9 place-items-center rounded-md"
        :class="
          section === 'presence-id' ? 'bg-indigo-50 text-indigo-600' : 'bg-cyan-50 text-cyan-600'
        "
      >
        <component :is="section === 'presence-id' ? PhoneIcon : ShieldCheckIcon" class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">
          {{ section === 'presence-id' ? 'Presence identity' : 'User calling options' }}
        </h2>
        <p class="text-[10px] text-slate-400">
          {{
            section === 'presence-id'
              ? 'The account-scoped identity used for presence subscriptions.'
              : 'Public Switch settings applied to this user.'
          }}
        </p>
      </div>
    </header>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
      <label v-if="section === 'presence-id'" class="grid gap-2 sm:max-w-sm">
        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
          <PhoneIcon class="size-4" /> Presence ID
        </span>
        <FormListbox
          v-model="configuration.presence_id"
          :options="presenceOptions"
          :invalid="Boolean(fieldErrors.presence_id)"
          aria-label="Presence ID"
        />
        <span v-if="fieldErrors.presence_id" class="text-[10px] text-danger">{{
          fieldErrors.presence_id[0]
        }}</span>
      </label>

      <template v-else>
        <label class="grid gap-2">
          <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
            <LanguageIcon class="size-4" /> Language
          </span>
          <FormListbox
            v-model="configuration.language"
            :options="languageOptions"
            :invalid="Boolean(fieldErrors.language)"
            aria-label="Language"
          />
          <span v-if="fieldErrors.language" class="text-[10px] text-danger">{{
            fieldErrors.language[0]
          }}</span>
        </label>

        <ToggleSwitch
          v-model="configuration.call_waiting.enabled"
          label="Call waiting"
          description="Allow another call while the user is busy"
          class="rounded-md border border-slate-200 px-3 py-2.5"
          :class="validationControlClass(fieldErrors['call_waiting.enabled'])"
          :invalid="Boolean(fieldErrors['call_waiting.enabled'])"
        />
        <ToggleSwitch
          v-model="configuration.do_not_disturb.enabled"
          label="Do not disturb"
          description="Send calls through the unavailable branch"
          class="rounded-md border border-slate-200 px-3 py-2.5"
          :class="validationControlClass(fieldErrors['do_not_disturb.enabled'])"
          :invalid="Boolean(fieldErrors['do_not_disturb.enabled'])"
        />
        <ToggleSwitch
          v-model="configuration.contact_list.exclude"
          label="Hide from directory contacts"
          description="Exclude this user from generated contact lists"
          class="rounded-md border border-slate-200 px-3 py-2.5"
          :class="validationControlClass(fieldErrors['contact_list.exclude'])"
          :invalid="Boolean(fieldErrors['contact_list.exclude'])"
        />

        <label class="grid gap-2">
          <span class="text-xs font-semibold text-slate-600">Outbound caller-ID privacy</span>
          <FormListbox
            v-model="configuration.caller_id_options.outbound_privacy"
            :invalid="Boolean(fieldErrors['caller_id_options.outbound_privacy'])"
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
      </template>
    </div>
  </article>
</template>
