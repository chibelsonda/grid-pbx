import { reactive, ref, watch } from 'vue'
import { validateForm, type FormErrors } from '@/shared/forms/zod'
import { accountSettingsSchema } from '../schemas/accountSettingsSchema'
import type {
  AccountCallRecording,
  AccountDetail,
  AccountRecordingParameters,
  AccountRecordingRules,
  AccountRecordingSource,
  AccountSettingsInput,
  AccountRestrictionOption,
} from '../types/account'
import type { MetaflowChild } from '@/shared/switch/metaflows/types'

export function useAccountSettingsForm(
  account: AccountDetail,
  restrictionOptions: AccountRestrictionOption[],
) {
  const callRestriction = Object.fromEntries(
    [
      ...new Set([
        ...restrictionOptions.map(({ key }) => key),
        ...Object.keys(account.configuration.call_restriction),
      ]),
    ].map((key) => [
      key,
      { action: account.configuration.call_restriction[key]?.action ?? 'inherit' },
    ]),
  )
  const form = reactive({
    name: account.name,
    organization_name: account.configuration.organization_name ?? '',
    timezone: account.timezone ?? '',
    language: account.configuration.language ?? '',
    call_waiting_enabled: account.configuration.call_waiting_enabled,
    do_not_disturb_enabled: account.configuration.do_not_disturb_enabled,
    outbound_privacy: account.configuration.outbound_privacy,
    show_rate: account.configuration.show_rate,
    ringtone_internal: account.configuration.ringtone_internal ?? '',
    ringtone_external: account.configuration.ringtone_external ?? '',
    caller_id: {
      internal: {
        name: account.configuration.caller_id.internal.name ?? '',
        number: account.configuration.caller_id.internal.number ?? '',
      },
      external: {
        name: account.configuration.caller_id.external.name ?? '',
        phone_number_id: account.configuration.caller_id.external.phone_number_id,
        preserve_number: account.configuration.caller_id.external.unresolved,
      },
      emergency: {
        name: account.configuration.caller_id.emergency.name ?? '',
        phone_number_id: account.configuration.caller_id.emergency.phone_number_id,
        preserve_number: account.configuration.caller_id.emergency.unresolved,
      },
    },
    call_restriction: callRestriction,
    call_recording: hydrateCallRecording(account.configuration.call_recording),
    dial_plan: {
      system: [...account.configuration.dial_plan.system],
      rules: account.configuration.dial_plan.rules.map((rule) => ({
        pattern: rule.pattern,
        description: rule.description ?? '',
        prefix: rule.prefix ?? '',
        suffix: rule.suffix ?? '',
      })),
    },
    formatters: account.configuration.formatters.map((formatter) => ({
      ...formatter,
      prefix: formatter.prefix ?? '',
      regex: formatter.regex ?? '',
      suffix: formatter.suffix ?? '',
      value: formatter.value ?? '',
    })),
    preflow: {
      callflow_id: account.configuration.preflow.callflow_id,
      preserve_callflow: account.configuration.preflow.unresolved,
    },
    metaflows: {
      binding_digit: account.configuration.metaflows.binding_digit,
      digit_timeout: account.configuration.metaflows.digit_timeout,
      listen_on: account.configuration.metaflows.listen_on,
      actions: account.configuration.metaflows.actions.map((action) => ({
        ...action,
        data: { ...action.data },
        children: cloneMetaflowChildren(action.children),
      })),
    },
  })
  const validationErrors = ref<FormErrors>({})

  watch(form, () => (validationErrors.value = {}), { deep: true })

  function validate() {
    const result = validateForm<AccountSettingsInput>(accountSettingsSchema, form)
    validationErrors.value = result.errors
    if (result.success) {
      result.data.call_recording = compactCallRecording(
        result.data.call_recording as AccountCallRecording,
        account.configuration.call_recording,
      )
    }
    return result
  }

  return { form, validate, validationErrors }
}

function cloneMetaflowChildren(children: MetaflowChild[]): MetaflowChild[] {
  return children.map((child) => ({
    ...child,
    data: { ...child.data },
    children: cloneMetaflowChildren(child.children),
  }))
}

function compactCallRecording(
  recording: AccountCallRecording,
  original: Partial<AccountCallRecording>,
): AccountSettingsInput['call_recording'] {
  const compacted: AccountSettingsInput['call_recording'] = {}

  for (const target of ['account', 'endpoint'] as const) {
    for (const direction of ['any', 'inbound', 'outbound'] as const) {
      for (const network of ['any', 'onnet', 'offnet'] as const) {
        const parameters = recording[target][direction][network]
        const existed = original[target]?.[direction]?.[network] !== undefined
        const configured =
          parameters.enabled ||
          parameters.format !== 'mp3' ||
          parameters.record_min_sec !== null ||
          !parameters.record_on_answer ||
          parameters.record_on_bridge ||
          parameters.record_sample_rate !== null ||
          parameters.time_limit !== null

        if (!existed && !configured) continue

        compacted[target] ??= {}
        compacted[target][direction] ??= {}
        compacted[target][direction][network] = parameters
      }
    }
  }

  return compacted
}

function defaultRecordingParameters(): AccountRecordingParameters {
  return {
    enabled: false,
    format: 'mp3',
    record_min_sec: null,
    record_on_answer: true,
    record_on_bridge: false,
    record_sample_rate: null,
    time_limit: null,
  }
}

function hydrateRecordingSource(source?: Partial<AccountRecordingSource>): AccountRecordingSource {
  return {
    any: { ...defaultRecordingParameters(), ...source?.any },
    onnet: { ...defaultRecordingParameters(), ...source?.onnet },
    offnet: { ...defaultRecordingParameters(), ...source?.offnet },
  }
}

function hydrateRecordingRules(rules?: Partial<AccountRecordingRules>): AccountRecordingRules {
  return {
    any: hydrateRecordingSource(rules?.any),
    inbound: hydrateRecordingSource(rules?.inbound),
    outbound: hydrateRecordingSource(rules?.outbound),
  }
}

function hydrateCallRecording(recording?: Partial<AccountCallRecording>): AccountCallRecording {
  return {
    account: hydrateRecordingRules(recording?.account),
    endpoint: hydrateRecordingRules(recording?.endpoint),
  }
}
