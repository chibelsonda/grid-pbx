import {
  deviceSupportsTab,
  supportsDeviceFieldGroup,
  supportsDeviceOption,
  supportsDeviceRecording,
  supportsMusicOnHold,
  supportsOutboundFlags,
  supportsIgnoreCompletedElsewhere,
  supportsProvisioning,
  usesForwarding,
  usesSip,
} from './deviceForm'
import type {
  DeviceBasicForm,
  DeviceConfiguration,
  DeviceInput,
  DeviceMetaflowAction,
  DeviceMetaflowChild,
  DeviceSchemaCompatibility,
  DeviceSipInput,
  FullDeviceSipInput,
} from './types/device'

function nullable(value: string | null): string | null {
  const trimmed = value?.trim() ?? ''

  return trimmed === '' ? null : trimmed
}

function metaflowChildrenInput(children: DeviceMetaflowChild[]): DeviceMetaflowChild[] {
  return children.map((child) => ({
    ...child,
    data: { ...child.data },
    children: metaflowChildrenInput(child.children),
  }))
}

function metaflowActionsInput(actions: DeviceMetaflowAction[]): DeviceMetaflowAction[] {
  return actions.map((action) => ({
    ...action,
    data: { ...action.data },
    children: metaflowChildrenInput(action.children),
  }))
}

export function normalizeMacAddress(value: string | null): string | null {
  const hex = value?.replace(/[^0-9a-f]/gi, '').toUpperCase() ?? ''

  if (hex === '') return null
  if (hex.length !== 12) return value?.trim() ?? null

  return hex.match(/.{2}/g)?.join(':') ?? null
}

function endpointModel(
  form: DeviceBasicForm,
  configuration: DeviceConfiguration,
): string | number | string[] | null {
  const value = configuration.provision.endpoint_model

  if (Array.isArray(value)) {
    const models = value.map((model) => model.trim()).filter((model) => model !== '')

    return models.length > 0 ? models : null
  }

  if (typeof value === 'number') return value

  return nullable(value ?? form.model)
}

function sipInput(
  form: DeviceBasicForm,
  configuration: DeviceConfiguration,
  compatibility: DeviceSchemaCompatibility,
): DeviceSipInput {
  if (form.device_type === 'sip_uri') {
    return {
      invite_format: configuration.sip.invite_format,
      route: nullable(configuration.sip.route),
    }
  }

  const {
    username_configured: _usernameConfigured,
    custom_sip_interface: customSipInterface,
    forward: sipForward,
    proxy: sipProxy,
    static_invite: staticInvite,
    transport: sipTransport,
    ignore_completed_elsewhere: ignoreCompletedElsewhere,
    custom_sip_headers: customSipHeaders,
    ...sipConfiguration
  } = configuration.sip
  void _usernameConfigured

  return {
    ...sipConfiguration,
    username: nullable(configuration.sip.username),
    password: nullable(configuration.sip.password),
    realm: nullable(configuration.sip.realm),
    ip: nullable(configuration.sip.ip),
    number: nullable(configuration.sip.number),
    route: nullable(configuration.sip.route),
    static_route: nullable(configuration.sip.static_route),
    ...(compatibility.sip.custom_sip_interface
      ? { custom_sip_interface: nullable(customSipInterface) }
      : {}),
    ...(compatibility.sip.forward ? { forward: nullable(sipForward) } : {}),
    ...(compatibility.sip.proxy ? { proxy: nullable(sipProxy) } : {}),
    ...(compatibility.sip.static_invite ? { static_invite: nullable(staticInvite) } : {}),
    ...(compatibility.sip.transport ? { transport: nullable(sipTransport) } : {}),
    ...(supportsIgnoreCompletedElsewhere(form.device_type)
      ? { ignore_completed_elsewhere: ignoreCompletedElsewhere }
      : {}),
    custom_sip_headers: {
      in: customSipHeaders.in.map((header) => ({ ...header })),
      out: customSipHeaders.out.map((header) => ({ ...header })),
    },
  } satisfies FullDeviceSipInput
}

