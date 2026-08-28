export type LineKeyCategory = 'combo' | 'feature'
export type LineKeyType = 'line' | 'presence' | 'personal_parking' | 'speed_dial' | 'parking'

export type LineKey = {
  id: string
  category: LineKeyCategory
  position: number
  type: LineKeyType
  label: string | null
  value: string | null
}

export type LineKeyDevice = {
  id: string
  name: string | null
  make: string | null
  endpoint_family: string | null
  model: string | null
  mac_address: string | null
  line_keys: LineKey[]
}

export type LineKeyCapability = {
  preview_available: boolean
  apply_available: boolean
  reason: string | null
}

export type LineKeyPreview = {
  device: LineKeyDevice
  capability: LineKeyCapability
  payload_preview: {
    provision: {
      combo_keys: Record<string, unknown>
      feature_keys: Record<string, unknown>
    }
  }
}

export type LineKeyInput = {
  category: LineKeyCategory
  position: number
  type: LineKeyType
  label: string | null
  value: string | number | null
}
