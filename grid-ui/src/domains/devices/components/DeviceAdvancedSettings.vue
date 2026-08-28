<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import {
  BellAlertIcon,
  IdentificationIcon,
  MicrophoneIcon,
  MusicalNoteIcon,
  PhoneArrowUpRightIcon,
  ShieldExclamationIcon,
  SignalIcon,
  Squares2X2Icon,
  VideoCameraIcon,
} from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import { validationControlClass } from '@/shared/forms/validationStyles'
import {
  audioCodecs,
  deviceAdvancedTabForError,
  deviceSupportsTab,
  supportsDeviceNotifications,
  supportsDeviceRecording,
  supportsVideo,
  usesForwarding,
  usesSip,
  videoCodecs,
} from '../deviceForm'
import type { DeviceConfiguration, DeviceRestrictionOption, DeviceType } from '../types/device'
import DeviceRecordingSettings from './DeviceRecordingSettings.vue'
import DeviceRoutingSettings from './DeviceRoutingSettings.vue'

const props = defineProps<{
  deviceType: DeviceType
  fieldErrors: Record<string, string[]>
  firstErrorField: string | null
  isEditing: boolean
  restrictionOptions: DeviceRestrictionOption[]
  mediaOptions: Array<{ id: string; name: string | null }>
}>()
const configuration = defineModel<DeviceConfiguration>({ required: true })
const callerIdScopes = ['internal', 'external', 'emergency'] as const
const selectedTabIndex = ref(0)

const tabs = computed(() => [
  { key: 'basic', label: 'Basic', icon: Squares2X2Icon },
  ...(deviceSupportsTab(props.deviceType, 'caller-id')
    ? [{ key: 'caller-id', label: 'Caller ID', icon: IdentificationIcon }]
    : []),
  ...(deviceSupportsTab(props.deviceType, 'sip')
    ? [
        {
          key: 'sip',
          label: props.deviceType === 'smartphone' ? 'Wi-Fi calling' : 'SIP',
          icon: SignalIcon,
        },
      ]
    : []),
  ...(deviceSupportsTab(props.deviceType, 'audio')
    ? [{ key: 'audio', label: 'Audio', icon: MusicalNoteIcon }]
    : []),
  ...(deviceSupportsTab(props.deviceType, 'video')
    ? [{ key: 'video', label: 'Video', icon: VideoCameraIcon }]
    : []),
  { key: 'options', label: 'Options', icon: PhoneArrowUpRightIcon },
  ...(deviceSupportsTab(props.deviceType, 'restrictions')
    ? [{ key: 'restrictions', label: 'Restrictions', icon: ShieldExclamationIcon }]
    : []),
])

const restrictionRows = computed(() => {
  const rows = new Map(props.restrictionOptions.map((option) => [option.key, option] as const))

  for (const key of Object.keys(configuration.value.call_restriction)) {
    if (key === 'closed_groups' || rows.has(key)) continue
    rows.set(key, {
      key,
      label: key
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' '),
      emergency: false,
    })
  }

  return [...rows.values()]
})

watch(
  [() => props.firstErrorField, tabs],
  ([field, availableTabs]) => {
    if (!field) return
    const index = availableTabs.findIndex(
      ({ key }) => key === deviceAdvancedTabForError(field, props.deviceType),
    )

    if (index >= 0) selectedTabIndex.value = index
  },
  { immediate: true },
)

function selectTab(index: number): void {
  selectedTabIndex.value = index
}

watch(
  () => props.deviceType,
  () => {
    selectedTabIndex.value = 0
  },
)

function error(field: string): string | null {
  return props.fieldErrors[field]?.[0] ?? null
}

function invalidClass(field: string): string {
  return validationControlClass(error(field))
}

function restrictionAction(key: string): 'inherit' | 'deny' {
  return configuration.value.call_restriction[key]?.action ?? 'inherit'
}

