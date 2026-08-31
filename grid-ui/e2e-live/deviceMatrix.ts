export interface DeviceParityCase {
  gridLabel: string
  switchType: string
  tabs: string[]
  gridTabs?: string[]
}

export const deviceParityMatrix: DeviceParityCase[] = [
  {
    gridLabel: 'VoIP phone',
    switchType: 'sip_device',
    tabs: ['Basic', 'Caller ID', 'SIP', 'Audio', 'Video', 'Options', 'Restrictions'],
  },
  { gridLabel: 'Cell phone', switchType: 'cellphone', tabs: ['Basic', 'Options'] },
  {
    gridLabel: 'Smartphone',
    switchType: 'smartphone',
    tabs: ['Basic', 'Wi-Fi calling', 'Options', 'Restrictions'],
    gridTabs: ['Basic', 'Caller ID', 'Wi-Fi calling', 'Audio', 'Video', 'Options', 'Restrictions'],
  },
  { gridLabel: 'Landline', switchType: 'landline', tabs: ['Basic', 'Options'] },
  {
    gridLabel: 'Softphone',
    switchType: 'softphone',
    tabs: ['Basic', 'Caller ID', 'SIP', 'Audio', 'Video', 'Options', 'Restrictions'],
  },
  {
    gridLabel: 'Fax',
    switchType: 'fax',
    tabs: ['Basic', 'Caller ID', 'SIP', 'Options', 'Restrictions'],
    gridTabs: ['Basic', 'Caller ID', 'SIP', 'Audio', 'Options', 'Restrictions'],
  },
  {
    gridLabel: 'ATA',
    switchType: 'ata',
    tabs: ['Basic', 'Caller ID', 'SIP', 'Options', 'Restrictions'],
    gridTabs: ['Basic', 'Caller ID', 'SIP', 'Audio', 'Options', 'Restrictions'],
  },
  { gridLabel: 'SIP URI', switchType: 'sip_uri', tabs: ['Basic', 'Options'] },
]

export function normalizeSwitchTab(label: string): string {
  const normalized = label
    .replace(/\s+settings$/i, '')
    .replace(/^wifi calling$/i, 'Wi-Fi calling')
    .trim()

  return normalized === 'CallerID' ? 'Caller ID' : normalized
}
