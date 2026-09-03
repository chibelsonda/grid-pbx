<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ArrowDownIcon,
  ArrowUpIcon,
  PlusIcon,
  ShieldCheckIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import CrudSlideOver from '@/shared/components/CrudSlideOver.vue'
import FormCheckbox from '@/shared/components/FormCheckbox.vue'
import FormInput from '@/shared/components/FormInput.vue'
import FormListbox, {
  type ListboxOptionValue,
  type ListboxValue,
} from '@/shared/components/FormListbox.vue'
import FormTextarea from '@/shared/components/FormTextarea.vue'
import ToggleSwitch from '@/shared/components/ToggleSwitch.vue'
import { findCallflowAction } from '../catalog/callflowActionCatalog'
import { callflowActionIcon } from '../catalog/callflowActionIcons'
import { useCallflowInlineNodeForm } from '../composables/useCallflowInlineNodeForm'
import { callflowDtmfDigits } from '../schemas/callflowInlineNodeFormSchema'
import type {
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
  CallflowAlertRecipient,
  CallflowCapturedNumberBranchKey,
  CallflowEditor,
  CallflowNodeEditorContext,
  CallflowRingGroupEndpoint,
  CallflowTreeBranchKey,
} from '../types/callRouting'

const props = withDefaults(
  defineProps<{
    context: CallflowNodeEditorContext
    editor?: CallflowEditor | null
    loading?: boolean
    saving: boolean
    error: string | null
    fieldErrors: Record<string, string[]>
    rootConfiguration?: boolean
  }>(),
  { editor: null, loading: false, rootConfiguration: false },
)
const emit = defineEmits<{
  close: []
  save: [input: CallflowInlineNodeCreateInput | CallflowInlineNodeUpdateInput]
}>()
const { form, module, branches, usesCapturedNumberBranch, validationErrors, validate } =
  useCallflowInlineNodeForm(() => props.context)
const action = computed(() =>
  findCallflowAction(
    module.value,
    form.data.action ?? (form.data.service_mode === true ? 'service' : undefined),
  ),
)
const errors = computed(() => ({ ...props.fieldErrors, ...validationErrors.value }))
const replacementConfirmed = ref(false)
const replacementError = ref<string | null>(null)
const branchOptions = computed<ListboxOptionValue[]>(() => branches.value)
const title = computed(() =>
  props.rootConfiguration && props.context.operation === 'create'
    ? `Configure ${action.value?.label ?? 'root action'}`
    : props.context.operation === 'create'
      ? `Add ${action.value?.label ?? 'callflow action'}`
      : `Edit ${action.value?.label ?? 'callflow action'}`,
)
const actionIcon = computed(() =>
  callflowActionIcon(module.value, {
    action: typeof form.data.action === 'string' ? form.data.action : undefined,
  }),
)
const branchBnumberHasExactChildren = computed(
  () =>
    module.value === 'branch_bnumber' &&
    props.context.operation === 'update' &&
    Object.keys(props.context.node.children).some((branch) => branch !== '_'),
)

const unitOptions: ListboxOptionValue[] = [
  { value: 'ms', label: 'Milliseconds' },
  { value: 's', label: 'Seconds' },
  { value: 'm', label: 'Minutes' },
  { value: 'h', label: 'Hours' },
]
const engineOptions: ListboxOptionValue[] = [
  { value: null, label: 'Switch default' },
  { value: 'flite', label: 'Flite' },
  { value: 'google', label: 'Google' },
  { value: 'ispeech', label: 'iSpeech' },
  { value: 'voicefabric', label: 'VoiceFabric' },
]
const recordingFormatOptions: ListboxOptionValue[] = [
  { value: null, label: 'Switch default' },
  { value: 'mp3', label: 'MP3' },
  { value: 'wav', label: 'WAV' },
]
const recordingActionOptions: ListboxOptionValue[] = [
  { value: 'start', label: 'Start recording' },
  { value: 'stop', label: 'Stop recording' },
]
const presenceStatusOptions: ListboxOptionValue[] = [
  { value: 'idle', label: 'Idle' },
  { value: 'ringing', label: 'Ringing' },
  { value: 'busy', label: 'Busy' },
]
const pageGroupAudioOptions: ListboxOptionValue[] = [
  { value: 'one-way', label: 'One-way', description: 'Devices listen to the page' },
  { value: 'two-way', label: 'Two-way', description: 'Devices can speak back to the caller' },
]
const ringGroupStrategyOptions: ListboxOptionValue[] = [
  { value: 'simultaneous', label: 'At the same time' },
  { value: 'single', label: 'In order' },
  {
    value: 'weighted_random',
    label: 'Weighted random order',
    description: 'Try every device in a new weighted random sequence per attempt',
  },
]
const faxOptionOptions: ListboxOptionValue[] = [
  { value: 'auto', label: 'Automatic', description: 'Let Switch negotiate T.38 when available' },
  { value: 'enabled', label: 'Enabled', description: 'Request T.38 fax media' },
  { value: 'disabled', label: 'Disabled', description: 'Receive fax without T.38 negotiation' },
]
const recipientTypeOptions: ListboxOptionValue[] = [
  { value: 'email', label: 'Email address' },
  { value: 'user', label: 'Extension user' },
]
const prependActionOptions: ListboxOptionValue[] = [
  { value: 'prepend', label: 'Prepend values' },
  { value: 'reset', label: 'Reset prefixes' },
]
const prependTargetOptions: ListboxOptionValue[] = [
  { value: 'original', label: 'Original caller ID' },
  { value: 'current', label: 'Current accumulated caller ID' },
]
const channelOptions: ListboxOptionValue[] = [
  { value: 'a', label: 'A leg only' },
  { value: 'both', label: 'Both call legs' },
]
const extensionOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.destinations.extension ?? []).map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const deviceOptions = computed(() => props.editor?.destinations.device ?? [])
const ringGroupTargetOptions = computed<ListboxOptionValue[]>(() => {
  const selected = new Set((form.data.endpoints ?? []).map(ringGroupEndpointIdentity))

  return (
    [
      ['extension', 'Extension'],
      ['device', 'Device'],
      ['group', 'Group'],
    ] as const
  ).flatMap(([type, typeLabel]) =>
    (props.editor?.destinations[type] ?? [])
      .filter(({ id }) => !selected.has(`${type}:${id}`))
      .map(({ id, label, detail }) => ({
        value: `${type}:${id}`,
        label,
        description: detail ? `${typeLabel} · ${detail}` : typeLabel,
      })),
  )
})
const ringGroupRingbackOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Switch default' },
  ...(props.editor?.destinations.media ?? [])
    .filter(({ supports_ringback }) => supports_ringback === true)
    .map(({ id, label, detail }) => ({ value: id, label, description: detail })),
])
const callerIdentityOwnerOptions = computed<ListboxOptionValue[]>(() => [
  { value: null, label: 'Do not override caller identity' },
  ...extensionOptions.value,
])
const callerIdListOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.caller_id_lists ?? []).map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const dynamicCallerIdNumberOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.phone_numbers ?? []).map(({ id, number, state }) => ({
    value: id,
    label: number,
    description: state ? `Account number · ${state.replaceAll('_', ' ')}` : 'Account number',
  })),
)
const temporalRuleOptions = computed(() => props.editor?.temporal_rules ?? [])
const callflowOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.destinations.callflow ?? [])
    .filter(({ supports_ring_group_toggle }) => supports_ring_group_toggle === true)
    .map(({ id, label, detail }) => ({
      value: id,
      label,
      description: detail,
    })),
)
const queueOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.destinations.queue ?? []).map(({ id, label, detail }) => ({
    value: id,
    label,
    description: detail,
  })),
)
const pivotEndpoints = computed(() => props.editor?.pivot_endpoints ?? [])
const selectedPivotEndpoint = computed(() =>
  pivotEndpoints.value.find(({ id }) => id === form.data.endpoint_id),
)
const pivotEndpointOptions = computed<ListboxOptionValue[]>(() =>
  pivotEndpoints.value.map(({ id, label }) => ({ value: id, label })),
)
const pivotMethodOptions = computed<ListboxOptionValue[]>(() =>
  (selectedPivotEndpoint.value?.methods ?? []).map((method) => ({
    value: method,
    label: method.toUpperCase(),
  })),
)
const pivotFormatOptions = computed<ListboxOptionValue[]>(() =>
  (selectedPivotEndpoint.value?.formats ?? []).map((format) => ({
    value: format,
    label: format === 'twiml' ? 'TwiML' : 'Switch Pivot',
  })),
)
const webhookEndpoints = computed(() => props.editor?.webhook_endpoints ?? [])
const selectedWebhookEndpoint = computed(() =>
  webhookEndpoints.value.find(({ id }) => id === form.data.endpoint_id),
)
const webhookEndpointOptions = computed<ListboxOptionValue[]>(() =>
  webhookEndpoints.value.map(({ id, label }) => ({ value: id, label })),
)
const webhookMethodOptions = computed<ListboxOptionValue[]>(() =>
  (selectedWebhookEndpoint.value?.methods ?? []).map((method) => ({
    value: method,
    label: method.toUpperCase(),
  })),
)
const disaAccessPolicies = computed(() => props.editor?.disa_access_policies ?? [])
const disaOperationalSafety = computed(() => props.editor?.disa_operational_safety ?? null)
const disaAccessPolicyOptions = computed<ListboxOptionValue[]>(() =>
  disaAccessPolicies.value.map(({ id, label, retries, max_digits }) => ({
    value: id,
    label,
    description: `${retries} attempts · up to ${max_digits} destination digits`,
  })),
)
const carrierRouteOptions = computed<ListboxOptionValue[]>(() =>
  (props.editor?.carrier_routes ?? [])
    .filter(({ module: routeModule }) => routeModule === module.value)
    .map(({ id, label, scope }) => ({
      value: id,
      label,
      description:
        scope === 'global'
          ? 'Switch-managed global carrier pool'
          : scope === 'reseller'
            ? 'Nearest projected reseller resources'
            : 'Current account resources',
    })),
)
const webhookCustomDataJson = ref(JSON.stringify(form.data.custom_data ?? {}, null, 2))
const webhookCustomDataError = ref<string | null>(null)
const groupPickupOptions = computed<ListboxOptionValue[]>(() =>
  (
    [
      ['group', 'Group'],
      ['extension', 'Extension'],
      ['device', 'Device'],
    ] as const
  ).flatMap(([type, typeLabel]) =>
    (props.editor?.destinations[type] ?? []).map(({ id, label, detail }) => ({
      value: `${type}:${id}`,
      label,
      description: detail ? `${typeLabel} · ${detail}` : typeLabel,
    })),
  ),
)
const groupPickupValue = computed(() =>
  form.data.target_type && form.data.target_id
    ? `${form.data.target_type}:${form.data.target_id}`
    : null,
)
const faxOptionValue = computed(() => {
  if (form.data.fax_option === 'auto') return 'auto'
  return form.data.fax_option === true ? 'enabled' : 'disabled'
})
const operationalModules = new Set([
  'temporal_route',
  'ring_group_toggle',
  'acdc_queue',
  'hotdesk',
  'do_not_disturb',
  'call_forward',
])
const lockedReason = computed<string | null>(() => {
  if (props.context.operation !== 'update') return null
  if (module.value === 'set_variable' && props.context.node.settings?.supported_variable !== true) {
    return 'This node uses a channel variable that the current Switch runtime does not support through its guided call-priority workflow. GridPBX preserves it without exposing its name or value.'
  }
  if (
    module.value === 'set_variables' &&
    props.context.node.settings?.supported_variables !== true
  ) {
    return 'This node contains custom application variables outside the supported Switch form contract. GridPBX preserves the complete node without exposing or rewriting those values.'
  }
  if (module.value === 'group_pickup' && props.context.node.settings?.supported_target !== true) {
    return 'This Group Pickup node has no single synchronized Device, Extension, or Group target. GridPBX preserves its target and private approval restrictions without exposing or rewriting them.'
  }
  if (
    module.value === 'page_group' &&
    props.context.node.settings?.supported_configuration !== true
  ) {
    return 'This Page Group uses unresolved or expanded endpoints, barge mode, unsafe timing values, or more than 20 devices. GridPBX preserves its complete configuration without exposing or rewriting raw endpoint IDs.'
  }
  if (
    module.value === 'ring_group' &&
    props.context.node.settings?.supported_configuration !== true
  ) {
    return 'This Ring Group uses unresolved endpoints, unsafe timing or weight values, more than 20 members, unsupported ringback, or unsafe phone-alert values. GridPBX preserves its complete configuration without exposing or rewriting raw resource IDs.'
  }
  if (
    module.value === 'receive_fax' &&
    props.context.node.settings?.supported_configuration !== true
  ) {
    return 'This Receive Fax node has no synchronized Extension owner or uses an unsupported T.38 mode. GridPBX preserves its complete configuration without exposing or rewriting it.'
  }
  if (
    module.value === 'ring_group_toggle' &&
    props.context.node.settings?.supported_configuration !== true
  ) {
    return 'This Ring Group Toggle target is unavailable or does not contain a Ring Group. GridPBX preserves its raw target without exposing or rewriting it.'
  }
  if (
    module.value === 'acdc_queue' &&
    props.context.node.settings?.supported_configuration !== true
  ) {
    return 'This ACDC Queue target is unavailable or its action is unsupported. GridPBX preserves the raw Queue ID and unknown settings without exposing or rewriting them.'
  }
  if (
    module.value === 'branch_variable' &&
    props.context.node.settings?.supported_variable !== true
  ) {
    return 'This node branches on a variable or scope outside the supported Switch call-priority workflow. GridPBX preserves its settings and dynamic branches without exposing or rewriting them.'
  }
  if (
    module.value === 'cidlistmatch' &&
    props.context.node.settings?.reference_status === 'unresolved'
  ) {
    return 'This Caller-ID List is not available in the current account projection. Synchronize Caller-ID Lists before editing this node.'
  }
  if (module.value === 'pivot' && props.context.node.settings?.supported_configuration !== true) {
    return 'This Pivot node does not match an administrator-approved endpoint. GridPBX preserves its private URL, headers, and unsupported settings without exposing or rewriting them.'
  }
  if (
    module.value === 'dynamic_cid' &&
    props.context.node.settings?.supported_configuration !== true
  ) {
    return 'This Dynamic CID node uses manual entry, a Caller-ID List, or a number that is no longer projected under this account. GridPBX preserves the complete Switch node without exposing or rewriting its caller ID.'
  }
  if (module.value === 'disa' && props.context.node.settings?.supported_configuration !== true) {
    return 'This DISA node does not match an active administrator-approved access policy. GridPBX preserves its private credential and unsupported settings without exposing or rewriting them.'
  }
  if (module.value !== 'check_cid') return null
  if (props.context.node.settings?.use_absolute_mode === true) {
    return 'This node uses Switch absolute caller-number branches. GridPBX preserves those dynamic branches but does not rewrite them.'
  }
  if (props.context.node.settings?.identity_reference_status === 'unresolved') {
    return 'The caller identity owner is not available in the current projection. Synchronize extensions before editing this node.'
  }
  return null
})

