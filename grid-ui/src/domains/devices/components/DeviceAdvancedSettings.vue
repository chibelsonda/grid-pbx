<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  Tab,
  TabGroup,
  TabList,
  TabPanel,
  TabPanels,
} from '@headlessui/vue'
import {
  IdentificationIcon,
  MusicalNoteIcon,
  PhoneArrowUpRightIcon,
  ShieldExclamationIcon,
  SignalIcon,
  Squares2X2Icon,
  VideoCameraIcon,
} from '@heroicons/vue/24/outline'
import FormListbox from '@/shared/components/FormListbox.vue'
import { useDelimitedStringList } from '@/shared/forms/useDelimitedStringList'
import { validationControlClass } from '@/shared/forms/validationStyles'
import {
  audioCodecs,
  deviceAdvancedTabForError,
  deviceSupportsTab,
  supportsDeviceOption,
  supportsFaxOption,
  supportsMusicOnHold,
  supportsOutboundFlags,
  supportsVideo,
  videoCodecs,
} from '../deviceForm'
import type {
  DeviceCallerIdNumberOption,
  DeviceConfiguration,
  DeviceMetaflowResources,
  DeviceRestrictionOption,
  DeviceSchemaCompatibility,
  DeviceType,
} from '../types/device'
import DeviceCodecPriority from './DeviceCodecPriority.vue'
import DeviceRingtoneSettings from './DeviceRingtoneSettings.vue'

const props = withDefaults(
  defineProps<{
    deviceType: DeviceType
    fieldErrors: Record<string, string[]>
    firstErrorField: string | null
    isEditing: boolean
    restrictionOptions: DeviceRestrictionOption[]
    mediaOptions: Array<{ id: string; name: string | null }>
    metaflowResources?: DeviceMetaflowResources
    extensionOptions?: Array<{ id: string; display_name: string; extension: string | null }>
    callerIdNumberOptions?: DeviceCallerIdNumberOption[]
    schemaCompatibility: DeviceSchemaCompatibility
  }>(),
  {
    callerIdNumberOptions: () => [],
    metaflowResources: () => ({ callflows: [], devices: [] }),
    extensionOptions: () => [],
  },
)
const configuration = defineModel<DeviceConfiguration>({ required: true })
const callerIdScopes = ['internal', 'external', 'emergency'] as const
const selectedTabIndex = ref(0)
const inviteFormatLabels = {
  contact: 'Contact',
  username: 'Username',
  npan: 'NPAN',
  '1npan': '1NPAN',
  e164: 'E.164',
  route: 'Route',
  strip_plus: 'Strip leading +',
} as const
const inviteFormatOptions = computed(() =>
  props.schemaCompatibility.sip.invite_formats.map((format) => ({
    value: format,
    label: inviteFormatLabels[format],
  })),
)
const staticOutboundFlags = useDelimitedStringList(
  () => configuration.value.outbound_flags.static,
  (values) => (configuration.value.outbound_flags.static = values),
)

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

function callerIdOptions(scope: 'external' | 'emergency') {
  const current = configuration.value.caller_id[scope].number
  const projected = props.callerIdNumberOptions
    .filter((option) => scope !== 'emergency' || option.e911_enabled)
    .map((option) => ({
      value: option.number,
      label: option.number,
      description: option.display_name,
    }))

  if (current && !projected.some((option) => option.value === current)) {
    projected.unshift({
      value: current,
      label: current,
      description: 'Current Switch value; not available in the local phone-number projection',
    })
  }

  return [{ value: null, label: 'Inherit account caller ID', description: null }, ...projected]
}

function restrictionAction(key: string): 'inherit' | 'deny' {
  return configuration.value.call_restriction[key]?.action ?? 'inherit'
}

function setRestrictionAction(key: string, action: unknown): void {
  if (action !== 'inherit' && action !== 'deny') return
  configuration.value.call_restriction[key] = { action }
}

function toggleEncryptionMethod(method: string): void {
  const methods = configuration.value.media.encryption.methods
  const index = methods.indexOf(method)

  if (index === -1) methods.push(method)
  else methods.splice(index, 1)
}
</script>