export function buildDeviceInput(
  form: DeviceBasicForm,
  configuration: DeviceConfiguration,
  compatibility: DeviceSchemaCompatibility,
): DeviceInput {
  const provisionable = supportsProvisioning(form.device_type)
  const supportsEndpointBehavior = supportsDeviceFieldGroup(form.device_type, 'endpoint-behavior')
  const supportsAdvancedRouting = supportsDeviceFieldGroup(form.device_type, 'advanced-routing')

  return {
    name: form.name.trim(),
    device_type: form.device_type,
    ...(provisionable
      ? {
          provision: {
            endpoint_brand: nullable(form.make),
            endpoint_family: nullable(form.family),
            endpoint_model: endpointModel(form, configuration),
            ...(compatibility.provision.template_id
              ? { id: nullable(configuration.provision.id) }
              : {}),
            ...(compatibility.provision.check_sync_event
              ? { check_sync_event: nullable(configuration.provision.check_sync_event) }
              : {}),
            ...(compatibility.provision.check_sync_reload
              ? { check_sync_reload: nullable(configuration.provision.check_sync_reload) }
              : {}),
            ...(compatibility.provision.check_sync_reboot
              ? { check_sync_reboot: nullable(configuration.provision.check_sync_reboot) }
              : {}),
          },
          mac_address: normalizeMacAddress(form.mac_address),
        }
      : {}),
    is_enabled: form.is_enabled,
    assigned_extension_id: nullable(form.assigned_extension_id),
    ...(usesForwarding(form.device_type)
      ? {
          call_forward: {
            enabled: form.is_enabled,
            number: nullable(configuration.call_forward.number),
            direct_calls_only: configuration.call_forward.direct_calls_only,
            failover: configuration.call_forward.failover,
            ignore_early_media: configuration.call_forward.ignore_early_media,
            require_keypress: configuration.call_forward.require_keypress,
            keep_caller_id: configuration.call_forward.keep_caller_id,
            substitute: configuration.call_forward.substitute,
          },
        }
      : {}),
    ...(usesSip(form.device_type)
      ? {
          sip: sipInput(form, configuration, compatibility),
          ...(deviceSupportsTab(form.device_type, 'audio')
            ? { media: configuration.media }
            : supportsDeviceOption(form.device_type, 'fax')
              ? { media: { fax_option: configuration.media.fax_option } }
              : {}),
        }
      : {}),
    ...(deviceSupportsTab(form.device_type, 'caller-id')
      ? {
          caller_id: configuration.caller_id,
          caller_id_options: configuration.caller_id_options,
        }
      : {}),
    contact_list: configuration.contact_list,
    ...(supportsEndpointBehavior
      ? {
          call_waiting: configuration.call_waiting,
          do_not_disturb: configuration.do_not_disturb,
          exclude_from_queues: configuration.exclude_from_queues,
          language: nullable(configuration.language),
          timezone: nullable(configuration.timezone),
          presence_id: nullable(configuration.presence_id),
          mwi_unsolicited_updates: configuration.mwi_unsolicited_updates,
          register_overwrite_notify: configuration.register_overwrite_notify,
          suppress_unregister_notifications: configuration.suppress_unregister_notifications,
        }
      : {}),
    ...(supportsDeviceOption(form.device_type, 'ringtones')
      ? {
          ringtones: {
            internal: nullable(configuration.ringtones.internal),
            external: nullable(configuration.ringtones.external),
          },
        }
      : {}),
    ...(deviceSupportsTab(form.device_type, 'restrictions')
      ? { call_restriction: configuration.call_restriction }
      : {}),
    ...(supportsDeviceRecording(form.device_type)
      ? { call_recording: configuration.call_recording }
      : {}),
    ...(supportsMusicOnHold(form.device_type)
      ? { music_on_hold: { media_id: configuration.music_on_hold.media_id } }
      : {}),
    ...(supportsOutboundFlags(form.device_type) || form.device_type === 'fax'
      ? {
          outbound_flags: {
            static:
              form.device_type === 'fax'
                ? [...new Set(['fax', ...configuration.outbound_flags.static])]
                : [...configuration.outbound_flags.static],
            dynamic: [...configuration.outbound_flags.dynamic],
          },
        }
      : {}),
    ...(supportsAdvancedRouting
      ? {
          dial_plan: {
            system: [...configuration.dial_plan.system],
            rules: configuration.dial_plan.rules.map((rule) => ({
              pattern: rule.pattern.trim(),
              description: nullable(rule.description),
              prefix: nullable(rule.prefix),
              suffix: nullable(rule.suffix),
            })),
          },
          metaflows: {
            binding_digit: configuration.metaflows.binding_digit,
            digit_timeout: configuration.metaflows.digit_timeout,
            listen_on: configuration.metaflows.listen_on,
            actions: metaflowActionsInput(configuration.metaflows.actions),
          },
          flags: [...configuration.flags],
          formatters: configuration.formatters.map((formatter) => ({ ...formatter })),
        }
      : {}),
  }
}