function fieldError(field: string): string | null {
  return errors.value[field]?.[0] ?? null
}

function setBranch(value: ListboxValue): void {
  if (branches.value.some((option) => option.value === value)) {
    form.branch = value as CallflowTreeBranchKey
  }
}

function setFaxOption(value: ListboxValue): void {
  form.data.fax_option = value === 'auto' ? 'auto' : value === 'enabled'
}

function setCapturedNumberBranch(value: string | number | null): void {
  const branch = String(value ?? '').trim()
  const defaultAvailable = branches.value.some(({ value }) => value === '_')

  form.branch =
    branch === '' ? (defaultAvailable ? '_' : null) : (branch as CallflowCapturedNumberBranchKey)
}

function setBranchBnumberHunt(value: boolean): void {
  form.data.hunt = value

  if (!value) {
    form.data.hunt_allow = null
    form.data.hunt_deny = null
  }
}

function setTerminators(value: boolean | string[]): void {
  if (Array.isArray(value)) form.data.terminators = value
}

function setTemporalRules(value: boolean | string[]): void {
  if (Array.isArray(value)) form.data.rules = value
}

function setPageGroupDevices(value: boolean | string[]): void {
  if (Array.isArray(value)) form.data.device_ids = value
}

function pageGroupDeviceIsDisabled(id: string): boolean {
  const selected = form.data.device_ids ?? []
  return selected.length >= 20 && !selected.includes(id)
}

function ringGroupEndpoints(): CallflowRingGroupEndpoint[] {
  return form.data.endpoints ?? (form.data.endpoints = [])
}

function addRingGroupEndpoint(value: ListboxValue): void {
  if (typeof value !== 'string') return

  const separator = value.indexOf(':')
  const type = value.slice(0, separator)
  const id = value.slice(separator + 1)
  if (!['device', 'extension', 'group'].includes(type) || !id) return
  if (ringGroupEndpoints().some((endpoint) => ringGroupEndpointIdentity(endpoint) === value)) return

  if (ringGroupEndpoints().length < 20) {
    ringGroupEndpoints().push({
      ...(type === 'device'
        ? { device_id: id }
        : type === 'extension'
          ? { extension_id: id }
          : { group_id: id }),
      delay: 0,
      timeout: 20,
      ...(form.data.strategy === 'weighted_random' ? { weight: 20 } : {}),
    })
  }
}

function setRingGroupStrategy(value: ListboxValue): void {
  if (!['simultaneous', 'single', 'weighted_random'].includes(String(value))) return

  form.data.strategy = value as 'simultaneous' | 'single' | 'weighted_random'
  ringGroupEndpoints().forEach((endpoint) => {
    if (value !== 'simultaneous') {
      endpoint.delay = 0
    }

    if (value === 'weighted_random') {
      endpoint.weight ??= 20
    } else {
      delete endpoint.weight
    }
  })
}

function setRingGroupTiming(
  index: number,
  field: 'delay' | 'timeout' | 'weight',
  value: string | number | null,
): void {
  const endpoint = ringGroupEndpoints()[index]
  if (endpoint) endpoint[field] = Number(value)
}

function moveRingGroupEndpoint(index: number, direction: -1 | 1): void {
  const target = index + direction
  const endpoints = ringGroupEndpoints()
  if (target < 0 || target >= endpoints.length) return

  const [endpoint] = endpoints.splice(index, 1)
  if (endpoint) endpoints.splice(target, 0, endpoint)
}

function removeRingGroupEndpoint(index: number): void {
  ringGroupEndpoints().splice(index, 1)
}

function ringGroupEndpointIdentity(endpoint: CallflowRingGroupEndpoint): string {
  if (endpoint.device_id) return `device:${endpoint.device_id}`
  if (endpoint.extension_id) return `extension:${endpoint.extension_id}`
  if (endpoint.group_id) return `group:${endpoint.group_id}`
  return 'unavailable:'
}

