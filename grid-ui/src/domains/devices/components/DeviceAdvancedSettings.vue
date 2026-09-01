<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import {
  IdentificationIcon,
  MusicalNoteIcon,
  PhoneArrowUpRightIcon,
  ShieldExclamationIcon,
  SignalIcon,
  Squares2X2Icon,
  VideoCameraIcon,
} from '@heroicons/vue/24/outline'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox from '@/shared/components/FormListbox.vue'
import {
  audioCodecs,
  deviceAdvancedTabForError,
  deviceSupportsTab,
  supportsDeviceOption,
  supportsFaxOption,
  supportsMusicOnHold,
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
        aria-label="Device advanced sections"
        class="flex gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50/70 px-3 pt-3 sm:px-4"
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

      <TabPanels class="p-4 sm:p-5">
        <TabPanel class="outline-none">
          <slot name="basic" />
        </TabPanel>

        <TabPanel v-if="deviceSupportsTab(deviceType, 'caller-id')" class="grid gap-5 outline-none">
          <FormInput
            v-model="configuration.presence_id"
            label="Presence ID"
            maxlength="255"
            placeholder="Use the SIP username when empty"
            :error="error('presence_id')"
          />
          <section
            v-for="scope in callerIdScopes"
            :key="scope"
            class="grid gap-4 rounded-md border border-slate-100 p-4 sm:grid-cols-2"
          >
            <h3 class="text-xs font-semibold capitalize text-slate-700 sm:col-span-2">
              {{ scope }} caller ID
            </h3>
            <FormInput
              v-model="configuration.caller_id[scope].name"
              label="Name"
              maxlength="35"
              :error="error(`caller_id.${scope}.name`)"
            />
            <label v-if="scope !== 'internal'" class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Number</span>
              <FormListbox
                v-model="configuration.caller_id[scope].number"
                :invalid="Boolean(error(`caller_id.${scope}.number`))"
                :options="callerIdOptions(scope)"
                :aria-label="`${scope} caller ID number`"
              />
              <span v-if="error(`caller_id.${scope}.number`)" class="text-[11px] text-danger">{{
                error(`caller_id.${scope}.number`)
              }}</span>
            </label>
            <FormInput
              v-else
              v-model="configuration.caller_id.internal.number"
              label="Number"
              maxlength="35"
              :error="error('caller_id.internal.number')"
            />
          </section>
          <section class="grid gap-4 rounded-md border border-slate-100 p-4 sm:grid-cols-2">
            <h3 class="text-xs font-semibold text-slate-700 sm:col-span-2">Asserted identity</h3>
            <FormInput
              v-model="configuration.caller_id.asserted.name"
              label="Name"
              maxlength="35"
              :error="error('caller_id.asserted.name')"
            />
            <FormInput
              v-model="configuration.caller_id.asserted.number"
              label="Number"
              maxlength="35"
              :error="error('caller_id.asserted.number')"
            />
            <FormInput
              v-model="configuration.caller_id.asserted.realm"
              label="Realm"
              maxlength="253"
              placeholder="Use the account realm when empty"
              class="sm:col-span-2"
              :error="error('caller_id.asserted.realm')"
            />
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
              <FormInput
                v-model="configuration.sip.username"
                label="SIP username"
                maxlength="32"
                autocomplete="off"
                :placeholder="
                  configuration.sip.username_configured ? 'Configured — enter to replace' : ''
                "
                :error="error('sip.username')"
              />
              <FormInput
                v-model="configuration.sip.password"
                :label="isEditing ? 'New SIP password' : 'SIP password'"
                type="password"
                minlength="12"
                maxlength="32"
                autocomplete="new-password"
                :placeholder="isEditing ? 'Leave blank to keep current password' : ''"
                :error="error('sip.password')"
              />
            </template>
            <FormInput
              v-else
              v-model="configuration.sip.ip"
              label="Authorized IP address"
              input-class="font-mono"
              placeholder="203.0.113.10"
              class="sm:col-span-2"
              :error="error('sip.ip')"
            />
            <FormInput
              v-model="configuration.sip.realm"
              label="Realm override"
              maxlength="253"
              placeholder="Use account realm"
              :error="error('sip.realm')"
            />
            <FormInput
              v-model.number="configuration.sip.expire_seconds"
              label="Registration period"
              type="number"
              min="30"
              max="86400"
              :error="error('sip.expire_seconds')"
            />
            <FormInput
              v-if="configuration.sip.invite_format === 'route' || deviceType === 'sip_uri'"
              v-model="configuration.sip.route"
              label="SIP route"
              maxlength="2048"
              input-class="font-mono"
              placeholder="sip:user@example.com"
              class="sm:col-span-2"
              :error="error('sip.route')"
            />
            <FormInput
              v-if="['npan', '1npan', 'e164'].includes(configuration.sip.invite_format)"
              v-model="configuration.sip.number"
              label="Invite number"
              maxlength="64"
              placeholder="Uses the dialed number when empty"
              class="sm:col-span-2"
              :error="error('sip.number')"
            />
            <FormInput
              v-model="configuration.sip.static_route"
              label="Static inbound route"
              maxlength="2048"
              input-class="font-mono"
              placeholder="Optional"
              class="sm:col-span-2"
              :error="error('sip.static_route')"
            />
            <FormInput
              v-if="schemaCompatibility.sip.custom_sip_interface"
              v-model="configuration.sip.custom_sip_interface"
              label="Custom SIP interface"
              maxlength="255"
              input-class="font-mono"
              placeholder="Optional interface name"
              :error="error('sip.custom_sip_interface')"
            />
            <FormInput
              v-if="schemaCompatibility.sip.forward"
              v-model="configuration.sip.forward"
              label="Forward IP"
              maxlength="255"
              input-class="font-mono"
              placeholder="Optional forwarding host"
              :error="error('sip.forward')"
            />
            <FormInput
              v-if="schemaCompatibility.sip.proxy"
              v-model="configuration.sip.proxy"
              label="SIP proxy"
              maxlength="2048"
              input-class="font-mono"
              placeholder="Optional proxy address"
              :error="error('sip.proxy')"
            />
            <FormInput
              v-if="schemaCompatibility.sip.static_invite"
              v-model="configuration.sip.static_invite"
              label="Static SIP To user"
              maxlength="2048"
              input-class="font-mono"
              placeholder="Optional To user"
              :error="error('sip.static_invite')"
            />
            <FormInput
              v-if="schemaCompatibility.sip.transport"
              v-model="configuration.sip.transport"
              label="SIP transport"
              maxlength="32"
              input-class="font-mono"
              placeholder="udp, tcp, or tls"
              :error="error('sip.transport')"
            />
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
              <FormInput
                v-model.number="configuration.media.progress_timeout"
                label="Progress timeout (seconds)"
                type="number"
                min="0"
                max="3600"
                :error="error('media.progress_timeout')"
              />
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
                data-testid="device-restriction-row"
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