function setRestrictionAction(key: string, action: unknown): void {
  if (action !== 'inherit' && action !== 'deny') return
  configuration.value.call_restriction[key] = { action }
}

function toggleCodec(kind: 'audio' | 'video', codec: string): void {
  const codecs = configuration.value.media[kind].codecs
  const index = codecs.indexOf(codec)

  if (index === -1) codecs.push(codec)
  else codecs.splice(index, 1)
}

function codecSelected(kind: 'audio' | 'video', codec: string): boolean {
  return configuration.value.media[kind].codecs.includes(codec)
}

function toggleEncryptionMethod(method: string): void {
  const methods = configuration.value.media.encryption.methods
  const index = methods.indexOf(method)

  if (index === -1) methods.push(method)
  else methods.splice(index, 1)
}
</script>

<template>
  <article class="card-surface overflow-hidden">
    <TabGroup :selected-index="selectedTabIndex" @change="selectTab">
      <TabList
        class="flex gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50/70 px-4 pt-3"
      >
        <Tab v-for="tab in tabs" :key="tab.key" v-slot="{ selected }" as="template">
          <button
            type="button"
            class="inline-flex h-10 shrink-0 items-center gap-2 border-b-2 px-3 text-xs font-semibold outline-none transition"
            :class="
              selected
                ? 'border-brand-500 text-brand-700'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            "
          >
            <component :is="tab.icon" class="size-4" />
            {{ tab.label }}
          </button>
        </Tab>
      </TabList>

      <TabPanels class="p-5">
        <TabPanel class="outline-none">
          <slot name="basic" />
        </TabPanel>

        <TabPanel v-if="deviceSupportsTab(deviceType, 'caller-id')" class="grid gap-5 outline-none">
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Presence ID</span>
            <input
              v-model="configuration.presence_id"
              maxlength="255"
              class="field-control"
              :class="invalidClass('presence_id')"
              :aria-invalid="Boolean(error('presence_id'))"
              placeholder="Use the SIP username when empty"
            />
            <span v-if="error('presence_id')" class="text-[11px] text-danger">
              {{ error('presence_id') }}
            </span>
          </label>
          <section
            v-for="scope in callerIdScopes"
            :key="scope"
            class="grid gap-4 rounded-md border border-slate-100 p-4 sm:grid-cols-2"
          >
            <h3 class="text-xs font-semibold capitalize text-slate-700 sm:col-span-2">
              {{ scope }} caller ID
            </h3>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Name</span>
              <input
                v-model="configuration.caller_id[scope].name"
                maxlength="35"
                class="field-control"
                :class="invalidClass(`caller_id.${scope}.name`)"
                :aria-invalid="Boolean(error(`caller_id.${scope}.name`))"
              />
              <span v-if="error(`caller_id.${scope}.name`)" class="text-[11px] text-danger">{{
                error(`caller_id.${scope}.name`)
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Number</span>
              <input
                v-model="configuration.caller_id[scope].number"
                maxlength="35"
                class="field-control"
                :class="invalidClass(`caller_id.${scope}.number`)"
                :aria-invalid="Boolean(error(`caller_id.${scope}.number`))"
              />
              <span v-if="error(`caller_id.${scope}.number`)" class="text-[11px] text-danger">{{
                error(`caller_id.${scope}.number`)
              }}</span>
            </label>
          </section>
          <section class="grid gap-4 rounded-md border border-slate-100 p-4 sm:grid-cols-2">
            <h3 class="text-xs font-semibold text-slate-700 sm:col-span-2">Asserted identity</h3>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Name</span>
              <input
                v-model="configuration.caller_id.asserted.name"
                maxlength="35"
                class="field-control"
                :class="invalidClass('caller_id.asserted.name')"
                :aria-invalid="Boolean(error('caller_id.asserted.name'))"
              />
              <span v-if="error('caller_id.asserted.name')" class="text-[11px] text-danger">
                {{ error('caller_id.asserted.name') }}
              </span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Number</span>
              <input
                v-model="configuration.caller_id.asserted.number"
                maxlength="35"
                class="field-control"
                :class="invalidClass('caller_id.asserted.number')"
                :aria-invalid="Boolean(error('caller_id.asserted.number'))"
              />
              <span v-if="error('caller_id.asserted.number')" class="text-[11px] text-danger">
                {{ error('caller_id.asserted.number') }}
              </span>
            </label>
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Realm</span>
              <input
                v-model="configuration.caller_id.asserted.realm"
                maxlength="253"
                class="field-control"
                :class="invalidClass('caller_id.asserted.realm')"
                :aria-invalid="Boolean(error('caller_id.asserted.realm'))"
                placeholder="Use the account realm when empty"
              />
              <span v-if="error('caller_id.asserted.realm')" class="text-[11px] text-danger">
                {{ error('caller_id.asserted.realm') }}
              </span>
            </label>
          </section>
          <label class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Outbound privacy</span>
            <FormListbox
              v-model="configuration.caller_id_options.outbound_privacy"
              :invalid="Boolean(error('caller_id_options.outbound_privacy'))"
              :options="[
                { value: 'none', label: 'Show name and number' },
                { value: 'name', label: 'Hide name' },
                { value: 'number', label: 'Hide number' },
                { value: 'full', label: 'Hide name and number' },
              ]"
            />
            <span
              v-if="error('caller_id_options.outbound_privacy')"
              class="text-[11px] text-danger"
              >{{ error('caller_id_options.outbound_privacy') }}</span
            >
          </label>
        </TabPanel>

        <template v-if="deviceSupportsTab(deviceType, 'sip')">
          <TabPanel class="grid gap-5 outline-none sm:grid-cols-2">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Authentication method</span>
              <FormListbox
                v-model="configuration.sip.method"
                :invalid="Boolean(error('sip.method'))"
                :options="[
                  { value: 'password', label: 'Username and password' },
                  { value: 'ip', label: 'IP authentication' },
                ]"
              />
              <span v-if="error('sip.method')" class="text-[11px] text-danger">{{
                error('sip.method')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Invite format</span>
              <FormListbox
                v-model="configuration.sip.invite_format"
                :invalid="Boolean(error('sip.invite_format'))"
                :options="[
                  { value: 'contact', label: 'Contact' },
                  { value: 'username', label: 'Username' },
                  { value: 'npan', label: 'NPAN' },
                  { value: '1npan', label: '1NPAN' },
                  { value: 'e164', label: 'E.164' },
                  { value: 'route', label: 'Route' },
                ]"
              />
              <span v-if="error('sip.invite_format')" class="text-[11px] text-danger">{{
                error('sip.invite_format')
              }}</span>
            </label>
            <template v-if="configuration.sip.method === 'password'">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">SIP username</span>
                <input
                  v-model="configuration.sip.username"
                  maxlength="32"
                  autocomplete="off"
                  class="field-control"
                  :class="invalidClass('sip.username')"
                  :aria-invalid="Boolean(error('sip.username'))"
                  :placeholder="
                    configuration.sip.username_configured ? 'Configured — enter to replace' : ''
                  "
                />
                <span v-if="error('sip.username')" class="text-[11px] text-danger">{{
                  error('sip.username')
                }}</span>
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">{{
                  isEditing ? 'New SIP password' : 'SIP password'
                }}</span>
                <input
                  v-model="configuration.sip.password"
                  type="password"
                  minlength="12"
                  maxlength="32"
                  autocomplete="new-password"
                  class="field-control"
                  :class="invalidClass('sip.password')"
                  :aria-invalid="Boolean(error('sip.password'))"
                  :placeholder="isEditing ? 'Leave blank to keep current password' : ''"
                />
                <span v-if="error('sip.password')" class="text-[11px] text-danger">{{
                  error('sip.password')
                }}</span>
              </label>
            </template>
            <label v-else class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Authorized IP address</span>
              <input
                v-model="configuration.sip.ip"
                class="field-control font-mono"
                :class="invalidClass('sip.ip')"
                :aria-invalid="Boolean(error('sip.ip'))"
                placeholder="203.0.113.10"
              />
              <span v-if="error('sip.ip')" class="text-[11px] text-danger">{{
                error('sip.ip')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Realm override</span>
              <input
                v-model="configuration.sip.realm"
                maxlength="253"
                class="field-control"
                :class="invalidClass('sip.realm')"
                :aria-invalid="Boolean(error('sip.realm'))"
                placeholder="Use account realm"
              />
              <span v-if="error('sip.realm')" class="text-[11px] text-danger">{{
                error('sip.realm')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Registration period</span>
              <input
                v-model.number="configuration.sip.expire_seconds"
                type="number"
                min="30"
                max="86400"
                class="field-control"
                :class="invalidClass('sip.expire_seconds')"
                :aria-invalid="Boolean(error('sip.expire_seconds'))"
              />
              <span v-if="error('sip.expire_seconds')" class="text-[11px] text-danger">{{
                error('sip.expire_seconds')
              }}</span>
            </label>
            <label
              v-if="configuration.sip.invite_format === 'route' || deviceType === 'sip_uri'"
              class="grid gap-2 sm:col-span-2"
            >
              <span class="text-xs font-semibold text-slate-600">SIP route</span>
              <input
                v-model="configuration.sip.route"
                maxlength="2048"
                class="field-control font-mono"
                :class="invalidClass('sip.route')"
                :aria-invalid="Boolean(error('sip.route'))"
                placeholder="sip:user@example.com"
              />
              <span v-if="error('sip.route')" class="text-[11px] text-danger">{{
                error('sip.route')
              }}</span>
            </label>
            <label
              v-if="['npan', '1npan', 'e164'].includes(configuration.sip.invite_format)"
              class="grid gap-2 sm:col-span-2"
            >
              <span class="text-xs font-semibold text-slate-600">Invite number</span>
              <input
                v-model="configuration.sip.number"
                maxlength="64"
                class="field-control"
                :class="invalidClass('sip.number')"
                :aria-invalid="Boolean(error('sip.number'))"
                placeholder="Uses the dialed number when empty"
              />
              <span v-if="error('sip.number')" class="text-[11px] text-danger">{{
                error('sip.number')
              }}</span>
            </label>
            <label class="grid gap-2 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-600">Static inbound route</span>
              <input
                v-model="configuration.sip.static_route"
                maxlength="2048"
                class="field-control font-mono"
                :class="invalidClass('sip.static_route')"
                :aria-invalid="Boolean(error('sip.static_route'))"
                placeholder="Optional"
              />
              <span v-if="error('sip.static_route')" class="text-[11px] text-danger">{{
                error('sip.static_route')
              }}</span>
            </label>
            <ToggleSwitch
              v-model="configuration.sip.ignore_completed_elsewhere"
              label="Ignore completed elsewhere"
              description="Do not mark group calls answered elsewhere as missed"
              class="rounded-md border border-slate-200 px-3 py-2.5 sm:col-span-2"
            />
          </TabPanel>
        </template>

        <template v-if="deviceSupportsTab(deviceType, 'audio')">
          <TabPanel class="grid gap-6 outline-none">
            <section class="grid gap-3">
              <div>
                <h3 class="text-xs font-semibold text-slate-700">Audio codecs</h3>
                <p class="mt-1 text-[10px] text-slate-400">
                  Selected codecs are sent in the order shown.
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="codec in audioCodecs"
                  :key="codec"
                  type="button"
                  :aria-pressed="codecSelected('audio', codec)"
                  class="rounded-md border px-3 py-2 text-xs font-semibold transition"
                  :class="
                    codecSelected('audio', codec)
                      ? 'border-brand-500 bg-brand-50 text-brand-700'
                      : 'border-slate-200 text-slate-500 hover:border-slate-300'
                  "
                  @click="toggleCodec('audio', codec)"
                >
                  {{ codec }}
                </button>
              </div>
              <span v-if="error('media.audio.codecs')" class="text-[11px] text-danger">{{
                error('media.audio.codecs')
              }}</span>
            </section>
            <div class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Bypass media</span>
                <FormListbox
                  v-model="configuration.media.bypass_media"
                  :invalid="Boolean(error('media.bypass_media'))"
                  :options="[
                    { value: false, label: 'Disabled' },
                    { value: true, label: 'Enabled' },
                    { value: 'auto', label: 'Automatic' },
                  ]"
                />
                <span v-if="error('media.bypass_media')" class="text-[11px] text-danger">{{
                  error('media.bypass_media')
                }}</span>
              </label>
              <ToggleSwitch
                v-model="configuration.media.encryption.enforce_security"
                label="Require media encryption"
              />
              <div
                v-if="configuration.media.encryption.enforce_security"
                class="grid gap-2 sm:col-span-2"
              >
                <span class="text-xs font-semibold text-slate-600">Encryption methods</span>
                <div class="flex gap-2">
                  <button
                    v-for="method in ['srtp', 'zrtp']"
                    :key="method"
                    type="button"
                    :aria-pressed="configuration.media.encryption.methods.includes(method)"
                    class="rounded-md border px-3 py-2 text-xs font-semibold uppercase transition"
                    :class="
                      configuration.media.encryption.methods.includes(method)
                        ? 'border-brand-500 bg-brand-50 text-brand-700'
                        : 'border-slate-200 text-slate-500 hover:border-slate-300'
                    "
                    @click="toggleEncryptionMethod(method)"
                  >
                    {{ method }}
                  </button>
                </div>
                <span v-if="error('media.encryption.methods')" class="text-[11px] text-danger">{{
                  error('media.encryption.methods')
                }}</span>
              </div>
              <ToggleSwitch
                v-if="deviceType === 'fax' || deviceType === 'ata'"
                v-model="configuration.media.fax_option"
                label="Enable T.38 fax"
              />
              <ToggleSwitch
                v-model="configuration.media.ignore_early_media"
                label="Ignore early media"
              />
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Progress timeout (seconds)</span>
                <input
                  v-model.number="configuration.media.progress_timeout"
                  type="number"
                  min="0"
                  max="3600"
                  class="field-control"
                  :class="invalidClass('media.progress_timeout')"
                  :aria-invalid="Boolean(error('media.progress_timeout'))"
                />
                <span v-if="error('media.progress_timeout')" class="text-[11px] text-danger">{{
                  error('media.progress_timeout')
                }}</span>
              </label>
            </div>
          </TabPanel>
        </template>

        <template v-if="deviceSupportsTab(deviceType, 'video') && supportsVideo(deviceType)">
          <TabPanel class="grid gap-3 outline-none">
            <div>
              <h3 class="text-xs font-semibold text-slate-700">Video codecs</h3>
              <p class="mt-1 text-[10px] text-slate-400">
                Select the video formats this endpoint can negotiate.
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="codec in videoCodecs"
                :key="codec"
                type="button"
                :aria-pressed="codecSelected('video', codec)"
                class="rounded-md border px-3 py-2 text-xs font-semibold transition"
                :class="
                  codecSelected('video', codec)
                    ? 'border-brand-500 bg-brand-50 text-brand-700'
                    : 'border-slate-200 text-slate-500 hover:border-slate-300'
                "
                @click="toggleCodec('video', codec)"
              >
                {{ codec }}
              </button>
            </div>
            <span v-if="error('media.video.codecs')" class="text-[11px] text-danger">
              {{ error('media.video.codecs') }}
            </span>
          </TabPanel>
        </template>

        <TabPanel class="grid gap-4 outline-none sm:grid-cols-2">
          <template v-if="usesForwarding(deviceType)">
            <ToggleSwitch
              v-model="configuration.call_forward.enabled"
              label="Enable call forwarding"
            />
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Forwarding number</span>
              <input
                v-model="configuration.call_forward.number"
                maxlength="15"
                class="field-control"
                :class="invalidClass('call_forward.number')"
                :aria-invalid="Boolean(error('call_forward.number'))"
              />
              <span v-if="error('call_forward.number')" class="text-[11px] text-danger">{{
                error('call_forward.number')
              }}</span>
            </label>
            <ToggleSwitch
              v-model="configuration.call_forward.require_keypress"
              label="Require keypress"
              description="Callee presses 1 to accept"
            />
            <ToggleSwitch
              v-model="configuration.call_forward.keep_caller_id"
              label="Keep original caller ID"
            />
            <ToggleSwitch
              v-model="configuration.call_forward.direct_calls_only"
              label="Direct calls only"
            />
            <ToggleSwitch
              v-model="configuration.call_forward.failover"
              label="Forward only when offline"
            />
            <ToggleSwitch
              v-model="configuration.call_forward.ignore_early_media"
              label="Ignore early media"
            />
            <ToggleSwitch
              v-model="configuration.call_forward.substitute"
              label="Replace this device"
            />
            <ToggleSwitch
              v-model="configuration.contact_list.exclude"
              label="Hide from contact list"
            />
          </template>
          <ToggleSwitch
            v-if="deviceType === 'fax' || deviceType === 'ata'"
            v-model="configuration.media.fax_option"
            label="Enable T.38 fax"
            description="Negotiate fax media for this endpoint"
          />
          <ToggleSwitch v-model="configuration.call_waiting.enabled" label="Call waiting" />
          <ToggleSwitch v-model="configuration.do_not_disturb.enabled" label="Do not disturb" />
          <ToggleSwitch v-model="configuration.exclude_from_queues" label="Exclude from queues" />
          <label v-if="!deviceSupportsTab(deviceType, 'caller-id')" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-600">Presence ID</span>
            <input
              v-model="configuration.presence_id"
              maxlength="255"
              class="field-control"
              :class="invalidClass('presence_id')"
              :aria-invalid="Boolean(error('presence_id'))"
            />
            <span v-if="error('presence_id')" class="text-[11px] text-danger">{{
              error('presence_id')
            }}</span>
          </label>
          <section
            v-if="supportsDeviceRecording(deviceType)"
            class="grid gap-4 border-t border-slate-100 pt-5 sm:col-span-2"
          >
            <div class="flex items-center gap-2">
              <MicrophoneIcon class="size-4 text-brand-500" />
              <h3 class="text-xs font-semibold text-slate-700">Call recording</h3>
            </div>
            <DeviceRecordingSettings
              v-model="configuration.call_recording"
              :field-errors="fieldErrors"
            />
          </section>
          <section
            v-if="supportsDeviceNotifications(deviceType)"
            class="grid gap-5 border-t border-slate-100 pt-5 sm:col-span-2 sm:grid-cols-2"
          >
            <div class="flex items-center gap-2 sm:col-span-2">
              <BellAlertIcon class="size-4 text-brand-500" />
              <h3 class="text-xs font-semibold text-slate-700">Notifications and locale</h3>
            </div>
            <ToggleSwitch
              v-model="configuration.mwi_unsolicited_updates"
              label="Unsolicited MWI updates"
            />
            <ToggleSwitch
              v-model="configuration.register_overwrite_notify"
              label="Registration overwrite notifications"
            />
            <ToggleSwitch
              :model-value="!configuration.suppress_unregister_notifications"
              label="Unregistration notifications"
              @update:model-value="configuration.suppress_unregister_notifications = !$event"
            />
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Language</span>
              <input
                v-model="configuration.language"
                maxlength="32"
                class="field-control"
                :class="invalidClass('language')"
                :aria-invalid="Boolean(error('language'))"
                placeholder="Account default"
              />
              <span v-if="error('language')" class="text-[11px] text-danger">{{
                error('language')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Timezone</span>
              <input
                v-model="configuration.timezone"
                class="field-control"
                :class="invalidClass('timezone')"
                :aria-invalid="Boolean(error('timezone'))"
                placeholder="Account default"
              />
              <span v-if="error('timezone')" class="text-[11px] text-danger">{{
                error('timezone')
              }}</span>
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Internal ringtone header</span>
              <input
                v-model="configuration.ringtones.internal"
                maxlength="256"
                class="field-control"
                :class="invalidClass('ringtones.internal')"
                :aria-invalid="Boolean(error('ringtones.internal'))"
              />
            </label>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">External ringtone header</span>
              <input
                v-model="configuration.ringtones.external"
                maxlength="256"
                class="field-control"
                :class="invalidClass('ringtones.external')"
                :aria-invalid="Boolean(error('ringtones.external'))"
              />
            </label>
          </section>
          <DeviceRoutingSettings
            v-model="configuration"
            :field-errors="fieldErrors"
            :media-options="mediaOptions"
            :supports-sip="usesSip(deviceType)"
          />
        </TabPanel>

        <TabPanel
          v-if="deviceSupportsTab(deviceType, 'restrictions')"
          class="grid gap-5 outline-none"
        >
          <section class="grid gap-4 rounded-lg border border-slate-200 p-4">
            <div>
              <h3 class="text-xs font-semibold text-slate-700">Internal calling</h3>
              <p class="mt-1 text-[10px] leading-4 text-slate-400">
                Deny calls that are limited to members of the same closed group.
              </p>
            </div>
            <ToggleSwitch
              :model-value="restrictionAction('closed_groups') === 'deny'"
              label="Deny closed-group calls"
              description="Use the account policy when disabled"
              @update:model-value="
                setRestrictionAction('closed_groups', $event ? 'deny' : 'inherit')
              "
            />
          </section>

          <section class="overflow-hidden rounded-lg border border-slate-200">
            <header class="border-b border-slate-100 bg-slate-50/70 px-4 py-3">
              <h3 class="text-xs font-semibold text-slate-700">Number classifications</h3>
              <p class="mt-1 text-[10px] leading-4 text-slate-400">
                Classifications come from the connected Switch deployment. Inherit uses the
                account-level policy.
              </p>
            </header>
            <div
              v-if="restrictionRows.length === 0"
              class="px-4 py-6 text-center text-xs text-slate-400"
            >
              No number classifications are available from Switch.
            </div>
            <div v-else class="divide-y divide-slate-100">
              <label
                v-for="restriction in restrictionRows"
                :key="restriction.key"
                class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_220px] sm:items-center"
              >
                <span>
                  <span class="block text-xs font-semibold text-slate-600">{{
                    restriction.label
                  }}</span>
                  <span class="mt-0.5 block text-[10px] text-slate-400">{{
                    restriction.emergency ? 'Emergency classification' : restriction.key
                  }}</span>
                </span>
                <span class="grid gap-1.5">
                  <FormListbox
                    :model-value="restrictionAction(restriction.key)"
                    :aria-label="`Restriction for ${restriction.label}`"
                    :invalid="Boolean(error(`call_restriction.${restriction.key}.action`))"
                    :options="[
                      { value: 'inherit', label: 'Inherit account policy' },
                      { value: 'deny', label: 'Deny' },
                    ]"
                    @update:model-value="setRestrictionAction(restriction.key, $event)"
                  />
                  <span
                    v-if="error(`call_restriction.${restriction.key}.action`)"
                    class="text-[11px] text-danger"
                    >{{ error(`call_restriction.${restriction.key}.action`) }}</span
                  >
                </span>
              </label>
            </div>
          </section>
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </article>
</template>

<style scoped>
@reference "../../../assets/main.css";

.field-control {
  @apply h-10 rounded-md border border-slate-200 px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100;
}
</style>