function ringGroupEndpointLabel(endpoint: CallflowRingGroupEndpoint): string {
  const [type, id] = ringGroupEndpointIdentity(endpoint).split(':', 2)
  const target =
    type === 'extension'
      ? props.editor?.destinations.extension.find((item) => item.id === id)
      : type === 'device'
        ? props.editor?.destinations.device.find((item) => item.id === id)
        : props.editor?.destinations.group.find((item) => item.id === id)

  return target?.label ?? 'Unavailable endpoint'
}

function ringGroupEndpointType(endpoint: CallflowRingGroupEndpoint): string {
  if (endpoint.extension_id) return 'Extension'
  if (endpoint.device_id) return 'Device'
  if (endpoint.group_id) return 'Group'
  return 'Endpoint'
}

function setRingGroupRingtone(
  field: 'ringtone_internal' | 'ringtone_external',
  value: unknown,
): void {
  const ringtone = String(value ?? '').trim()
  form.data[field] = ringtone === '' ? null : ringtone
}

function setRingGroupCallflow(value: ListboxValue): void {
  if (typeof value === 'string') form.data.callflow_id = value
}

function setAcdcQueue(value: ListboxValue): void {
  if (typeof value === 'string') form.data.queue_id = value
}

function setDynamicCallerIdNumber(value: ListboxValue): void {
  if (typeof value === 'string') form.data.phone_number_id = value
}

function setPivotEndpoint(value: ListboxValue): void {
  if (typeof value !== 'string') return
  const endpoint = pivotEndpoints.value.find(({ id }) => id === value)
  if (!endpoint) return

  form.data.endpoint_id = endpoint.id
  if (!endpoint.methods.includes(form.data.method ?? 'get')) {
    form.data.method = endpoint.methods[0]
  }
  if (!endpoint.formats.includes(form.data.req_format ?? 'switch')) {
    form.data.req_format = endpoint.formats[0]
  }
}

function setPivotMethod(value: ListboxValue): void {
  if (value === 'get' || value === 'post') form.data.method = value
}

function setPivotFormat(value: ListboxValue): void {
  if (value === 'switch' || value === 'twiml') form.data.req_format = value
}

function setWebhookEndpoint(value: ListboxValue): void {
  if (typeof value !== 'string') return
  const endpoint = webhookEndpoints.value.find(({ id }) => id === value)
  if (!endpoint) return

  form.data.endpoint_id = endpoint.id
  if (!endpoint.methods.includes(form.data.http_verb ?? 'post')) {
    form.data.http_verb = endpoint.methods[0]
  }
  form.data.retries = Math.min(form.data.retries ?? 1, endpoint.max_retries)
}

function setWebhookMethod(value: ListboxValue): void {
  if (value === 'get' || value === 'post') form.data.http_verb = value
}

function setDisaAccessPolicy(value: ListboxValue): void {
  if (typeof value === 'string') form.data.access_policy_id = value
}

function setCarrierRoute(value: ListboxValue): void {
  if (typeof value === 'string') form.data.route_profile_id = value
}

function parseWebhookCustomData(): boolean {
  webhookCustomDataError.value = null

  try {
    const value: unknown = JSON.parse(webhookCustomDataJson.value || '{}')
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
      webhookCustomDataError.value = 'Enter a JSON object containing simple key/value pairs.'
      return false
    }
    form.data.custom_data = value as Record<string, string | number | boolean>
    return true
  } catch {
    webhookCustomDataError.value = 'Enter valid JSON custom data.'
    return false
  }
}

function setGroupPickupTarget(value: ListboxValue): void {
  if (typeof value !== 'string') return

  const separator = value.indexOf(':')
  const type = value.slice(0, separator)
  const id = value.slice(separator + 1)

  if (separator < 1 || !['extension', 'device', 'group'].includes(type) || id === '') return

  form.data.target_type = type as 'extension' | 'device' | 'group'
  form.data.target_id = id
}

function recipients(): CallflowAlertRecipient[] {
  return form.data.recipients ?? (form.data.recipients = [])
}

function addRecipient(type: CallflowAlertRecipient['type']): void {
  recipients().push({ type, id: '' })
}

function setRecipientType(index: number, value: ListboxValue): void {
  if (value !== 'user' && value !== 'email') return
  const recipient = recipients()[index]
  if (recipient) recipients()[index] = { type: value, id: '' }
}

function setRecipientId(index: number, value: string): void {
  const recipient = recipients()[index]
  if (recipient) recipient.id = value
}

function removeRecipient(index: number): void {
  recipients().splice(index, 1)
}

function customApplicationVariables() {
  return form.data.custom_application_variables ?? (form.data.custom_application_variables = [])
}

function addCustomApplicationVariable(): void {
  customApplicationVariables().push({ key: '', value: '' })
}

function setCustomApplicationVariable(
  index: number,
  field: 'key' | 'value',
  value: string | number | null,
): void {
  const variable = customApplicationVariables()[index]
  if (variable) variable[field] = String(value ?? '')
}

function removeCustomApplicationVariable(index: number): void {
  customApplicationVariables().splice(index, 1)
}

function setCallerIdentityOwner(value: ListboxValue): void {
  form.data.user_id = typeof value === 'string' ? value : null
  if (form.data.user_id === null) {
    form.data.external_caller_id_name = null
    form.data.external_caller_id_number = null
  }
}

function submit(): void {
  if (props.context.placement === 'replace' && !replacementConfirmed.value) {
    replacementError.value = 'Confirm that the existing next step will be replaced.'
    return
  }
  if (module.value === 'webhook' && !parseWebhookCustomData()) return

  const input = validate()
  if (input) {
    if ('parent_path' in input && props.context.placement === 'replace') {
      input.confirm_replace = true
    }
    emit('save', input)
  }
}

watch(
  () => props.context,
  () => {
    replacementConfirmed.value = false
    replacementError.value = null
    webhookCustomDataJson.value = JSON.stringify(form.data.custom_data ?? {}, null, 2)
    webhookCustomDataError.value = null
  },
)
</script>

