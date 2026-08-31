export const globalSearchTypes = [
  'extension',
  'device',
  'phone_number',
  'callflow',
  'voicemail_box',
  'queue',
  'menu',
  'conference',
  'directory',
  'group',
  'media',
  'recording',
  'fax_box',
  'blacklist',
  'caller_id_list',
] as const

export type GlobalSearchType = (typeof globalSearchTypes)[number]

export const globalSearchTypeOptions: ReadonlyArray<{
  value: GlobalSearchType
  label: string
}> = [
  { value: 'extension', label: 'People' },
  { value: 'device', label: 'Devices' },
  { value: 'phone_number', label: 'Phone numbers' },
  { value: 'callflow', label: 'Callflows' },
  { value: 'voicemail_box', label: 'Voicemail' },
  { value: 'queue', label: 'Queues' },
  { value: 'menu', label: 'Menus' },
  { value: 'conference', label: 'Conferences' },
  { value: 'directory', label: 'Directories' },
  { value: 'group', label: 'Groups' },
  { value: 'media', label: 'Media' },
  { value: 'recording', label: 'Recordings' },
  { value: 'fax_box', label: 'Fax boxes' },
  { value: 'blacklist', label: 'Blacklists' },
  { value: 'caller_id_list', label: 'Caller-ID lists' },
]

export type GlobalSearchResult = {
  id: string
  type: GlobalSearchType
  title: string
  subtitle: string
  matched_field: string
}

export type GlobalSearchGroup = {
  type: GlobalSearchType
  label: string
  results: GlobalSearchResult[]
}

export type GlobalSearchResponse = {
  query: string
  groups: GlobalSearchGroup[]
  total: number
}
