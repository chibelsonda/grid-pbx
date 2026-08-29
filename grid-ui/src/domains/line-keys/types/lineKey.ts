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
  model: {
    matched: boolean
    max_keys: number | null
    max_expansion_modules: number | null
    keys_per_expansion_module: number | null
    total_keys: number | null
    supported_key_types: LineKeyType[]
    value_sources: string[]
    manufacturer_provider: string | null
  }
}

export type LineKeyPreview = {
  device: LineKeyDevice
  capability: LineKeyCapability
  value_choices: LineKeyValueChoice[]
  payload_preview: {
    provision: {
      combo_keys: Record<string, unknown>
      feature_keys: Record<string, unknown>
    }
  }
}

export type LineKeyValueChoice = {
  id: string
  source: 'extensions' | 'devices'
  value: string
  label: string
  description: string | null
}

export type LineKeyInput = {
  category: LineKeyCategory
  position: number
  type: LineKeyType
  label: string | null
  value: string | number | null
}
