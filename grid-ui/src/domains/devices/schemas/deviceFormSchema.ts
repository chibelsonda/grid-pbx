import { z } from 'zod'
import { audioCodecs, isForwardingOnlyDevice, videoCodecs } from '../deviceForm'
import type {
  DeviceMetaflowChild,
  DeviceMetaflowNode,
  DeviceProvisioningCatalog,
  DeviceSchemaCompatibility,
} from '../types/device'

const nullableString = (maximum: number) => z.string().trim().max(maximum).nullable()
const nullableInteger = (minimum: number, maximum: number) =>
  z.number().int().min(minimum).max(maximum).nullable()

const callerIdIdentitySchema = z
  .object({
    name: nullableString(35),
    number: nullableString(35),
  })
  .strict()

const recordingParametersSchema = z
  .object({
    enabled: z.boolean(),
    format: z.enum(['mp3', 'wav']),
    record_min_sec: nullableInteger(0, 3600),
    record_on_answer: z.boolean(),
    record_on_bridge: z.boolean(),
    record_sample_rate: z
      .union([z.literal(8000), z.literal(16000), z.literal(32000), z.literal(48000)])
      .nullable(),
    time_limit: nullableInteger(5, 10800),
  })
  .strict()

const recordingSourceSchema = z
  .object({
    any: recordingParametersSchema,
    onnet: recordingParametersSchema,
    offnet: recordingParametersSchema,
  })
  .strict()

const sipHeaderSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, 'Enter a header name.')
      .max(128)
      .regex(/^[A-Za-z0-9_-]+$/, 'Use only letters, numbers, underscores, and hyphens.')
      .refine(
        (name) => !/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i.test(name),
        'Sensitive authentication headers cannot be configured here.',
      ),
    value: z.string().trim().min(1, 'Enter a header value.').max(1024),
  })
  .strict()

const fullSipSchema = z
  .object({
    method: z.enum(['password', 'ip']),
    username: z.string().trim().min(2).max(32).nullable(),
    password: z.string().min(12, 'Use at least 12 characters.').max(32).nullable(),
    realm: z
      .string()
      .trim()
      .min(4)
      .max(253)
      .regex(/^[.\w_-]+$/, 'Use only letters, numbers, periods, underscores, and hyphens.')
      .nullable(),
    expire_seconds: nullableInteger(30, 86400),
    invite_format: z.enum(['username', 'npan', '1npan', 'e164', 'route', 'strip_plus', 'contact']),
    ip: z.union([z.ipv4(), z.ipv6()]).nullable(),
    number: nullableString(64),
    route: nullableString(2048),
    static_route: nullableString(2048),
    custom_sip_interface: nullableString(255).optional(),
    forward: nullableString(255).optional(),
    proxy: nullableString(2048).optional(),
    static_invite: nullableString(2048).optional(),
    transport: nullableString(32).optional(),
    ignore_completed_elsewhere: z.boolean().optional(),
    custom_sip_headers: z
      .object({ in: z.array(sipHeaderSchema).max(50), out: z.array(sipHeaderSchema).max(50) })
      .strict()
      .optional(),
  })
  .strict()
  .superRefine((sip, context) => {
    if (sip.method === 'ip' && sip.ip === null) {
      context.addIssue({
        code: 'custom',
        path: ['ip'],
        message: 'Enter the IP address authorized to use this device.',
      })
    }
  })

const sipUriSchema = z
  .object({
    invite_format: z.enum(['username', 'npan', '1npan', 'e164', 'route', 'strip_plus', 'contact']),
    route: nullableString(2048),
  })
  .strict()

const metaflowModules = [
  'audio_level',
  'break',
  'callflow',
  'hangup',
  'hold_control',
  'move',
  'play',
  'record_call',
  'resume',
  'say',
  'sound_touch',
  'transfer',
  'tts',
] as const

