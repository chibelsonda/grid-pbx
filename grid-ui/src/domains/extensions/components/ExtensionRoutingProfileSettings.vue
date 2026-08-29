<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import {
  ChevronDownIcon,
  IdentificationIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { useDelimitedStringList } from '@/shared/forms/useDelimitedStringList'
import { validationControlClass } from '@/shared/forms/validationStyles'
import type {
  ExtensionProfileAddressType,
  ExtensionUpdate,
} from '../types/extension'

type Model = Pick<
  ExtensionUpdate,
  'dial_plan' | 'formatters' | 'profile' | 'pronounced_name'
>

const props = defineProps<{
  fieldErrors: Record<string, string[]>
  mediaOptions: Array<{ id: string; name: string | null }>
  policy: {
    verified: boolean
    privilege: 'user' | 'admin' | null
    feature_level: string | null
    external_flag_count: number
  }
}>()
const settings = defineModel<Model>({ required: true })
const systemDialPlans = useDelimitedStringList(
  () => settings.value.dial_plan.system,
  (values) => (settings.value.dial_plan.system = values),
)
const nicknames = useDelimitedStringList(
  () => settings.value.profile.nicknames,
  (values) => (settings.value.profile.nicknames = values),
)
const addressTypes: Array<{ value: ExtensionProfileAddressType; label: string }> = [
  { value: 'home', label: 'Home' },
  { value: 'work', label: 'Work' },
  { value: 'postal', label: 'Postal' },
  { value: 'intl', label: 'International' },
  { value: 'parcel', label: 'Parcel' },
  { value: 'dom', label: 'Domestic' },
  { value: 'pref', label: 'Preferred' },
]

function error(path: string): string | null {
  return props.fieldErrors[path]?.[0] ?? null
}

function addDialPlanRule(): void {
  settings.value.dial_plan.rules.push({ pattern: '', description: null, prefix: null, suffix: null })
}

function addFormatter(): void {
  settings.value.formatters.push({
    field: '',
    direction: 'both',
    match_invite_format: false,
    prefix: null,
    regex: null,
    strip: false,
    suffix: null,
    value: null,
  })
}

function addAddress(): void {
  settings.value.profile.addresses.push({ address: '', types: [] })
}

function toggleAddressType(index: number, type: ExtensionProfileAddressType): void {
  const address = settings.value.profile.addresses[index]
  if (!address) return

  address.types = address.types.includes(type)
    ? address.types.filter((value) => value !== type)
    : [...address.types, type]
}

function selectPronouncedName(value: unknown): void {
  settings.value.pronounced_name.media_id = typeof value === 'string' ? value : null
  settings.value.pronounced_name.preserve_media = false
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
      <span class="grid size-9 place-items-center rounded-md bg-violet-50 text-violet-600">
        <IdentificationIcon class="size-5" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-700">Routing and directory profile</h2>
        <p class="text-[10px] leading-4 text-slate-500">
          Advanced User dial transformations, profile metadata, and spoken-name media.
        </p>
      </div>
    </header>

    <div class="grid gap-3 p-5">
      <Disclosure v-slot="{ open }">
        <div class="rounded-md border border-slate-200">
          <DisclosureButton
            class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
          >
            Dial plan
            <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
          </DisclosureButton>
          <DisclosurePanel class="grid gap-4 border-t border-slate-200 p-4">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">System dial plans</span>
              <input
                v-model="systemDialPlans"
                class="field-control"
                :class="validationControlClass(error('dial_plan.system'))"
                :aria-invalid="Boolean(error('dial_plan.system'))"
                placeholder="System plan names, comma separated"
              />
              <span v-if="error('dial_plan.system')" class="text-[10px] text-danger">{{
                error('dial_plan.system')
              }}</span>
            </label>
            <div class="flex items-center justify-between gap-3">
              <p class="text-[10px] text-slate-500">Rules modify locally dialed numbers.</p>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                @click="addDialPlanRule"
              >
                <PlusIcon class="size-3.5" /> Add rule
              </button>
            </div>
            <section
              v-for="(rule, index) in settings.dial_plan.rules"
              :key="index"
              class="grid gap-3 rounded-md border border-slate-200 p-4 sm:grid-cols-2"
            >
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Regex pattern</span>
                <input
                  v-model="rule.pattern"
                  maxlength="512"
                  class="field-control font-mono"
                  :class="validationControlClass(error(`dial_plan.rules.${index}.pattern`))"
                  :aria-invalid="Boolean(error(`dial_plan.rules.${index}.pattern`))"
                />
                <span
                  v-if="error(`dial_plan.rules.${index}.pattern`)"
                  class="text-[10px] text-danger"
                  >{{ error(`dial_plan.rules.${index}.pattern`) }}</span
                >
              </label>
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Description</span>
                <input
                  v-model="rule.description"
                  maxlength="255"
                  class="field-control"
                  :class="validationControlClass(error(`dial_plan.rules.${index}.description`))"
                  :aria-invalid="Boolean(error(`dial_plan.rules.${index}.description`))"
                />
              </label>
              <label v-for="key in ['prefix', 'suffix'] as const" :key="key" class="grid gap-2">
                <span class="text-xs font-semibold capitalize text-slate-600">{{ key }}</span>
                <input
                  v-model="rule[key]"
                  maxlength="64"
                  class="field-control"
                  :class="validationControlClass(error(`dial_plan.rules.${index}.${key}`))"
                  :aria-invalid="Boolean(error(`dial_plan.rules.${index}.${key}`))"
                />
              </label>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-1 rounded-md border border-red-200 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50 sm:col-span-2"
                @click="settings.dial_plan.rules.splice(index, 1)"
              >
                <TrashIcon class="size-3.5" /> Remove rule
              </button>
            </section>
          </DisclosurePanel>
        </div>
      </Disclosure>

      <Disclosure v-slot="{ open }">
        <div class="rounded-md border border-slate-200">
          <DisclosureButton
            class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
          >
            Request formatters
            <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
          </DisclosureButton>
          <DisclosurePanel class="grid gap-4 border-t border-slate-200 p-4">
            <div class="flex items-start justify-between gap-4">
              <p class="text-[10px] leading-4 text-slate-500">
                Transform bounded Switch request fields without raw JSON.
              </p>
              <button
                type="button"
                class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                @click="addFormatter"
              >
                <PlusIcon class="size-3.5" /> Add formatter
              </button>
            </div>
            <section
              v-for="(formatter, index) in settings.formatters"
              :key="index"
              class="grid gap-3 rounded-md border border-slate-200 p-4 sm:grid-cols-3"
            >
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Switch field</span>
                <input
                  v-model="formatter.field"
                  maxlength="128"
                  class="field-control font-mono"
                  :class="validationControlClass(error(`formatters.${index}.field`))"
                  :aria-invalid="Boolean(error(`formatters.${index}.field`))"
                />
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Direction</span>
                <FormListbox
                  v-model="formatter.direction"
                  :invalid="Boolean(error(`formatters.${index}.direction`))"
                  :options="[
                    { value: null, label: 'Switch default' },
                    { value: 'both', label: 'Both directions' },
                    { value: 'inbound', label: 'Inbound' },
                    { value: 'outbound', label: 'Outbound' },
                  ]"
                />
              </label>
              <label
                v-for="control in [
                  { key: 'regex', label: 'Match regex', maximum: 2048 },
                  { key: 'value', label: 'Fixed value', maximum: 1024 },
                  { key: 'prefix', label: 'Prefix', maximum: 1024 },
                  { key: 'suffix', label: 'Suffix', maximum: 1024 },
                ] as const"
                :key="control.key"
                class="grid gap-2"
              >
                <span class="text-xs font-semibold text-slate-600">{{ control.label }}</span>
                <input
                  v-model="formatter[control.key]"
                  :maxlength="control.maximum"
                  class="field-control"
                  :class="validationControlClass(error(`formatters.${index}.${control.key}`))"
                  :aria-invalid="Boolean(error(`formatters.${index}.${control.key}`))"
                />
              </label>
              <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                <ToggleSwitch v-model="formatter.strip" label="Strip matched value" />
                <ToggleSwitch
                  v-model="formatter.match_invite_format"
                  label="Match INVITE format"
                />
              </div>
              <button
                type="button"
                class="inline-flex items-center justify-center gap-1 rounded-md border border-red-200 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50"
                @click="settings.formatters.splice(index, 1)"
              >
                <TrashIcon class="size-3.5" /> Remove
              </button>
            </section>
          </DisclosurePanel>
        </div>
      </Disclosure>

      <Disclosure v-slot="{ open }">
        <div class="rounded-md border border-slate-200">
          <DisclosureButton
            class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-slate-700"
          >
            Directory profile and spoken name
            <ChevronDownIcon class="size-4 transition" :class="open && 'rotate-180'" />
          </DisclosureButton>
          <DisclosurePanel class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2">
            <label
              v-for="field in [
                { key: 'title', label: 'Title', maximum: 255 },
                { key: 'role', label: 'Profile role', maximum: 255 },
                { key: 'assistant', label: 'Assistant', maximum: 255 },
                { key: 'birthday', label: 'Birthday', maximum: 64 },
                { key: 'sort_string', label: 'Sort string', maximum: 255 },
              ] as const"
              :key="field.key"
              class="grid gap-2"
            >
              <span class="text-xs font-semibold text-slate-600">{{ field.label }}</span>
              <input
                v-model="settings.profile[field.key]"
                :maxlength="field.maximum"
                class="field-control"
                :class="validationControlClass(error(`profile.${field.key}`))"
                :aria-invalid="Boolean(error(`profile.${field.key}`))"
              />
            </label>
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Nicknames</span>
              <input
                v-model="nicknames"
                class="field-control"
                :class="validationControlClass(error('profile.nicknames'))"
                :aria-invalid="Boolean(error('profile.nicknames'))"
                placeholder="Comma separated"
              />
            </label>
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Profile note</span>
              <textarea
                v-model="settings.profile.note"
                maxlength="2000"
                rows="3"
                class="field-control h-auto py-3"
                :class="validationControlClass(error('profile.note'))"
                :aria-invalid="Boolean(error('profile.note'))"
              />
            </label>
            <div class="grid gap-3 sm:col-span-2">
              <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-slate-600">Addresses</span>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                  @click="addAddress"
                >
                  <PlusIcon class="size-3.5" /> Add address
                </button>
              </div>
              <section
                v-for="(address, index) in settings.profile.addresses"
                :key="index"
                class="grid gap-3 rounded-md border border-slate-200 p-4"
              >
                <textarea
                  v-model="address.address"
                  maxlength="512"
                  rows="2"
                  class="field-control h-auto py-3"
                  :class="validationControlClass(error(`profile.addresses.${index}.address`))"
                  :aria-invalid="Boolean(error(`profile.addresses.${index}.address`))"
                  :aria-label="`Profile address ${index + 1}`"
                />
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="type in addressTypes"
                    :key="type.value"
                    type="button"
                    :aria-pressed="address.types.includes(type.value)"
                    class="rounded-md border px-2.5 py-1.5 text-[10px] font-semibold transition"
                    :class="
                      address.types.includes(type.value)
                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                        : 'border-slate-200 text-slate-600'
                    "
                    @click="toggleAddressType(index, type.value)"
                  >
                    {{ type.label }}
                  </button>
                </div>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-1 rounded-md border border-red-200 px-3 py-2 text-[11px] font-semibold text-danger hover:bg-red-50"
                  @click="settings.profile.addresses.splice(index, 1)"
                >
                  <TrashIcon class="size-3.5" /> Remove address
                </button>
              </section>
            </div>
            <div class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Pronounced-name media</span>
              <FormListbox
                :model-value="settings.pronounced_name.media_id"
                :options="[
                  { value: null, label: 'No pronounced-name media' },
                  ...mediaOptions.map((media) => ({
                    value: media.id,
                    label: media.name || 'Untitled media',
                  })),
                ]"
                :invalid="Boolean(error('pronounced_name.media_id'))"
                aria-label="Select pronounced-name media"
                @update:model-value="selectPronouncedName"
              />
              <div
                v-if="settings.pronounced_name.preserve_media"
                class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] leading-4 text-amber-800"
              >
                The current spoken-name media is not projected locally.
                <ToggleSwitch
                  v-model="settings.pronounced_name.preserve_media"
                  class="mt-2"
                  label="Preserve unresolved spoken name"
                />
              </div>
            </div>
          </DisclosurePanel>
        </div>
      </Disclosure>

      <aside class="rounded-md border border-slate-200 bg-slate-50 p-4 text-[10px] leading-4 text-slate-600">
        <p class="font-semibold text-slate-700">Switch-managed policy</p>
        <p class="mt-1">
          Verified: {{ policy.verified ? 'yes' : 'no' }} · Privilege:
          {{ policy.privilege ?? 'not reported' }} · Feature level:
          {{ policy.feature_level ?? 'not reported' }} · External flags:
          {{ policy.external_flag_count }}
        </p>
        <p class="mt-1">These values are read-only here and are not submitted by the form.</p>
      </aside>
    </div>
  </article>
</template>