<template>
  <CrudSlideOver
    :title="title"
    eyebrow="GridPBX / Callflows / Action"
    description="Configure the public Switch schema fields for this inline action."
    width="medium"
    @close="emit('close')"
  >
    <div v-if="lockedReason" class="grid gap-5">
      <div
        class="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900"
      >
        <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
        <p>{{ lockedReason }}</p>
      </div>
      <div class="slide-over-actions flex justify-end">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-700"
          @click="emit('close')"
        >
          Close
        </button>
      </div>
    </div>
    <form v-else class="grid gap-5" novalidate @submit.prevent="submit">
      <div
        v-if="context.placement === 'insert_before'"
        class="flex gap-3 rounded-md border border-blue-200 bg-blue-50 p-4 text-xs leading-5 text-blue-900"
      >
        <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          This action will be inserted before the current next step. The existing downstream
          callflow remains attached beneath the new action.
        </p>
      </div>

      <div
        v-if="context.placement === 'replace'"
        class="grid gap-3 rounded-md border border-amber-300 bg-amber-50 p-4"
      >
        <div class="flex gap-3 text-xs leading-5 text-amber-950">
          <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
          <p>
            This terminal action cannot retain the existing next step. Saving will replace that step
            and its complete downstream subtree in one atomic Switch update.
          </p>
        </div>
        <FormCheckbox
          v-model="replacementConfirmed"
          label="Replace the current next step"
          description="I understand that the existing downstream route will be removed."
          :error="replacementError"
          variant="compact"
          @update:model-value="replacementError = null"
        />
      </div>

      <section class="card-surface overflow-hidden">
        <header class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
          <span class="grid size-9 place-items-center rounded-md bg-brand-50 text-brand-600">
            <component :is="actionIcon" class="size-4" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-slate-700">
              {{ action?.label ?? context.module }}
            </h2>
            <p class="mt-0.5 font-mono text-[10px] text-slate-500">{{ context.module }}</p>
          </div>
        </header>

        <div class="grid gap-5 p-5">
          <FormInput
            v-if="context.operation === 'create' && usesCapturedNumberBranch"
            :model-value="form.branch === '_' ? '' : (form.branch ?? '')"
            label="Captured number branch"
            description="Enter the exact captured dial string, or leave empty for the default continuation."
            placeholder="1000"
            :error="fieldError('branch')"
            @update:model-value="setCapturedNumberBranch"
          />
          <label
            v-else-if="context.operation === 'create' && !rootConfiguration"
            class="grid gap-2"
          >
            <span class="text-xs font-semibold text-slate-700">Parent branch</span>
            <FormListbox
              :model-value="form.branch"
              :options="branchOptions"
              aria-label="Parent branch"
              :invalid="Boolean(fieldError('branch'))"
              placeholder="Select an empty branch"
              @update:model-value="setBranch"
            />
            <span v-if="fieldError('branch')" class="text-[10px] font-medium text-danger">
              {{ fieldError('branch') }}
            </span>
          </label>

          <div
            v-if="operationalModules.has(module)"
            class="rounded-md border border-blue-100 bg-blue-50 p-4"
          >
            <p class="text-xs font-semibold text-blue-900">{{ action?.label }}</p>
            <p class="mt-1 text-[10px] leading-4 text-blue-700">
              The selected Switch operation is carried from the palette and saved as
              <span class="font-mono">action={{ form.data.action }}</span
              >.
            </p>
          </div>

          <div
            v-if="module === 'hotdesk'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
          >
            The caller enters the Hotdesk ID at call time. Login enforces the user’s configured PIN,
            but logout and toggle’s logout path do not prompt for it. Keep this action behind a
            trusted feature-code route.
          </div>

          <div
            v-if="module === 'do_not_disturb'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
          >
            Switch changes Do Not Disturb for the authenticated caller’s owner, or the authorizing
            device when no owner is available. It does not prompt for a PIN. Keep this action behind
            a trusted feature-code route; GridPBX never stores a user or device ID in this node.
          </div>

          <div
            v-if="module === 'call_forward'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
          >
            Switch changes call forwarding for the authenticated caller's owner. This action does
            not contain a destination number; activate and update collect or use the owner-level
            forwarding configuration at call time. Keep it behind a trusted feature-code route.
          </div>

          <template v-if="module === 'dynamic_cid'">
            <div
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
            >
              Switch replaces the active caller ID for the remainder of this callflow. GridPBX only
              permits a synchronized number owned by this account; arbitrary manual numbers and raw
              Caller-ID List identifiers are never submitted by this form.
            </div>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-700">Caller-ID phone number</span>
              <FormListbox
                :model-value="form.data.phone_number_id ?? ''"
                :options="dynamicCallerIdNumberOptions"
                aria-label="Caller-ID phone number"
                :invalid="Boolean(fieldError('data.phone_number_id'))"
                placeholder="Select an account phone number"
                @update:model-value="setDynamicCallerIdNumber"
              />
              <span
                v-if="fieldError('data.phone_number_id')"
                class="text-[10px] font-medium text-danger"
              >
                {{ fieldError('data.phone_number_id') }}
              </span>
            </label>
            <FormInput
              :model-value="form.data.caller_id_name ?? ''"
              label="Caller-ID name"
              description="Optional display name; carrier presentation rules still apply."
              maxlength="128"
              :error="fieldError('data.caller_id_name')"
              @update:model-value="form.data.caller_id_name = String($event)"
            />
          </template>

          <div
            v-if="module === 'acdc_queue'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
          >
            Switch adds or removes the authenticated caller’s owner from the selected Queue. It does
            not prompt for a PIN. Keep this action behind a trusted feature-code route; GridPBX maps
            the Queue UUID on the server and never stores an agent ID in this node.
          </div>

          <div
            v-if="module === 'conference'"
            class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
          >
            Conference Service asks Switch to discover a conference by an account conference number
            entered by the caller. It does not store or expose a conference resource ID.
          </div>

          <div
            v-if="module === 'voicemail'"
            class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
          >
            Check Voicemail asks Switch to identify an account mailbox and enforce that mailbox's
            login policy. It does not store or expose a voicemail box resource ID, enable caller-ID
            matching, or enable single-mailbox auto-login.
          </div>

          <template v-if="module === 'temporal_route'">
            <div>
              <h3 class="text-xs font-semibold text-slate-700">Affected time-of-day rules</h3>
              <p class="mt-1 text-[10px] text-slate-500">
                Leave all rules clear to apply the Switch operation without a rule filter.
              </p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
              <FormCheckbox
                v-for="rule in temporalRuleOptions"
                :key="rule.id"
                :model-value="form.data.rules ?? []"
                :value="rule.id"
                :label="rule.label"
                variant="compact"
                @update:model-value="setTemporalRules"
              />
            </div>
            <p v-if="fieldError('data.rules')" class="text-[10px] text-danger">
              {{ fieldError('data.rules') }}
            </p>
          </template>

          <label v-if="module === 'ring_group_toggle'" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-700">Ring-group callflow</span>
            <FormListbox
              :model-value="form.data.callflow_id ?? ''"
              :options="callflowOptions"
              aria-label="Ring-group callflow"
              :invalid="Boolean(fieldError('data.callflow_id'))"
              placeholder="Select a callflow containing a ring group"
              @update:model-value="setRingGroupCallflow"
            />
            <span v-if="fieldError('data.callflow_id')" class="text-[10px] text-danger">
              {{ fieldError('data.callflow_id') }}
            </span>
          </label>

          <label v-if="module === 'acdc_queue'" class="grid gap-2">
            <span class="text-xs font-semibold text-slate-700">Queue</span>
            <FormListbox
              :model-value="form.data.queue_id ?? ''"
              :options="queueOptions"
              aria-label="Queue"
              :invalid="Boolean(fieldError('data.queue_id'))"
              placeholder="Select a synchronized queue"
              @update:model-value="setAcdcQueue"
            />
            <span v-if="fieldError('data.queue_id')" class="text-[10px] text-danger">
              {{ fieldError('data.queue_id') }}
            </span>
          </label>

          <template v-if="module === 'pivot'">
            <div
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
            >
              Pivot hands call control to an administrator-approved HTTPS application. Endpoint URLs
              and authentication headers remain server-side and are never exposed in this form.
            </div>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-700">Voice application</span>
              <FormListbox
                :model-value="form.data.endpoint_id ?? ''"
                :options="pivotEndpointOptions"
                aria-label="Voice application"
                :invalid="Boolean(fieldError('data.endpoint_id'))"
                placeholder="Select an approved endpoint"
                @update:model-value="setPivotEndpoint"
              />
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-700">Request method</span>
                <FormListbox
                  :model-value="form.data.method ?? 'get'"
                  :options="pivotMethodOptions"
                  aria-label="Request method"
                  :disabled="!selectedPivotEndpoint"
                  :invalid="Boolean(fieldError('data.method'))"
                  @update:model-value="setPivotMethod"
                />
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-700">Response format</span>
                <FormListbox
                  :model-value="form.data.req_format ?? 'switch'"
                  :options="pivotFormatOptions"
                  aria-label="Response format"
                  :disabled="!selectedPivotEndpoint"
                  :invalid="Boolean(fieldError('data.req_format'))"
                  @update:model-value="setPivotFormat"
                />
              </label>
            </div>
            <p v-if="fieldError('data.endpoint_id')" class="text-[10px] font-medium text-danger">
              {{ fieldError('data.endpoint_id') }}
            </p>
          </template>

          <template v-if="module === 'webhook'">
            <div
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
            >
              Webhook sends call context to an administrator-approved HTTPS receiver and then
              continues the callflow. The private destination stays server-side.
            </div>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-700">Webhook receiver</span>
              <FormListbox
                :model-value="form.data.endpoint_id ?? ''"
                :options="webhookEndpointOptions"
                aria-label="Webhook receiver"
                :invalid="Boolean(fieldError('data.endpoint_id'))"
                placeholder="Select an approved endpoint"
                @update:model-value="setWebhookEndpoint"
              />
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-700">Request method</span>
                <FormListbox
                  :model-value="form.data.http_verb ?? 'post'"
                  :options="webhookMethodOptions"
                  aria-label="Webhook request method"
                  :disabled="!selectedWebhookEndpoint"
                  :invalid="Boolean(fieldError('data.http_verb'))"
                  @update:model-value="setWebhookMethod"
                />
              </label>
              <FormInput
                :model-value="form.data.retries ?? 1"
                label="Attempts"
                type="number"
                min="1"
                :max="selectedWebhookEndpoint?.max_retries ?? 5"
                required
                :error="fieldError('data.retries')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.retries = Number($event)"
              />
            </div>
            <FormTextarea
              v-model="webhookCustomDataJson"
              label="Custom data (JSON object)"
              rows="6"
              placeholder='{ "source": "support" }'
              :error="webhookCustomDataError ?? fieldError('data.custom_data')"
            />
            <p v-if="fieldError('data.endpoint_id')" class="text-[10px] font-medium text-danger">
              {{ fieldError('data.endpoint_id') }}
            </p>
          </template>

          <template v-if="module === 'disa'">
            <div
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
            >
              DISA permits an authenticated caller to dial through this account. The PIN stays
              encrypted server-side; account call restrictions are always enforced and the original
              caller ID is retained.
            </div>
            <div
              v-if="disaOperationalSafety"
              class="rounded-md border p-4 text-[10px] leading-4"
              :class="
                disaOperationalSafety.ready
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                  : 'border-red-200 bg-red-50 text-red-800'
              "
            >
              <strong class="block text-xs">
                {{
                  disaOperationalSafety.ready
                    ? 'Operational ingress guard ready'
                    : 'Operational ingress guard unavailable'
                }}
              </strong>
              <span>
                {{
                  disaOperationalSafety.reason ??
                  'Persistent lockout, rate and concurrency limits, destination policy, redacted monitoring, and emergency stop are enforced.'
                }}
              </span>
            </div>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-700">DISA access policy</span>
              <FormListbox
                :model-value="form.data.access_policy_id ?? ''"
                :options="disaAccessPolicyOptions"
                aria-label="DISA access policy"
                :invalid="Boolean(fieldError('data.access_policy_id'))"
                placeholder="Select an approved access policy"
                @update:model-value="setDisaAccessPolicy"
              />
              <span
                v-if="fieldError('data.access_policy_id')"
                class="text-[10px] font-medium text-danger"
              >
                {{ fieldError('data.access_policy_id') }}
              </span>
            </label>
          </template>

          <template v-if="module === 'offnet' || module === 'resources'">
            <div
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-[10px] leading-4 text-amber-900"
            >
              This is a terminal outbound routing action. GridPBX submits only a public profile
              reference; private carrier and reseller identifiers are resolved server-side.
            </div>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-700">Routing authorization</span>
              <FormListbox
                :model-value="form.data.route_profile_id ?? ''"
                :options="carrierRouteOptions"
                aria-label="Carrier routing authorization"
                :invalid="Boolean(fieldError('data.route_profile_id'))"
                placeholder="Select an approved carrier profile"
                @update:model-value="setCarrierRoute"
              />
              <span
                v-if="fieldError('data.route_profile_id')"
                class="text-[10px] font-medium text-danger"
              >
                {{ fieldError('data.route_profile_id') }}
              </span>
            </label>
          </template>

          <template v-if="module === 'sleep'">
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.duration ?? null"
                label="Duration"
                type="number"
                min="0"
                max="86400000"
                required
                :error="fieldError('data.duration')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.duration = Number($event)"
              />
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Unit</span>
                <FormListbox
                  :model-value="form.data.unit ?? 's'"
                  :options="unitOptions"
                  aria-label="Duration unit"
                  :invalid="Boolean(fieldError('data.unit'))"
                  @update:model-value="form.data.unit = $event as 'ms' | 's' | 'm' | 'h'"
                />
                <span v-if="fieldError('data.unit')" class="text-[10px] text-danger">
                  {{ fieldError('data.unit') }}
                </span>
              </label>
            </div>
          </template>

          <template v-if="module === 'tts'">
            <FormTextarea
              :model-value="form.data.text ?? ''"
              label="Text to speak"
              description="1–1000 characters sent to the selected Switch speech engine."
              required
              :error="fieldError('data.text')"
              @update:model-value="form.data.text = $event"
            />
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.voice ?? ''"
                label="Voice"
                placeholder="female"
                :error="fieldError('data.voice')"
                @update:model-value="form.data.voice = String($event)"
              />
              <FormInput
                :model-value="form.data.language ?? ''"
                label="Language"
                placeholder="en-US"
                :error="fieldError('data.language')"
                @update:model-value="form.data.language = String($event)"
              />
              <label class="grid gap-2 sm:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Speech engine</span>
                <FormListbox
                  :model-value="form.data.engine ?? null"
                  :options="engineOptions"
                  aria-label="Speech engine"
                  :invalid="Boolean(fieldError('data.engine'))"
                  @update:model-value="
                    form.data.engine = $event as
                      'flite' | 'google' | 'ispeech' | 'voicefabric' | null
                  "
                />
                <span v-if="fieldError('data.engine')" class="text-[10px] text-danger">
                  {{ fieldError('data.engine') }}
                </span>
              </label>
            </div>
          </template>

          <template v-if="module === 'collect_dtmf'">
            <FormInput
              :model-value="form.data.collection_name ?? ''"
              label="Collection name"
              description="Optional variable name used to retain the collected digits."
              :error="fieldError('data.collection_name')"
              @update:model-value="form.data.collection_name = String($event)"
            />
            <div class="grid gap-4 sm:grid-cols-3">
              <FormInput
                :model-value="form.data.max_digits ?? null"
                label="Maximum digits"
                type="number"
                min="1"
                max="128"
                required
                :error="fieldError('data.max_digits')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.max_digits = Number($event)"
              />
              <FormInput
                :model-value="form.data.timeout ?? null"
                label="Overall timeout (ms)"
                type="number"
                min="1"
                required
                :error="fieldError('data.timeout')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.timeout = Number($event)"
              />
              <FormInput
                :model-value="form.data.interdigit_timeout ?? null"
                label="Interdigit timeout (ms)"
                type="number"
                min="1"
                required
                :error="fieldError('data.interdigit_timeout')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.interdigit_timeout = Number($event)"
              />
            </div>
          </template>

          <template v-if="module === 'send_dtmf'">
            <FormInput
              :model-value="form.data.digits ?? ''"
              label="DTMF digits"
              description="Digits and keypad symbols sent in order to the active call."
              placeholder="1234#"
              required
              :error="fieldError('data.digits')"
              @update:model-value="form.data.digits = String($event)"
            />
            <FormInput
              :model-value="form.data.duration_ms ?? null"
              label="Tone duration (ms)"
              type="number"
              min="1"
              max="60000"
              required
              :error="fieldError('data.duration_ms')"
              :model-modifiers="{ number: true }"
              @update:model-value="form.data.duration_ms = Number($event)"
            />
          </template>

          <FormInput
            v-if="module === 'flush_dtmf'"
            :model-value="form.data.collection_name ?? ''"
            label="Collection name"
            description="The buffered digit collection to clear. Switch defaults this to default."
            required
            :error="fieldError('data.collection_name')"
            @update:model-value="form.data.collection_name = String($event)"
          />

          <FormInput
            v-if="module === 'language'"
            :model-value="form.data.language ?? ''"
            label="Call language"
            description="Two-letter language with an optional region, such as en or en-US."
            placeholder="en-US"
            required
            :error="fieldError('data.language')"
            @update:model-value="form.data.language = String($event)"
          />

          <template v-if="module === 'manual_presence'">
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.presence_id ?? ''"
                label="Presence ID"
                description="Use a local ID or a complete ID with realm, such as 1001@example.com."
                placeholder="1001"
                maxlength="256"
                required
                :error="fieldError('data.presence_id')"
                @update:model-value="form.data.presence_id = String($event)"
              />
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Presence status</span>
                <FormListbox
                  :model-value="form.data.status ?? 'busy'"
                  :options="presenceStatusOptions"
                  aria-label="Presence status"
                  :invalid="Boolean(fieldError('data.status'))"
                  @update:model-value="form.data.status = $event as 'idle' | 'ringing' | 'busy'"
                />
                <span v-if="fieldError('data.status')" class="text-[10px] text-danger">
                  {{ fieldError('data.status') }}
                </span>
              </label>
            </div>
            <div
              class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
            >
              Idle clears active-call presence, Ringing publishes an early call state, and Busy
              publishes an answered-call state. A local ID inherits the account realm in Switch.
            </div>
          </template>

          <template v-if="module === 'group_pickup'">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Pickup target</span>
              <FormListbox
                :model-value="groupPickupValue"
                :options="groupPickupOptions"
                aria-label="Pickup target"
                placeholder="Select a device, extension, or group"
                :disabled="loading || groupPickupOptions.length === 0"
                :invalid="Boolean(fieldError('data.target_type') || fieldError('data.target_id'))"
                @update:model-value="setGroupPickupTarget"
              />
              <span
                v-if="fieldError('data.target_type') || fieldError('data.target_id')"
                class="text-[10px] text-danger"
              >
                {{ fieldError('data.target_type') || fieldError('data.target_id') }}
              </span>
            </label>
            <div
              class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
            >
              Switch picks up one ringing call for the selected endpoint scope. Device is the most
              specific target, followed by Extension and then Group. Existing private
              <span class="font-mono">approved_*</span> caller restrictions are preserved but not
              exposed by this form.
            </div>
          </template>

          <template v-if="module === 'receive_fax'">
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Fax owner</span>
                <FormListbox
                  :model-value="form.data.owner_id ?? null"
                  :options="extensionOptions"
                  aria-label="Fax owner"
                  placeholder="Select an extension"
                  :disabled="loading || extensionOptions.length === 0"
                  :invalid="Boolean(fieldError('data.owner_id'))"
                  @update:model-value="form.data.owner_id = String($event ?? '')"
                />
                <span v-if="fieldError('data.owner_id')" class="text-[10px] text-danger">
                  {{ fieldError('data.owner_id') }}
                </span>
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">T.38 negotiation</span>
                <FormListbox
                  :model-value="faxOptionValue"
                  :options="faxOptionOptions"
                  aria-label="T.38 negotiation"
                  :invalid="Boolean(fieldError('data.fax_option'))"
                  @update:model-value="setFaxOption"
                />
                <span v-if="fieldError('data.fax_option')" class="text-[10px] text-danger">
                  {{ fieldError('data.fax_option') }}
                </span>
              </label>
            </div>
            <div
              class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
            >
              Incoming fax content is delivered to the selected Extension owner. Automatic uses
              Switch's schema-supported negotiation mode; Enabled and Disabled match Monster's T.38
              checkbox states. Other Switch-managed media properties are preserved.
            </div>
          </template>

          <template v-if="module === 'page_group'">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Page audio</span>
              <FormListbox
                :model-value="form.data.audio ?? 'one-way'"
                :options="pageGroupAudioOptions"
                aria-label="Page audio"
                :invalid="Boolean(fieldError('data.audio'))"
                @update:model-value="form.data.audio = $event as 'one-way' | 'two-way'"
              />
              <span v-if="fieldError('data.audio')" class="text-[10px] text-danger">
                {{ fieldError('data.audio') }}
              </span>
            </label>
            <div>
              <h3 class="text-xs font-semibold text-slate-700">Devices</h3>
              <p class="mt-1 text-[10px] text-slate-500">
                Select 1–20 synchronized devices. {{ form.data.device_ids?.length ?? 0 }} selected.
              </p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
              <FormCheckbox
                v-for="device in deviceOptions"
                :key="device.id"
                :model-value="form.data.device_ids ?? []"
                :value="device.id"
                :label="device.label"
                :description="device.detail"
                :disabled="loading || pageGroupDeviceIsDisabled(device.id)"
                variant="compact"
                @update:model-value="setPageGroupDevices"
              />
            </div>
            <p v-if="fieldError('data.device_ids')" class="text-[10px] text-danger">
              {{ fieldError('data.device_ids') }}
            </p>
            <div
              class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
            >
              GridPBX uses direct Device endpoints so the fan-out limit is enforceable. Existing
              group expansion and barge mode remain read-only. Safe schema timing values stay
              server-owned and are preserved during edits.
            </div>
          </template>

          <template v-if="module === 'ring_group'">
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Ring strategy</span>
                <FormListbox
                  :model-value="form.data.strategy ?? 'simultaneous'"
                  :options="ringGroupStrategyOptions"
                  aria-label="Ring strategy"
                  :invalid="Boolean(fieldError('data.strategy'))"
                  @update:model-value="setRingGroupStrategy"
                />
                <span v-if="fieldError('data.strategy')" class="text-[10px] text-danger">
                  {{ fieldError('data.strategy') }}
                </span>
              </label>
              <FormInput
                :model-value="form.data.repeats ?? 1"
                label="Attempts"
                description="Retry the complete group one to three times."
                type="number"
                min="1"
                max="3"
                required
                :model-modifiers="{ number: true }"
                :error="fieldError('data.repeats')"
                @update:model-value="form.data.repeats = Number($event)"
              />
            </div>

            <section class="grid gap-3">
              <div>
                <h3 class="text-xs font-semibold text-slate-700">Members</h3>
                <p class="mt-1 text-[10px] leading-4 text-slate-500">
                  Add 1–20 synchronized Extensions, Devices, or Groups. In-order strategy follows
                  the displayed order.
                </p>
              </div>
              <FormListbox
                :model-value="null"
                :options="ringGroupTargetOptions"
                aria-label="Add Ring Group member"
                placeholder="Add an extension, device, or group"
                :disabled="
                  loading ||
                  ringGroupTargetOptions.length === 0 ||
                  ringGroupEndpoints().length >= 20
                "
                :invalid="Boolean(fieldError('data.endpoints'))"
                @update:model-value="addRingGroupEndpoint"
              />

              <p
                v-if="ringGroupEndpoints().length === 0"
                class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-center text-[10px] text-slate-600"
              >
                No members selected.
              </p>

              <div
                v-for="(endpoint, index) in ringGroupEndpoints()"
                :key="ringGroupEndpointIdentity(endpoint)"
                class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="grid size-7 place-items-center rounded-md bg-brand-50 text-[10px] font-bold text-brand-700"
                  >
                    {{ index + 1 }}
                  </span>
                  <span class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-700">
                    {{ ringGroupEndpointLabel(endpoint) }}
                  </span>
                  <span class="text-[10px] font-semibold text-slate-500 uppercase">
                    {{ ringGroupEndpointType(endpoint) }}
                  </span>
                  <button
                    type="button"
                    :disabled="index === 0"
                    :aria-label="`Move ${ringGroupEndpointLabel(endpoint)} up`"
                    class="rounded p-1.5 text-slate-500 hover:bg-white disabled:opacity-20"
                    @click="moveRingGroupEndpoint(index, -1)"
                  >
                    <ArrowUpIcon class="size-4" />
                  </button>
                  <button
                    type="button"
                    :disabled="index === ringGroupEndpoints().length - 1"
                    :aria-label="`Move ${ringGroupEndpointLabel(endpoint)} down`"
                    class="rounded p-1.5 text-slate-500 hover:bg-white disabled:opacity-20"
                    @click="moveRingGroupEndpoint(index, 1)"
                  >
                    <ArrowDownIcon class="size-4" />
                  </button>
                  <button
                    type="button"
                    :aria-label="`Remove ${ringGroupEndpointLabel(endpoint)}`"
                    class="rounded p-1.5 text-danger hover:bg-red-50"
                    @click="removeRingGroupEndpoint(index)"
                  >
                    <TrashIcon class="size-4" />
                  </button>
                </div>
                <div
                  class="grid gap-3"
                  :class="
                    form.data.strategy === 'weighted_random' ? 'sm:grid-cols-3' : 'sm:grid-cols-2'
                  "
                >
                  <FormInput
                    :model-value="endpoint.delay"
                    :label="`Member ${index + 1} delay`"
                    description="Seconds before this member starts ringing."
                    type="number"
                    min="0"
                    max="60"
                    required
                    :disabled="form.data.strategy !== 'simultaneous'"
                    :model-modifiers="{ number: true }"
                    :error="fieldError(`data.endpoints.${index}.delay`)"
                    @update:model-value="setRingGroupTiming(index, 'delay', $event)"
                  />
                  <FormInput
                    :model-value="endpoint.timeout"
                    :label="`Member ${index + 1} timeout`"
                    description="Ring this member for 1–60 seconds."
                    type="number"
                    min="1"
                    max="60"
                    required
                    :model-modifiers="{ number: true }"
                    :error="fieldError(`data.endpoints.${index}.timeout`)"
                    @update:model-value="setRingGroupTiming(index, 'timeout', $event)"
                  />
                  <FormInput
                    v-if="form.data.strategy === 'weighted_random'"
                    :model-value="endpoint.weight ?? 20"
                    :label="`Member ${index + 1} weight`"
                    description="Relative chance of being tried earlier, from 1–100."
                    type="number"
                    min="1"
                    max="100"
                    required
                    :model-modifiers="{ number: true }"
                    :error="fieldError(`data.endpoints.${index}.weight`)"
                    @update:model-value="setRingGroupTiming(index, 'weight', $event)"
                  />
                </div>
              </div>
              <p v-if="fieldError('data.endpoints')" class="text-[10px] text-danger">
                {{ fieldError('data.endpoints') }}
              </p>
            </section>

            <section class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4">
              <div>
                <h3 class="text-xs font-semibold text-slate-700">Call handling</h3>
                <p class="mt-1 text-[10px] leading-4 text-slate-500">
                  Control how device forwarding and individual rejections affect this attempt.
                </p>
              </div>
              <FormCheckbox
                :model-value="Boolean(form.data.ignore_forward)"
                label="Ignore device forwarding"
                description="Do not follow SIP redirects from endpoints in this Ring Group. This is Switch's safe default."
                variant="compact"
                @update:model-value="form.data.ignore_forward = Boolean($event)"
              />
              <FormCheckbox
                :model-value="Boolean(form.data.fail_on_single_reject)"
                label="Stop when one device rejects"
                description="Cancel the remaining ringing members when any one endpoint rejects the call."
                variant="compact"
                @update:model-value="form.data.fail_on_single_reject = Boolean($event)"
              />
            </section>

            <section class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4">
              <div>
                <h3 class="text-xs font-semibold text-slate-700">Ringback and phone alerts</h3>
                <p class="mt-1 text-[10px] leading-4 text-slate-500">
                  Ringback is account audio heard while devices ring. Phone alerts are optional SIP
                  Alert-Info values, not audio files.
                </p>
              </div>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Ringback audio</span>
                <FormListbox
                  :model-value="form.data.ringback_media_id ?? null"
                  :options="ringGroupRingbackOptions"
                  aria-label="Ringback audio"
                  :invalid="Boolean(fieldError('data.ringback_media_id'))"
                  @update:model-value="
                    form.data.ringback_media_id = typeof $event === 'string' ? $event : null
                  "
                />
                <span v-if="fieldError('data.ringback_media_id')" class="text-[10px] text-danger">
                  {{ fieldError('data.ringback_media_id') }}
                </span>
              </label>
              <div class="grid gap-4 sm:grid-cols-2">
                <FormInput
                  :model-value="form.data.ringtone_internal ?? ''"
                  label="Internal phone alert"
                  description="Optional Alert-Info value for calls originating inside the account."
                  maxlength="256"
                  :error="fieldError('data.ringtone_internal')"
                  @update:model-value="setRingGroupRingtone('ringtone_internal', $event)"
                />
                <FormInput
                  :model-value="form.data.ringtone_external ?? ''"
                  label="External phone alert"
                  description="Optional Alert-Info value for calls originating outside the account."
                  maxlength="256"
                  :error="fieldError('data.ringtone_external')"
                  @update:model-value="setRingGroupRingtone('ringtone_external', $event)"
                />
              </div>
            </section>

            <div
              class="rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
            >
              GridPBX computes Switch's overall attempt timeout from the member rows. Weighted
              random tries every member sequentially in a newly shuffled weighted order per attempt.
              Raw resource IDs, URL/special-stream ringback values, and unknown fields remain
              private.
            </div>
          </template>

          <template v-if="module === 'response'">
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.code ?? null"
                label="SIP response code"
                description="A final error response from 400 through 699."
                type="number"
                min="400"
                max="699"
                required
                :error="fieldError('data.code')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.code = Number($event)"
              />
              <FormInput
                :model-value="form.data.message ?? ''"
                label="Cause text"
                description="Optional reason phrase returned with the response."
                maxlength="128"
                :error="fieldError('data.message')"
                @update:model-value="form.data.message = String($event)"
              />
            </div>
            <div
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900"
            >
              Response ends this callflow path. Any existing Switch-managed response media remains
              attached and is not exposed by this form.
            </div>
          </template>

          <div
            v-if="module === 'dead_air'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-800"
          >
            Dead Air suppresses media and waits for the caller to hang up. It is normally used as a
            terminal action.
          </div>

          <div
            v-if="module === 'hangup'"
            class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900"
          >
            Hangup ends this callflow path and disconnects the call. The current Switch schema has
            no additional user-managed settings for this action.
          </div>

          <section v-if="module === 'set_variables'" class="grid gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <div class="mr-auto">
                <h3 class="text-xs font-semibold text-slate-700">Custom application variables</h3>
                <p class="mt-0.5 text-[10px] leading-4 text-slate-500">
                  Set the key/value variables made available to this call and subsequent actions.
                </p>
              </div>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-[10px] font-semibold text-slate-700 hover:bg-slate-50"
                @click="addCustomApplicationVariable"
              >
                <PlusIcon class="size-3.5" /> Add variable
              </button>
            </div>

            <p
              v-if="(form.data.custom_application_variables ?? []).length === 0"
              class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-center text-[10px] leading-4 text-slate-600"
            >
              No variables configured. An empty variable object is valid in the Switch schema.
            </p>

            <div
              v-for="(variable, index) in form.data.custom_application_variables ?? []"
              :key="index"
              class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_2rem]"
            >
              <FormInput
                :model-value="variable.key"
                :label="`Variable ${index + 1} name`"
                placeholder="account_code"
                required
                :error="fieldError(`data.custom_application_variables.${index}.key`)"
                @update:model-value="setCustomApplicationVariable(index, 'key', $event)"
              />
              <FormInput
                :model-value="variable.value"
                :label="`Variable ${index + 1} value`"
                placeholder="support"
                :error="fieldError(`data.custom_application_variables.${index}.value`)"
                @update:model-value="setCustomApplicationVariable(index, 'value', $event)"
              />
              <button
                type="button"
                :aria-label="`Remove variable ${index + 1}`"
                class="mt-6 grid size-8 place-items-center rounded-md border border-red-200 bg-white text-danger hover:bg-red-50"
                @click="removeCustomApplicationVariable(index)"
              >
                <TrashIcon class="size-4" />
              </button>
            </div>
            <p
              v-if="fieldError('data.custom_application_variables')"
              class="text-[10px] text-danger"
            >
              {{ fieldError('data.custom_application_variables') }}
            </p>
          </section>

          <template v-if="module === 'set_variable'">
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.variable ?? 'call_priority'"
                label="Variable"
                description="Switch currently supports only the call-priority variable."
                disabled
              />
              <FormInput
                :model-value="form.data.value ?? ''"
                label="Call priority"
                description="Higher values are handled before lower-priority queued calls."
                type="number"
                min="0"
                max="255"
                required
                :error="fieldError('data.value')"
                @update:model-value="form.data.value = String($event)"
              />
            </div>
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Apply to</span>
              <FormListbox
                :model-value="form.data.channel ?? 'a'"
                :options="channelOptions"
                aria-label="Call priority channel"
                :invalid="Boolean(fieldError('data.channel'))"
                @update:model-value="form.data.channel = $event as 'a' | 'both'"
              />
              <span v-if="fieldError('data.channel')" class="text-[10px] text-danger">
                {{ fieldError('data.channel') }}
              </span>
            </label>
          </template>

          <template v-if="module === 'branch_variable'">
            <div class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.variable ?? 'call_priority'"
                label="Variable"
                description="The guided workflow is restricted to Switch Call Priority."
                disabled
              />
              <FormInput
                :model-value="form.data.scope ?? 'custom_channel_vars'"
                label="Scope"
                description="Reads the call-priority custom channel variable set earlier in the route."
                disabled
              />
            </div>
            <div
              class="rounded-md border border-violet-200 bg-violet-50 p-4 text-xs leading-5 text-violet-900"
            >
              Add subsequent actions to a <strong>Priority 0–255</strong> branch or the
              <strong>Default</strong> branch on the route map. Priorities must use canonical whole
              numbers without leading zeroes.
            </div>
          </template>

          <template v-if="module === 'branch_bnumber'">
            <ToggleSwitch
              :model-value="Boolean(form.data.hunt)"
              label="Hunt for a matching callflow"
              description="Use the feature-code capture group to find and run an account callflow instead of an exact child branch."
              :disabled="branchBnumberHasExactChildren"
              @update:model-value="setBranchBnumberHunt"
            />
            <p
              v-if="branchBnumberHasExactChildren"
              class="rounded-md border border-amber-200 bg-amber-50 p-3 text-[10px] leading-4 text-amber-900"
            >
              Remove the existing exact captured-number branches before enabling hunt mode.
            </p>
            <div v-if="form.data.hunt" class="grid gap-4 sm:grid-cols-2">
              <FormInput
                :model-value="form.data.hunt_allow ?? ''"
                label="Allowed-number pattern"
                description="Optional safe regular expression that captured numbers must match."
                placeholder="^1\\d{3}$"
                :error="fieldError('data.hunt_allow')"
                @update:model-value="form.data.hunt_allow = String($event)"
              />
              <FormInput
                :model-value="form.data.hunt_deny ?? ''"
                label="Denied-number pattern"
                description="Optional safe regular expression that blocks matching captured numbers."
                placeholder="^1900"
                :error="fieldError('data.hunt_deny')"
                @update:model-value="form.data.hunt_deny = String($event)"
              />
            </div>
            <div
              class="rounded-md border border-violet-200 bg-violet-50 p-4 text-xs leading-5 text-violet-900"
            >
              <template v-if="form.data.hunt">
                Hunt mode resolves another account callflow dynamically. Only the default
                continuation runs when no eligible callflow is found.
              </template>
              <template v-else>
                Add subsequent actions to exact captured-number branches such as
                <strong>1000</strong>, plus an optional <strong>Default</strong> continuation.
              </template>
            </div>
          </template>

          <div v-if="module === 'set_cid'" class="grid gap-4 sm:grid-cols-2">
            <FormInput
              :model-value="form.data.caller_id_name ?? ''"
              label="Caller ID name"
              description="Leave empty to restore the original name."
              :error="fieldError('data.caller_id_name')"
              @update:model-value="form.data.caller_id_name = String($event)"
            />
            <FormInput
              :model-value="form.data.caller_id_number ?? ''"
              label="Caller ID number"
              description="Leave empty to restore the original number."
              :error="fieldError('data.caller_id_number')"
              @update:model-value="form.data.caller_id_number = String($event)"
            />
          </div>

          <template v-if="module === 'prepend_cid'">
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Action</span>
                <FormListbox
                  :model-value="form.data.action ?? 'prepend'"
                  :options="prependActionOptions"
                  aria-label="Caller ID prefix action"
                  :invalid="Boolean(fieldError('data.action'))"
                  @update:model-value="form.data.action = $event as 'prepend' | 'reset'"
                />
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Apply to</span>
                <FormListbox
                  :model-value="form.data.apply_to ?? 'original'"
                  :options="prependTargetOptions"
                  aria-label="Caller ID prefix target"
                  :invalid="Boolean(fieldError('data.apply_to'))"
                  :disabled="form.data.action === 'reset'"
                  @update:model-value="form.data.apply_to = $event as 'original' | 'current'"
                />
              </label>
              <FormInput
                :model-value="form.data.caller_id_name_prefix ?? ''"
                label="Name prefix"
                :disabled="form.data.action === 'reset'"
                :error="fieldError('data.caller_id_name_prefix')"
                @update:model-value="form.data.caller_id_name_prefix = String($event)"
              />
              <FormInput
                :model-value="form.data.caller_id_number_prefix ?? ''"
                label="Number prefix"
                :disabled="form.data.action === 'reset'"
                :error="fieldError('data.caller_id_number_prefix')"
                @update:model-value="form.data.caller_id_number_prefix = String($event)"
              />
            </div>
          </template>

          <FormInput
            v-if="module === 'set_alert_info'"
            :model-value="form.data.alert_info ?? ''"
            label="Alert-Info"
            description="Distinctive-ring header value sent to the called endpoint."
            placeholder="Bellcore-dr2"
            required
            :error="fieldError('data.alert_info')"
            @update:model-value="form.data.alert_info = String($event)"
          />

          <template v-if="module === 'check_cid'">
            <FormInput
              :model-value="form.data.regex ?? ''"
              label="Caller ID pattern"
              description="A safe regular expression matched against the incoming caller ID number."
              placeholder="^\\+1555"
              required
              :error="fieldError('data.regex')"
              @update:model-value="form.data.regex = String($event)"
            />
            <section class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4">
              <div>
                <h3 class="text-xs font-semibold text-slate-700">Matched-call identity override</h3>
                <p class="mt-0.5 text-[10px] leading-4 text-slate-500">
                  Optional. Switch applies the override only when owner, name, and number are all
                  set.
                </p>
              </div>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Owner extension</span>
                <FormListbox
                  :model-value="form.data.user_id ?? null"
                  :options="callerIdentityOwnerOptions"
                  aria-label="Caller identity owner"
                  :invalid="Boolean(fieldError('data.user_id'))"
                  :disabled="loading"
                  @update:model-value="setCallerIdentityOwner"
                />
                <span v-if="fieldError('data.user_id')" class="text-[10px] text-danger">
                  {{ fieldError('data.user_id') }}
                </span>
              </label>
              <div class="grid gap-4 sm:grid-cols-2">
                <FormInput
                  :model-value="form.data.external_caller_id_name ?? ''"
                  label="External caller ID name"
                  :required="form.data.user_id !== null"
                  :disabled="form.data.user_id === null"
                  :error="fieldError('data.external_caller_id_name')"
                  @update:model-value="form.data.external_caller_id_name = String($event)"
                />
                <FormInput
                  :model-value="form.data.external_caller_id_number ?? ''"
                  label="External caller ID number"
                  :required="form.data.user_id !== null"
                  :disabled="form.data.user_id === null"
                  :error="fieldError('data.external_caller_id_number')"
                  @update:model-value="form.data.external_caller_id_number = String($event)"
                />
              </div>
            </section>
            <div
              class="rounded-md border border-violet-200 bg-violet-50 p-4 text-xs leading-5 text-violet-900"
            >
              Add subsequent actions to the <strong>Caller ID matches</strong> and
              <strong>Caller ID does not match</strong> branches on the route map.
            </div>
          </template>

          <template v-if="module === 'cidlistmatch'">
            <label class="grid gap-2">
              <span class="text-xs font-semibold text-slate-600">Caller-ID List</span>
              <FormListbox
                :model-value="form.data.caller_id_list_id ?? null"
                :options="callerIdListOptions"
                aria-label="Caller-ID List"
                :invalid="Boolean(fieldError('data.caller_id_list_id'))"
                :disabled="loading"
                placeholder="Select a synchronized list"
                @update:model-value="form.data.caller_id_list_id = String($event ?? '')"
              />
              <span
                v-if="fieldError('data.caller_id_list_id')"
                class="text-[10px] font-medium text-danger"
              >
                {{ fieldError('data.caller_id_list_id') }}
              </span>
            </label>
            <p
              v-if="!loading && callerIdListOptions.length === 0"
              class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900"
            >
              No Caller-ID Lists are projected for this account. Synchronize Caller-ID Lists before
              adding this action.
            </p>
            <div
              class="rounded-md border border-violet-200 bg-violet-50 p-4 text-xs leading-5 text-violet-900"
            >
              Add subsequent actions to the <strong>Caller ID matches</strong> and
              <strong>Caller ID does not match</strong> branches on the route map.
            </div>
          </template>

          <section v-if="module === 'missed_call_alert'" class="grid gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <div class="mr-auto">
                <h3 class="text-xs font-semibold text-slate-700">Notification recipients</h3>
                <p class="mt-0.5 text-[10px] text-slate-500">
                  Public extension IDs are translated to private Switch IDs by the API.
                </p>
              </div>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-[10px] font-semibold text-slate-700"
                @click="addRecipient('email')"
              >
                <PlusIcon class="size-3.5" /> Email
              </button>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-[10px] font-semibold text-slate-700"
                @click="addRecipient('user')"
              >
                <PlusIcon class="size-3.5" /> Extension
              </button>
            </div>

            <p v-if="loading" class="text-xs text-slate-500">Loading synchronized extensions…</p>
            <div
              v-for="(recipient, index) in form.data.recipients ?? []"
              :key="`${index}-${recipient.type}`"
              class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[10rem_minmax(0,1fr)_2rem]"
            >
              <FormListbox
                :model-value="recipient.type"
                :options="recipientTypeOptions"
                :aria-label="`Recipient ${index + 1} type`"
                @update:model-value="setRecipientType(index, $event)"
              />
              <FormInput
                v-if="recipient.type === 'email'"
                :model-value="recipient.id"
                :label="`Recipient ${index + 1} email`"
                type="email"
                required
                :error="fieldError(`data.recipients.${index}.id`)"
                @update:model-value="setRecipientId(index, String($event))"
              />
              <label v-else class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Extension</span>
                <FormListbox
                  :model-value="recipient.id || null"
                  :options="extensionOptions"
                  :aria-label="`Recipient ${index + 1} extension`"
                  :invalid="Boolean(fieldError(`data.recipients.${index}.id`))"
                  :disabled="loading || extensionOptions.length === 0"
                  placeholder="Select an extension"
                  @update:model-value="typeof $event === 'string' && setRecipientId(index, $event)"
                />
                <span
                  v-if="fieldError(`data.recipients.${index}.id`)"
                  class="text-[10px] text-danger"
                >
                  {{ fieldError(`data.recipients.${index}.id`) }}
                </span>
              </label>
              <button
                type="button"
                :aria-label="`Remove recipient ${index + 1}`"
                class="grid size-8 place-items-center rounded-md border border-red-200 bg-white text-danger hover:bg-red-50"
                @click="removeRecipient(index)"
              >
                <TrashIcon class="size-4" />
              </button>
            </div>
            <p v-if="fieldError('data.recipients')" class="text-[10px] text-danger">
              {{ fieldError('data.recipients') }}
            </p>
          </section>

          <section v-if="module === 'tts' || module === 'collect_dtmf'" class="grid gap-3">
            <div>
              <h3 class="text-xs font-semibold text-slate-700">DTMF terminators</h3>
              <p class="mt-0.5 text-[10px] text-slate-500">
                These keys stop playback or complete digit collection.
              </p>
            </div>
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
              <FormCheckbox
                v-for="digit in callflowDtmfDigits"
                :key="digit"
                :model-value="form.data.terminators ?? []"
                :value="digit"
                :label="digit"
                variant="compact"
                @update:model-value="setTerminators"
              />
            </div>
            <p v-if="fieldError('data.terminators')" class="text-[10px] text-danger">
              {{ fieldError('data.terminators') }}
            </p>
          </section>

          <template v-if="module === 'record_call' || module === 'record_caller'">
            <div class="grid gap-4 sm:grid-cols-2">
              <label v-if="module === 'record_call'" class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Recording action</span>
                <FormListbox
                  :model-value="form.data.action ?? 'start'"
                  :options="recordingActionOptions"
                  aria-label="Recording action"
                  :invalid="Boolean(fieldError('data.action'))"
                  @update:model-value="form.data.action = $event as 'start' | 'stop'"
                />
                <span v-if="fieldError('data.action')" class="text-[10px] text-danger">
                  {{ fieldError('data.action') }}
                </span>
              </label>
              <label class="grid gap-2">
                <span class="text-xs font-semibold text-slate-600">Format</span>
                <FormListbox
                  :model-value="form.data.format ?? null"
                  :options="recordingFormatOptions"
                  aria-label="Recording format"
                  :invalid="Boolean(fieldError('data.format'))"
                  @update:model-value="form.data.format = $event as 'mp3' | 'wav' | null"
                />
                <span v-if="fieldError('data.format')" class="text-[10px] text-danger">
                  {{ fieldError('data.format') }}
                </span>
              </label>
              <FormInput
                :model-value="form.data.time_limit ?? null"
                label="Time limit (seconds)"
                type="number"
                min="5"
                max="10800"
                required
                :error="fieldError('data.time_limit')"
                :model-modifiers="{ number: true }"
                @update:model-value="form.data.time_limit = Number($event)"
              />
              <FormInput
                v-if="module === 'record_call'"
                :model-value="form.data.label ?? ''"
                label="Recording label"
                :error="fieldError('data.label')"
                @update:model-value="form.data.label = String($event)"
              />
              <FormInput
                v-if="module === 'record_call'"
                :model-value="form.data.record_min_sec ?? null"
                label="Minimum length (seconds)"
                type="number"
                min="0"
                max="10800"
                :error="fieldError('data.record_min_sec')"
                :model-modifiers="{ number: true }"
                @update:model-value="
                  form.data.record_min_sec = $event === '' ? null : Number($event)
                "
              />
              <FormInput
                v-if="module === 'record_call'"
                :model-value="form.data.record_sample_rate ?? null"
                label="Sample rate (Hz)"
                type="number"
                min="8000"
                max="192000"
                :error="fieldError('data.record_sample_rate')"
                :model-modifiers="{ number: true }"
                @update:model-value="
                  form.data.record_sample_rate = $event === '' ? null : Number($event)
                "
              />
            </div>
          </template>
        </div>
      </section>

      <section class="card-surface grid gap-4 p-5">
        <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Behavior</h2>
        <ToggleSwitch
          v-if="module === 'tts'"
          :model-value="Boolean(form.data.endless_playback)"
          label="Endless playback"
          description="Repeat speech until a terminator is entered or the call ends."
          @update:model-value="form.data.endless_playback = $event"
        />
        <ToggleSwitch
          v-if="module === 'record_call'"
          :model-value="Boolean(form.data.record_on_answer)"
          label="Record on answer"
          description="Delay recording until the call is answered."
          @update:model-value="form.data.record_on_answer = $event"
        />
        <ToggleSwitch
          v-if="module === 'record_call'"
          :model-value="Boolean(form.data.record_on_bridge)"
          label="Record on bridge"
          description="Delay recording until both call legs are bridged."
          @update:model-value="form.data.record_on_bridge = $event"
        />
        <ToggleSwitch
          v-if="module === 'record_call'"
          :model-value="Boolean(form.data.should_follow_transfer)"
          label="Follow transfers"
          description="Continue recording if the call is transferred."
          @update:model-value="form.data.should_follow_transfer = $event"
        />
        <ToggleSwitch
          v-if="module === 'set_variables'"
          :model-value="Boolean(form.data.export)"
          label="Export to future bridged legs"
          description="Ask Switch to carry these variables to channels bridged later in this call."
          @update:model-value="form.data.export = $event"
        />
        <ToggleSwitch
          :model-value="Boolean(form.data.skip_module)"
          label="Skip this action"
          description="Preserve the action but ask Switch not to execute it."
          @update:model-value="form.data.skip_module = $event"
        />
      </section>

      <div
        class="flex gap-3 rounded-md border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-800"
      >
        <ShieldCheckIcon class="mt-0.5 size-5 shrink-0" />
        <p>
          Storage URLs, HTTP methods, origins, and media names are server-managed. Existing values
          and child branches are preserved without exposing them to this form.
        </p>
      </div>

      <p v-if="error" class="rounded-md border border-red-100 bg-red-50 p-4 text-xs text-danger">
        {{ error }}
      </p>

      <div class="slide-over-actions flex justify-end gap-3">
        <button
          type="button"
          class="h-10 rounded-md border border-slate-300 bg-white px-5 text-xs font-semibold text-slate-700"
          :disabled="saving"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="h-10 rounded-md bg-brand-500 px-5 text-xs font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="saving"
        >
          {{
            saving
              ? 'Saving…'
              : rootConfiguration && context.operation === 'create'
                ? 'Use action'
                : context.operation === 'create'
                  ? 'Add action'
                  : 'Save action'
          }}
        </button>
      </div>
    </form>
  </CrudSlideOver>
</template>