const metaflowFields: Record<(typeof metaflowModules)[number], string[]> = {
  audio_level: ['action', 'level', 'mode'],
  break: [],
  callflow: ['callflow_id', 'captures', 'collected'],
  hangup: [],
  hold_control: ['action'],
  move: ['device_id', 'extension_id', 'auto_answer', 'can_call_self', 'dial_strategy'],
  play: ['media_id', 'leg'],
  record_call: [
    'action',
    'dtmf_leg',
    'format',
    'label',
    'media_name',
    'origin',
    'record_min_sec',
    'record_on_answer',
    'record_on_bridge',
    'record_sample_rate',
    'time_limit',
  ],
  resume: [],
  say: ['gender', 'language', 'method', 'text', 'type'],
  sound_touch: [
    'action',
    'adjust_in_octaves',
    'adjust_in_semitones',
    'hook_dtmf',
    'pitch',
    'rate',
    'sending_leg',
    'tempo',
  ],
  transfer: ['leg', 'target', 'transfer_type'],
  tts: ['engine', 'language', 'leg', 'text', 'voice'],
}

function validateMetaflowNode(
  node: Pick<DeviceMetaflowNode, 'module' | 'data'>,
  context: z.RefinementCtx,
): void {
  Object.keys(node.data).forEach((field) => {
    if (!metaflowFields[node.module].includes(field)) {
      context.addIssue({
        code: 'custom',
        path: ['data'],
        message: 'The selected metaflow action contains an unsupported field.',
      })
    }
  })

  if (node.module === 'play' && !node.data.media_id) {
    context.addIssue({
      code: 'custom',
      path: ['data', 'media_id'],
      message: 'Select media to play.',
    })
  }

  if (node.module === 'callflow' && !node.data.callflow_id) {
    context.addIssue({
      code: 'custom',
      path: ['data', 'callflow_id'],
      message: 'Select a callflow to run.',
    })
  }

  if (node.module === 'move' && !node.data.device_id && !node.data.extension_id) {
    context.addIssue({
      code: 'custom',
      path: ['data'],
      message: 'Select a destination device or extension.',
    })
  }

  const resourceFields =
    node.module === 'play'
      ? ['media_id']
      : node.module === 'callflow'
        ? ['callflow_id']
        : node.module === 'move'
          ? ['device_id', 'extension_id']
          : []

  resourceFields.forEach((field) => {
    const value = node.data[field]

    if (value !== undefined && !z.uuid().safeParse(value).success) {
      context.addIssue({
        code: 'custom',
        path: ['data', field],
        message: 'Select a projected resource.',
      })
    }
  })
}

const metaflowDataSchema = z.record(
  z.string(),
  z.union([z.string().max(2048), z.number(), z.boolean(), z.null()]),
)

const metaflowChildSchema: z.ZodType<DeviceMetaflowChild> = z.lazy(() =>
  z
    .object({
      key: z.string().trim().min(1, 'Enter a branch key.').max(64),
      module: z.enum(metaflowModules),
      data: metaflowDataSchema,
      children: z.array(metaflowChildSchema).max(20).default([]),
    })
    .strict()
    .superRefine(validateMetaflowNode),
)

const metaflowActionSchema = z
  .object({
    trigger_type: z.enum(['number', 'pattern']),
    trigger: z.string().trim().min(1, 'Enter a metaflow trigger.').max(255),
    module: z.enum(metaflowModules),
    data: metaflowDataSchema,
    children: z.array(metaflowChildSchema).max(20).default([]),
  })
  .strict()
  .superRefine(validateMetaflowNode)

