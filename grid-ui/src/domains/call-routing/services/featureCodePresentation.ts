import type { FeatureCodePresentation, FeatureCodeRoute } from '../types/featureCode'

const specialLabels: Record<string, string> = {
  directed_ext_pickup: 'Directed Extension Pickup',
  intercom: 'Intercom',
  move: 'Call Move',
  park_and_retrieve: 'Park and Retrieve',
  retrieve: 'Retrieve Parked Call',
  valet: 'Valet Parking',
  'privacy[mode=full]': 'Full Caller-ID Privacy',
  'voicemail[single_mailbox_login]': 'Single Mailbox Login',
}

const moduleDetails: Record<string, Pick<FeatureCodePresentation, 'category' | 'dependency'>> = {
  call_forward: {
    category: 'Call forwarding',
    dependency: 'Authorizing device or owner resolved by Switch at call time',
  },
  do_not_disturb: {
    category: 'Account access',
    dependency: 'Authorizing device or owner resolved by Switch at call time',
  },
  group_pickup_feature: {
    category: 'Pickup',
    dependency: 'Captured extension and account pickup membership',
  },
  hotdesk: {
    category: 'Account access',
    dependency: 'Caller device and Hotdesk profile resolved at call time',
  },
  intercom: {
    category: 'Call handling',
    dependency: 'Captured destination extension',
  },
  move: {
    category: 'Call handling',
    dependency: 'Authorizing device and an active movable call',
  },
  park: {
    category: 'Parking',
    dependency: 'Account parking configuration and active call state',
  },
  privacy: {
    category: 'Call handling',
    dependency: 'Captured destination number',
  },
  voicemail: {
    category: 'Voicemail',
    dependency: 'Mailbox selected, captured, or inferred at call time',
  },
}

function words(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function actionName(name: string | null): string | null {
  return name?.match(/\[action=["']?([^\]"']+)["']?\]/)?.[1] ?? null
}

function usesStarPrefix(route: FeatureCodeRoute): boolean {
  return (
    route.numbers.some((number) => number.startsWith('*')) ||
    route.patterns.some((pattern) => pattern.startsWith('^\\*') || pattern.startsWith('^*'))
  )
}

function dialCode(route: FeatureCodeRoute): string {
  const number = route.feature_code.number?.replaceAll('\\', '')

  if (!number) return 'Pattern route'
  if (!usesStarPrefix(route) || (number.startsWith('*') && number !== '*')) return number

  return `*${number}`
}

export function presentFeatureCode(route: FeatureCodeRoute): FeatureCodePresentation {
  const name = route.feature_code.name
  const action = actionName(name)
  const module = route.root_module ?? 'unknown'
  const details = moduleDetails[module] ?? {
    category: 'Other',
    dependency: 'Runtime dependencies require a module-specific audit',
  }

  return {
    label:
      (name ? specialLabels[name] : null) ??
      (action ? `${words(module)} ${words(action)}` : words(name ?? module)),
    category: details.category,
    action: action ? words(action) : words(module),
    dialCode: dialCode(route),
    dependency: details.dependency,
  }
}
