export type ExtensionAdvancedSection =
  | 'caller-id'
  | 'options'
  | 'call-forward'
  | 'password'
  | 'recording'
  | 'hot-desking'
  | 'restrictions'
  | 'media'
  | 'routing-profile'
  | 'metaflows'

const sectionFields: Record<ExtensionAdvancedSection, readonly string[]> = {
  'caller-id': ['caller_id'],
  options: [
    'language',
    'presence_id',
    'call_waiting',
    'do_not_disturb',
    'contact_list',
    'caller_id_options',
  ],
  'call-forward': ['call_forward'],
  password: ['password', 'password_confirmation', 'require_password_update'],
  recording: ['call_recording'],
  'hot-desking': ['hotdesk'],
  restrictions: ['call_restriction'],
  media: ['media', 'music_on_hold', 'ringtones'],
  'routing-profile': ['dial_plan', 'formatters', 'profile', 'pronounced_name'],
  metaflows: ['metaflows'],
}

export function extensionAdvancedSectionForField(field: string): ExtensionAdvancedSection | null {
  for (const [section, prefixes] of Object.entries(sectionFields)) {
    if (prefixes.some((prefix) => field === prefix || field.startsWith(`${prefix}.`))) {
      return section as ExtensionAdvancedSection
    }
  }

  return null
}