const deviceFormBaseSchema = z
  .object({
    name: z.string().trim().min(1, 'Enter a device name.').max(128),
    device_type: z.enum([
      'sip_device',
      'cellphone',
      'smartphone',
      'softphone',
      'landline',
      'fax',
      'ata',
      'sip_uri',
    ]),
    provision: z
      .object({
        id: nullableString(255).optional(),
        endpoint_brand: nullableString(255),
        endpoint_family: nullableString(255),
        endpoint_model: z
          .union([
            nullableString(255),
            z.number().int(),
            z.array(z.string().trim().min(1).max(255)).max(32),
          ])
          .nullable(),
        check_sync_event: nullableString(255).optional(),
        check_sync_reload: nullableString(255).optional(),
        check_sync_reboot: nullableString(255).optional(),
      })
      .strict()
      .optional(),
    mac_address: z
      .string()
      .trim()
      .max(64)
      .regex(/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/, 'Enter a valid MAC address.')
      .nullable()
      .optional(),
    is_enabled: z.boolean(),
    assigned_extension_id: z.uuid().nullable(),
    call_forward: z
      .object({
        enabled: z.boolean(),
        number: nullableString(35),
        direct_calls_only: z.boolean().optional(),
        failover: z.boolean().optional(),
        ignore_early_media: z.boolean().optional(),
        keep_caller_id: z.boolean(),
        require_keypress: z.boolean(),
        substitute: z.boolean().optional(),
      })
      .strict()
      .optional(),
    sip: z.union([fullSipSchema, sipUriSchema]).optional(),
    media: z
      .object({
        audio: z
          .object({ codecs: z.array(z.enum(audioCodecs)).max(audioCodecs.length) })
          .strict()
          .optional(),
        video: z
          .object({ codecs: z.array(z.enum(videoCodecs)).max(videoCodecs.length) })
          .strict()
          .optional(),
        bypass_media: z.union([z.boolean(), z.literal('auto')]).optional(),
        encryption: z
          .object({
            enforce_security: z.boolean(),
            methods: z.array(z.enum(['zrtp', 'srtp'])).max(2),
          })
          .strict()
          .optional(),
        fax_option: z.boolean(),
        ignore_early_media: z.boolean().optional(),
        progress_timeout: nullableInteger(0, 3600).optional(),
      })
      .strict()
      .optional(),
    caller_id: z
      .object({
        internal: callerIdIdentitySchema,
        external: callerIdIdentitySchema,
        emergency: callerIdIdentitySchema,
        asserted: callerIdIdentitySchema.extend({ realm: nullableString(253) }).strict(),
      })
      .strict()
      .optional(),
    caller_id_options: z
      .object({ outbound_privacy: z.enum(['full', 'name', 'number', 'none']) })
      .strict()
      .optional(),
    call_waiting: z.object({ enabled: z.boolean() }).strict().optional(),
    do_not_disturb: z.object({ enabled: z.boolean() }).strict().optional(),
    contact_list: z.object({ exclude: z.boolean() }).strict().optional(),
    exclude_from_queues: z.boolean().optional(),
    language: nullableString(32).optional(),
    timezone: nullableString(255).optional(),
    presence_id: nullableString(255).optional(),
    mwi_unsolicited_updates: z.boolean().optional(),
    register_overwrite_notify: z.boolean().optional(),
    suppress_unregister_notifications: z.boolean().optional(),
    ringtones: z
      .object({ internal: nullableString(256), external: nullableString(256) })
      .strict()
      .optional(),
    call_restriction: z
      .record(z.string(), z.object({ action: z.enum(['inherit', 'deny']) }).strict())
      .optional(),
    call_recording: z
      .object({
        any: recordingSourceSchema,
        inbound: recordingSourceSchema,
        outbound: recordingSourceSchema,
      })
      .strict()
      .optional(),
    music_on_hold: z.object({ media_id: z.uuid().nullable() }).strict().optional(),
    outbound_flags: z
      .object({
        static: z.array(z.string().trim().min(1).max(255)).max(64),
        dynamic: z.array(z.string().trim().min(1).max(255)).max(64),
      })
      .strict()
      .optional(),
    dial_plan: z
      .object({
        system: z.array(z.string().trim().min(1).max(255)).max(64),
        rules: z
          .array(
            z
              .object({
                pattern: z.string().trim().min(1, 'Enter a dial-plan pattern.').max(512),
                description: nullableString(255),
                prefix: nullableString(64),
                suffix: nullableString(64),
              })
              .strict(),
          )
          .max(64),
      })
      .strict()
      .optional(),
    metaflows: z
      .object({
        binding_digit: z
          .enum(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#'])
          .nullable(),
        digit_timeout: nullableInteger(0, 60000),
        listen_on: z.enum(['both', 'self', 'peer']).nullable(),
        actions: z.array(metaflowActionSchema).max(50).default([]),
      })
      .strict()
      .superRefine((metaflows, context) => {
        const triggers = new Set<string>()
        let nodeCount = 0

        const inspectChildren = (
          children: DeviceMetaflowChild[],
          path: Array<string | number>,
          depth: number,
        ): void => {
          const keys = new Set<string>()

          children.forEach((child, index) => {
            nodeCount += 1

            if (depth > 8) {
              context.addIssue({
                code: 'custom',
                path: [...path, index, 'children'],
                message: 'Metaflow branches may be at most 8 levels deep.',
              })
            }

            if (keys.has(child.key)) {
              context.addIssue({
                code: 'custom',
                path: [...path, index, 'key'],
                message: 'Branch keys must be unique at this level.',
              })
            }
            keys.add(child.key)
            inspectChildren(child.children, [...path, index, 'children'], depth + 1)
          })
        }

        metaflows.actions.forEach((action, index) => {
          nodeCount += 1
          const identity = `${action.trigger_type}:${action.trigger}`

          if (action.trigger_type === 'number' && !/^[0-9]+$/.test(action.trigger)) {
            context.addIssue({
              code: 'custom',
              path: ['actions', index, 'trigger'],
              message: 'Number metaflow triggers may contain digits only.',
            })
          }

          if (triggers.has(identity)) {
            context.addIssue({
              code: 'custom',
              path: ['actions', index, 'trigger'],
              message: 'Each metaflow trigger must be unique within its type.',
            })
          }

          triggers.add(identity)
          inspectChildren(action.children, ['actions', index, 'children'], 1)
        })

        if (nodeCount > 100) {
          context.addIssue({
            code: 'custom',
            path: ['actions'],
            message: 'A device may contain at most 100 guided metaflow nodes.',
          })
        }
      })
      .optional(),
    flags: z.array(z.string().trim().min(1).max(255)).max(64).optional(),
    formatters: z
      .array(
        z
          .object({
            field: z
              .string()
              .trim()
              .min(1, 'Enter the Switch field to format.')
              .max(128)
              .regex(/^[A-Za-z0-9_]+$/, 'Use only letters, numbers, and underscores.'),
            direction: z.enum(['inbound', 'outbound', 'both']).nullable(),
            match_invite_format: z.boolean(),
            prefix: nullableString(1024),
            regex: nullableString(2048),
            strip: z.boolean(),
            suffix: nullableString(1024),
            value: nullableString(1024),
          })
          .strict(),
      )
      .max(64)
      .optional(),
  })
  .strict()

const fullDeviceSchemaCompatibility: DeviceSchemaCompatibility = {
  source: 'connected_switch',
  schema_id: 'devices',
  call_forward: { number_max_length: 35 },
  sip: {
    invite_formats: ['username', 'npan', '1npan', 'e164', 'route', 'strip_plus', 'contact'],
    custom_sip_interface: true,
    forward: true,
    proxy: true,
    static_invite: true,
    transport: true,
  },
  provision: {
    template_id: true,
    endpoint_model_types: ['string', 'integer', 'array'],
    check_sync_event: true,
    check_sync_reload: true,
    check_sync_reboot: true,
  },
}

export function createDeviceFormSchema(
  compatibility: DeviceSchemaCompatibility,
  provisioningCatalog?: DeviceProvisioningCatalog,
) {
  return deviceFormBaseSchema.superRefine((device, context) => {
    const forwardingOnly = isForwardingOnlyDevice(device.device_type)
    const minimalWorkflow = forwardingOnly || device.device_type === 'sip_uri'

    if (!minimalWorkflow && device.provision) {
      const provisioningValues = [
        device.provision.endpoint_brand,
        device.provision.endpoint_family,
        device.provision.endpoint_model,
      ]
      const configured = provisioningValues.some((value) =>
        Array.isArray(value) ? value.length > 0 : value !== null && String(value).trim() !== '',
      )

      if (configured) {
        const paths = ['endpoint_brand', 'endpoint_family', 'endpoint_model'] as const
        const complete = provisioningValues.every((value) =>
          Array.isArray(value) ? value.length > 0 : value !== null && String(value).trim() !== '',
        )

        provisioningValues.forEach((value, index) => {
          const missing = Array.isArray(value)
            ? value.length === 0
            : value === null || String(value).trim() === ''

          if (missing) {
            context.addIssue({
              code: 'custom',
              path: ['provision', paths[index]!],
              message: 'Select the complete provisioning brand, family, and model.',
            })
          }
        })

        if (!device.mac_address) {
          context.addIssue({
            code: 'custom',
            path: ['mac_address'],
            message: 'Enter the MAC address used by the provisioner.',
          })
        }

        if (complete && provisioningCatalog?.available) {
          const matches = (selected: string | number, ...candidates: unknown[]) =>
            candidates.some(
              (candidate) =>
                (typeof candidate === 'string' || typeof candidate === 'number') &&
                String(selected).trim().toLowerCase() === String(candidate).trim().toLowerCase(),
            )
          const brand = provisioningCatalog.brands.find((candidate) =>
            matches(device.provision!.endpoint_brand!, candidate.id, candidate.name),
          )

          if (!brand) {
            context.addIssue({
              code: 'custom',
              path: ['provision', 'endpoint_brand'],
              message: 'Select a brand from the current provisioning catalog.',
            })
          } else {
            const family = brand.families.find((candidate) =>
              matches(device.provision!.endpoint_family!, candidate.id, candidate.name),
            )

            if (!family) {
              context.addIssue({
                code: 'custom',
                path: ['provision', 'endpoint_family'],
                message: 'Select a family belonging to the selected brand.',
              })
            } else {
              const selectedModels = Array.isArray(device.provision.endpoint_model)
                ? device.provision.endpoint_model
                : [device.provision.endpoint_model!]
              const models = selectedModels.map((selected) =>
                family.models.find((candidate) =>
                  matches(selected, candidate.id, candidate.name, candidate.template_id),
                ),
              )

              if (models.some((model) => !model)) {
                context.addIssue({
                  code: 'custom',
                  path: ['provision', 'endpoint_model'],
                  message: 'Select a model belonging to the selected brand and family.',
                })
              } else if (
                device.provision.id &&
                !models.some((model) => matches(device.provision!.id!, model?.template_id))
              ) {
                context.addIssue({
                  code: 'custom',
                  path: ['provision', 'id'],
                  message: 'The provisioning template does not belong to the selected model.',
                })
              }
            }
          }
        }
      }
    }

    if (minimalWorkflow) {
      for (const field of [
        'provision',
        'mac_address',
        'media',
        'caller_id',
        'caller_id_options',
        'call_waiting',
        'do_not_disturb',
        'exclude_from_queues',
        'language',
        'timezone',
        'presence_id',
        'mwi_unsolicited_updates',
        'register_overwrite_notify',
        'suppress_unregister_notifications',
        'ringtones',
        'call_restriction',
        'call_recording',
        'music_on_hold',
        'outbound_flags',
        'dial_plan',
        'metaflows',
        'flags',
        'formatters',
      ] as const) {
        if (device[field] === undefined) continue

        context.addIssue({
          code: 'custom',
          path: [field],
          message: 'This field is not available for the selected device type.',
        })
      }
    }

    if (forwardingOnly && device.sip !== undefined) {
      context.addIssue({
        code: 'custom',
        path: ['sip'],
        message: 'Forwarding-only devices do not use SIP endpoint configuration.',
      })
    }

    if (forwardingOnly && device.call_forward === undefined) {
      context.addIssue({
        code: 'custom',
        path: ['call_forward'],
        message: 'Enter the forwarding configuration for this device type.',
      })
    }

    if (
      forwardingOnly &&
      device.call_forward !== undefined &&
      device.is_enabled !== device.call_forward.enabled
    ) {
      context.addIssue({
        code: 'custom',
        path: ['call_forward', 'enabled'],
        message: 'Forwarding state must match the device enabled state.',
      })
    }

    for (const direction of ['in', 'out'] as const) {
      const names = new Set<string>()

      if (
        !device.sip ||
        !('custom_sip_headers' in device.sip) ||
        !device.sip.custom_sip_headers
      ) {
        continue
      }

      device.sip.custom_sip_headers[direction].forEach((header, index) => {
        const normalized = header.name.toLowerCase()

        if (names.has(normalized)) {
          context.addIssue({
            code: 'custom',
            path: ['sip', 'custom_sip_headers', direction, index, 'name'],
            message: 'Header names must be unique in this direction.',
          })
        }

        names.add(normalized)
      })
    }

    const dialPlanPatterns = new Set<string>()
    device.dial_plan?.rules.forEach((rule, index) => {
      if (dialPlanPatterns.has(rule.pattern)) {
        context.addIssue({
          code: 'custom',
          path: ['dial_plan', 'rules', index, 'pattern'],
          message: 'Dial-plan patterns must be unique.',
        })
      }

      dialPlanPatterns.add(rule.pattern)
    })

    if (
      ['cellphone', 'smartphone', 'landline'].includes(device.device_type) &&
      device.call_forward?.enabled &&
      device.call_forward.number === null
    ) {
      context.addIssue({
        code: 'custom',
        path: ['call_forward', 'number'],
        message: 'Enter the number that should receive forwarded calls.',
      })
    }

    if (device.device_type === 'sip_uri' && device.sip && 'method' in device.sip) {
      context.addIssue({
        code: 'custom',
        path: ['sip'],
        message: 'SIP URI devices accept only the destination route and invite format.',
      })
    }

    if (device.device_type === 'sip_uri' && (!device.sip || device.sip.route === null)) {
      context.addIssue({
        code: 'custom',
        path: ['sip', 'route'],
        message: 'Enter the SIP URI that should receive calls.',
      })
    }

    if (
      ['sip_device', 'smartphone', 'softphone', 'fax', 'ata'].includes(device.device_type) &&
      (!device.sip || !('method' in device.sip))
    ) {
      context.addIssue({
        code: 'custom',
        path: ['sip'],
        message: 'Enter the SIP configuration for this device type.',
      })
    }

    if (
      device.call_forward?.number &&
      device.call_forward.number.length > compatibility.call_forward.number_max_length
    ) {
      context.addIssue({
        code: 'custom',
        path: ['call_forward', 'number'],
        message: `Use no more than ${compatibility.call_forward.number_max_length} characters for this Switch.`,
      })
    }

    if (device.sip && !compatibility.sip.invite_formats.includes(device.sip.invite_format)) {
      context.addIssue({
        code: 'custom',
        path: ['sip', 'invite_format'],
        message: 'The selected invite format is not supported by the connected Switch.',
      })
    }

    for (const field of [
      'custom_sip_interface',
      'forward',
      'proxy',
      'static_invite',
      'transport',
    ] as const) {
      if (
        device.sip &&
        field in device.sip &&
        device.sip[field as keyof typeof device.sip] !== null &&
        !compatibility.sip[field]
      ) {
        context.addIssue({
          code: 'custom',
          path: ['sip', field],
          message: 'This field is not supported by the connected Switch.',
        })
      }
    }

    if (
      device.provision?.id !== null &&
      device.provision?.id !== undefined &&
      !compatibility.provision.template_id
    ) {
      context.addIssue({
        code: 'custom',
        path: ['provision', 'id'],
        message: 'Provisioner template IDs are not supported by the connected Switch.',
      })
    }

    for (const field of ['check_sync_event', 'check_sync_reload', 'check_sync_reboot'] as const) {
      if (
        device.provision?.[field] !== null &&
        device.provision?.[field] !== undefined &&
        !compatibility.provision[field]
      ) {
        context.addIssue({
          code: 'custom',
          path: ['provision', field],
          message: 'This legacy provisioning field is not supported by the connected Switch.',
        })
      }
    }

    const endpointModel = device.provision?.endpoint_model
    const endpointModelType = Array.isArray(endpointModel)
      ? 'array'
      : typeof endpointModel === 'number'
        ? 'integer'
        : endpointModel === null || endpointModel === undefined
          ? null
          : 'string'

    if (
      endpointModelType !== null &&
      !compatibility.provision.endpoint_model_types.includes(endpointModelType)
    ) {
      context.addIssue({
        code: 'custom',
        path: ['provision', 'endpoint_model'],
        message: 'The endpoint model does not match the connected Switch schema.',
      })
    }
  })
}

export const deviceFormSchema = createDeviceFormSchema(fullDeviceSchemaCompatibility)

export type ValidatedDeviceInput = z.infer<typeof deviceFormSchema>