<template>
  <article class="card-surface overflow-visible">
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
              <FormListbox
                v-if="scope !== 'internal'"
                v-model="configuration.caller_id[scope].number"
                :invalid="Boolean(error(`caller_id.${scope}.number`))"
                :options="callerIdOptions(scope)"
                :aria-label="`${scope} caller ID number`"
              />
              <input
                v-else
                v-model="configuration.caller_id.internal.number"
                maxlength="35"
                class="field-control"
                :class="invalidClass('caller_id.internal.number')"
                :aria-invalid="Boolean(error('caller_id.internal.number'))"
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
                :options="inviteFormatOptions"
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
            <label v-if="schemaCompatibility.sip.custom_sip_interface" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Custom SIP interface</span>
              <input
                v-model="configuration.sip.custom_sip_interface"
                maxlength="255"
                class="field-control font-mono"
                :class="invalidClass('sip.custom_sip_interface')"
                :aria-invalid="Boolean(error('sip.custom_sip_interface'))"
                placeholder="Optional interface name"
              />
              <span v-if="error('sip.custom_sip_interface')" class="text-[11px] text-danger">{{
                error('sip.custom_sip_interface')
              }}</span>
            </label>
            <label v-if="schemaCompatibility.sip.forward" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Forward IP</span>
              <input
                v-model="configuration.sip.forward"
                maxlength="255"
                class="field-control font-mono"
                :class="invalidClass('sip.forward')"
                :aria-invalid="Boolean(error('sip.forward'))"
                placeholder="Optional forwarding host"
              />
              <span v-if="error('sip.forward')" class="text-[11px] text-danger">{{
                error('sip.forward')
              }}</span>
            </label>
            <label v-if="schemaCompatibility.sip.proxy" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">SIP proxy</span>
              <input
                v-model="configuration.sip.proxy"
                maxlength="2048"
                class="field-control font-mono"
                :class="invalidClass('sip.proxy')"
                :aria-invalid="Boolean(error('sip.proxy'))"
                placeholder="Optional proxy address"
              />
              <span v-if="error('sip.proxy')" class="text-[11px] text-danger">{{
                error('sip.proxy')
              }}</span>
            </label>
            <label v-if="schemaCompatibility.sip.static_invite" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Static SIP To user</span>
              <input
                v-model="configuration.sip.static_invite"
                maxlength="2048"
                class="field-control font-mono"
                :class="invalidClass('sip.static_invite')"
                :aria-invalid="Boolean(error('sip.static_invite'))"
                placeholder="Optional To user"
              />
              <span v-if="error('sip.static_invite')" class="text-[11px] text-danger">{{
                error('sip.static_invite')
              }}</span>
            </label>
            <label v-if="schemaCompatibility.sip.transport" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">SIP transport</span>
              <input
                v-model="configuration.sip.transport"
                maxlength="32"
                class="field-control font-mono"
                :class="invalidClass('sip.transport')"
                :aria-invalid="Boolean(error('sip.transport'))"
                placeholder="udp, tcp, or tls"
              />
              <span v-if="error('sip.transport')" class="text-[11px] text-danger">{{
                error('sip.transport')
              }}</span>
            </label>
            <label
              v-if="supportsOutboundFlags(deviceType)"
              class="grid gap-2 sm:col-span-2"
            >
              <span class="text-xs font-semibold text-slate-600">Outbound flags</span>
              <textarea
                v-model="staticOutboundFlags"
                rows="2"
                class="field-control min-h-20 py-2"
                :class="invalidClass('outbound_flags.static')"
                :aria-invalid="Boolean(error('outbound_flags.static'))"
                placeholder="fax, trusted"
              />
              <span class="text-[10px] text-slate-500">
                Separate Switch resource flags with commas or new lines.
              </span>
              <span v-if="error('outbound_flags.static')" class="text-[11px] text-danger">{{
                error('outbound_flags.static')
              }}</span>
            </label>
          </TabPanel>
        </template>

        <template v-if="deviceSupportsTab(deviceType, 'audio')">
          <TabPanel class="grid gap-6 outline-none">
            <label v-if="supportsMusicOnHold(deviceType)" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Music on hold</span>
              <FormListbox
                v-model="configuration.music_on_hold.media_id"
                :invalid="Boolean(error('music_on_hold.media_id'))"
                :options="[
                  { value: null, label: 'Inherit account music' },
                  ...mediaOptions.map((media) => ({
                    value: media.id,
                    label: media.name || 'Untitled media',
                  })),
                ]"
                aria-label="Select device music on hold"
              />
              <span v-if="error('music_on_hold.media_id')" class="text-[11px] text-danger">
                {{ error('music_on_hold.media_id') }}
              </span>
            </label>
            <DeviceCodecPriority
              v-model="configuration.media.audio.codecs"
              label="Audio codec priority"
              description="The Switch negotiates selected codecs from first priority to last."
              :options="audioCodecs"
              :error="error('media.audio.codecs')"
            />
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
          <TabPanel class="outline-none">
            <DeviceCodecPriority
              v-model="configuration.media.video.codecs"
              label="Video codec priority"
              description="The Switch negotiates selected codecs from first priority to last."
              :options="videoCodecs"
              :error="error('media.video.codecs')"
            />
          </TabPanel>
        </template>

        <TabPanel class="grid gap-4 outline-none sm:grid-cols-2">
          <DeviceRingtoneSettings
            v-if="supportsDeviceOption(deviceType, 'ringtones')"
            v-model="configuration.ringtones"
            :field-errors="fieldErrors"
            class="sm:col-span-2"
          />
          <template v-if="supportsDeviceOption(deviceType, 'forwarding')">
            <ToggleSwitch
              v-model="configuration.call_forward.require_keypress"
              label="Require keypress"
              description="Callee presses 1 to accept"
            />
            <ToggleSwitch
              v-model="configuration.call_forward.keep_caller_id"
              label="Keep original caller ID"
            />
          </template>
          <ToggleSwitch
            v-if="supportsDeviceOption(deviceType, 'fax') && supportsFaxOption(deviceType)"
            v-model="configuration.media.fax_option"
            label="Enable T.38 fax"
            description="Negotiate fax media for this endpoint"
          />
          <ToggleSwitch
            v-if="supportsDeviceOption(deviceType, 'contact-list')"
            v-model="configuration.contact_list.exclude"
            label="Hide from contact list"
          />
          <ToggleSwitch
            v-if="supportsDeviceOption(deviceType, 'ignore-completed-elsewhere')"
            v-model="configuration.sip.ignore_completed_elsewhere"
            label="Ignore completed elsewhere"
            description="Do not mark group calls answered elsewhere as missed"
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

          <section class="overflow-visible rounded-lg border border-slate-200">
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
