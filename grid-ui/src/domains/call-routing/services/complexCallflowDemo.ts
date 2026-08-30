import type { Callflow, CallflowNode } from '../types/callRouting'

function inline(
  module: string,
  children: Record<string, CallflowNode> = {},
  branch: CallflowNode['branch'] = null,
  settings: Record<string, unknown> | null = null,
): CallflowNode {
  return {
    module,
    target: null,
    reference_status: 'not_applicable',
    branch,
    settings,
    children,
  }
}

function resource(
  module: string,
  type: NonNullable<CallflowNode['target']>['type'],
  label: string,
  children: Record<string, CallflowNode> = {},
  branch: CallflowNode['branch'] = null,
): CallflowNode {
  return {
    module,
    target: {
      type,
      id: `demo-${module}-${label.toLowerCase().replaceAll(/[^a-z0-9]+/g, '-')}`,
      label,
    },
    reference_status: 'resolved',
    branch,
    settings: null,
    children,
  }
}

const defaultBranch = (label = 'Default branch'): NonNullable<CallflowNode['branch']> => ({
  key: '_',
  label,
  kind: 'default',
})

const menuBranch = (key: string, label = key): NonNullable<CallflowNode['branch']> => ({
  key,
  label,
  kind: 'key',
})

function countNodes(node: CallflowNode): number {
  return 1 + Object.values(node.children).reduce((sum, child) => sum + countNodes(child), 0)
}

function maxDepth(node: CallflowNode): number {
  const childDepths = Object.values(node.children).map(maxDepth)
  return 1 + (childDepths.length ? Math.max(...childDepths) : 0)
}

export function createComplexCallflowDemo(): Callflow {
  const supportBranch = inline(
    'tts',
    {
      _: resource('device', 'device', 'Support desk phone', {
        _: resource(
          'voicemail',
          'voicemail',
          'Support overflow mailbox',
          {
            _: inline('response', {}, defaultBranch(), {
              code: 486,
              message: 'Support route completed',
              skip_module: false,
            }),
          },
          defaultBranch(),
        ),
      }, defaultBranch()),
    },
    menuBranch('1', 'Key 1 · Support'),
    {
      text: 'Routing your call to support.',
      voice: 'female',
      language: 'en-US',
      skip_module: false,
    },
  )

  const conferenceBranch = resource(
    'conference',
    'conference',
    'Operations bridge',
    {
      _: inline(
        'record_call',
        {
          _: inline('language', {
            _: inline('response', {}, defaultBranch(), {
              code: 486,
              message: 'Conference route completed',
              skip_module: false,
            }),
          }, defaultBranch(), { language: 'en-US', skip_module: false }),
        },
        defaultBranch(),
        {
          action: 'start',
          format: 'wav',
          time_limit: 1800,
          skip_module: false,
        },
      ),
    },
    menuBranch('2', 'Key 2 · Conference'),
  )

  const timeoutBranch = inline(
    'missed_call_alert',
    {
      _: inline(
        'collect_dtmf',
        {
          _: resource(
            'voicemail',
            'voicemail',
            'Main reception mailbox',
            {
              _: inline('response', {}, defaultBranch(), {
                code: 486,
                message: 'No input received',
                skip_module: false,
              }),
            },
            defaultBranch(),
          ),
        },
        defaultBranch(),
        {
          collection_name: 'demo_selection',
          max_digits: 4,
          timeout: 8000,
          skip_module: false,
        },
      ),
    },
    menuBranch('timeout', 'No input / timeout'),
    { recipients: [{ type: 'email', id: 'ops@example.test' }], skip_module: false },
  )

  const forwardingBranch = inline(
    'call_forward',
    {
      _: resource(
        'user',
        'extension',
        'Duty manager',
        {
          _: resource(
            'voicemail',
            'voicemail',
            'Duty manager mailbox',
            {},
            defaultBranch(),
          ),
        },
        defaultBranch(),
      ),
    },
    menuBranch('*', 'Star · Duty manager'),
    { action: 'activate' },
  )

  const scheduled = resource(
    'menu',
    'menu',
    'Main IVR',
    {
      '1': supportBranch,
      '2': conferenceBranch,
      timeout: timeoutBranch,
      '*': forwardingBranch,
    },
    { key: 'rule_set', label: 'Business hours match', kind: 'schedule_match' },
  )

  const afterHours = inline(
    'play',
    {
      _: resource(
        'device',
        'device',
        'After-hours duty phone',
        {
          _: resource('voicemail', 'voicemail', 'After-hours mailbox', {}, defaultBranch()),
        },
        defaultBranch(),
      ),
    },
    defaultBranch('Outside business hours'),
    { media_label: 'After-hours greeting' },
  )

  const flow = resource('temporal_route', 'temporal_rule_set', 'Office hours and holidays', {
    rule_set: scheduled,
    _: afterHours,
  })

  return {
    id: 'ui-only-complex-callflow-demo',
    name: 'Complex callflow demo · UI only',
    route_type: 'unassigned',
    numbers: ['DEMO-2001'],
    patterns: [],
    flags: ['ui_only'],
    modules: [
      'temporal_route',
      'menu',
      'tts',
      'device',
      'voicemail',
      'response',
      'conference',
      'record_call',
      'language',
      'collect_dtmf',
      'missed_call_alert',
      'call_forward',
      'user',
      'play',
    ],
    root_module: 'temporal_route',
    node_count: countNodes(flow),
    max_depth: maxDepth(flow),
    feature_code: null,
    flow,
    linked_extension: null,
    phone_numbers: [],
    sync_status: 'healthy',
    last_synced_at: null,
  }
}
