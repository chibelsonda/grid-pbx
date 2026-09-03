import { computed, reactive, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import {
  availableCallflowBranches,
  supportsCapturedNumberBranches,
} from '../services/callflowTreeBranches'
import {
  callflowDtmfDigits,
  createCallflowInlineNodeFormSchema,
} from '../schemas/callflowInlineNodeFormSchema'
import type {
  CallflowInlineModule,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeData,
  CallflowInlineNodeFormData,
  CallflowInlineNodeUpdateInput,
  CallflowNodeEditorContext,
  CallflowTreeBranchKey,
} from '../types/callRouting'

type InlineFormState = {
  branch: CallflowTreeBranchKey | null
  data: CallflowInlineNodeFormData
}

function defaults(
  module: CallflowInlineModule,
  preset: Readonly<Partial<CallflowInlineNodeData>> = {},
): CallflowInlineNodeFormData {
  let data: CallflowInlineNodeFormData

  switch (module) {
    case 'sleep':
      data = { duration: 0, unit: 's', skip_module: false }
      break
    case 'tts':
      data = {
        text: '',
        voice: 'female',
        language: null,
        engine: null,
        endless_playback: false,
        terminators: [...callflowDtmfDigits],
        skip_module: false,
      }
      break
    case 'collect_dtmf':
      data = {
        collection_name: null,
        interdigit_timeout: 2000,
        max_digits: 1,
        terminators: ['#'],
        timeout: 5000,
        skip_module: false,
      }
      break
    case 'record_call':
      data = {
        action: 'start',
        format: null,
        label: null,
        record_min_sec: null,
        record_on_answer: false,
        record_on_bridge: false,
        record_sample_rate: null,
        should_follow_transfer: true,
        time_limit: 3600,
        skip_module: false,
      }
      break
    case 'record_caller':
      data = { format: null, time_limit: 3600, skip_module: false }
      break
    case 'send_dtmf':
      data = { digits: '', duration_ms: 2000, skip_module: false }
      break
    case 'flush_dtmf':
      data = { collection_name: 'default', skip_module: false }
      break
    case 'dead_air':
      data = { skip_module: false }
      break
    case 'language':
      data = { language: 'en', skip_module: false }
      break
    case 'response':
      data = { code: 486, message: null, skip_module: false }
      break
    case 'hangup':
      data = { skip_module: false }
      break
    case 'set_variable':
      data = { variable: 'call_priority', value: '0', channel: 'a', skip_module: false }
      break
    case 'set_variables':
      data = { custom_application_variables: [], export: false, skip_module: false }
      break
    case 'manual_presence':
      data = { presence_id: '', status: 'busy', skip_module: false }
      break
    case 'group_pickup':
      data = { target_type: 'extension', target_id: '', skip_module: false }
      break
    case 'page_group':
      data = { audio: 'one-way', device_ids: [], skip_module: false }
      break
    case 'ring_group':
      data = {
        strategy: 'simultaneous',
        endpoints: [],
        repeats: 1,
        ignore_forward: true,
        fail_on_single_reject: false,
        ringback_media_id: null,
        ringtone_internal: null,
        ringtone_external: null,
        skip_module: false,
      }
      break
    case 'receive_fax':
      data = { owner_id: '', fax_option: false, skip_module: false }
      break
    case 'conference':
      data = { service_mode: true, skip_module: false }
      break
    case 'voicemail':
      data = { action: 'check', skip_module: false }
      break
    case 'branch_variable':
      data = {
        variable: 'call_priority',
        scope: 'custom_channel_vars',
        skip_module: false,
      }
      break
    case 'branch_bnumber':
      data = {
        hunt: false,
        hunt_allow: null,
        hunt_deny: null,
        skip_module: false,
      }
      break
    case 'missed_call_alert':
      data = { recipients: [], skip_module: false }
      break
    case 'set_cid':
      data = { caller_id_name: '', caller_id_number: '', skip_module: false }
      break
    case 'prepend_cid':
      data = {
        action: 'prepend',
        apply_to: 'original',
        caller_id_name_prefix: '',
        caller_id_number_prefix: '',
        skip_module: false,
      }
      break
    case 'set_alert_info':
      data = { alert_info: '', skip_module: false }
      break
    case 'check_cid':
      data = {
        regex: '.*',
        use_absolute_mode: false,
        external_caller_id_name: null,
        external_caller_id_number: null,
        user_id: null,
        skip_module: false,
      }
      break
    case 'cidlistmatch':
      data = { caller_id_list_id: '', skip_module: false }
      break
    case 'temporal_route':
      data = { action: 'disable', rules: [], skip_module: false }
      break
    case 'ring_group_toggle':
      data = { action: 'login', callflow_id: '', skip_module: false }
      break
    case 'acdc_queue':
      data = { action: 'login', queue_id: '', skip_module: false }
      break
    case 'hotdesk':
      data = { action: 'login', skip_module: false }
      break
    case 'do_not_disturb':
      data = { action: 'activate', skip_module: false }
      break
    case 'call_forward':
      data = { action: 'activate', skip_module: false }
      break
    case 'dynamic_cid':
      data = {
        action: 'static',
        phone_number_id: '',
        caller_id_name: '',
        skip_module: false,
      }
      break
    case 'pivot':
      data = { endpoint_id: '', method: 'get', req_format: 'switch', skip_module: false }
      break
    case 'webhook':
      data = {
        endpoint_id: '',
        http_verb: 'post',
        retries: 1,
        custom_data: {},
        skip_module: false,
      }
      break
    case 'disa':
      data = { access_policy_id: '', skip_module: false }
      break
    case 'offnet':
    case 'resources':
      data = { route_profile_id: '', skip_module: false }
      break
  }

  return { ...data, ...preset }
}

function settingsForEdit(
  module: CallflowInlineModule,
  settings: Record<string, unknown> | null | undefined,
): CallflowInlineNodeFormData {
  const data = defaults(module)
  if (!settings) return data

  const writable = data as Record<string, unknown>
  for (const key of Object.keys(writable)) {
    if (Object.hasOwn(settings, key)) writable[key] = settings[key]
  }

  if (
    module === 'collect_dtmf' &&
    !Object.hasOwn(settings, 'terminators') &&
    typeof settings.terminator === 'string'
  ) {
    data.terminators = [settings.terminator]
  }

  if (module === 'set_variables') {
    const variables = settings.custom_application_vars
    data.custom_application_variables =
      variables !== null && typeof variables === 'object' && !Array.isArray(variables)
        ? Object.entries(variables)
            .filter((entry): entry is [string, string] => typeof entry[1] === 'string')
            .map(([key, value]) => ({ key, value }))
        : []
  }

  if (module === 'ring_group') {
    data.endpoints = Array.isArray(settings.endpoints)
      ? settings.endpoints.map((endpoint) => {
          const normalized = { ...endpoint }
          if (normalized.weight === null) delete normalized.weight

          return normalized
        })
      : []
  }

  return data
}

export function useCallflowInlineNodeForm(
  contextSource: MaybeRefOrGetter<CallflowNodeEditorContext>,
) {
  const context = computed(() => toValue(contextSource))
  const module = computed(() => context.value.module as CallflowInlineModule)
  const branches = computed(() =>
    context.value.operation === 'create'
      ? context.value.placement && context.value.placement !== 'append'
        ? [{ value: '_' as const, label: 'Next step', description: 'Occupied continuation branch' }]
        : availableCallflowBranches(context.value.node)
      : [],
  )
  const usesCapturedNumberBranch = computed(
    () =>
      context.value.operation === 'create' && supportsCapturedNumberBranches(context.value.node),
  )
  const form = reactive<InlineFormState>({
    branch: null,
    data: defaults(module.value, context.value.preset),
  })
  const validationErrors = ref<FormErrors>({})

  function initialize(): void {
    form.branch = branches.value[0]?.value ?? null
    form.data =
      context.value.operation === 'update'
        ? settingsForEdit(module.value, context.value.node.settings)
        : defaults(module.value, context.value.preset)
    validationErrors.value = {}
  }

  watch(context, initialize, { immediate: true })
  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate(): CallflowInlineNodeCreateInput | CallflowInlineNodeUpdateInput | null {
    const result = validateForm(
      createCallflowInlineNodeFormSchema(
        module.value,
        branches.value.map(({ value }) => value),
        context.value.operation === 'create',
        usesCapturedNumberBranch.value,
        Object.keys(context.value.node.children),
      ),
      { branch: form.branch, data: { ...form.data } },
    )
    validationErrors.value = result.errors

    if (!result.success) return null

    const parsed = result.data.data as CallflowInlineNodeFormData
    const data: CallflowInlineNodeData =
      module.value === 'set_variables'
        ? {
            custom_application_vars: Object.fromEntries(
              (parsed.custom_application_variables ?? []).map(({ key, value }) => [
                key.trim(),
                value,
              ]),
            ),
            export: Boolean(parsed.export),
            skip_module: parsed.skip_module,
          }
        : parsed
    if (context.value.operation === 'create') {
      return {
        parent_path: [...context.value.path],
        branch: result.data.branch as CallflowTreeBranchKey,
        placement: context.value.placement ?? 'append',
        module: module.value,
        data,
      }
    }

    return {
      node_path: [...context.value.path],
      module: module.value,
      data,
    }
  }

  return { form, module, branches, usesCapturedNumberBranch, validationErrors, validate }
}
