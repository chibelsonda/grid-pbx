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
    catalog_available: boolean
    catalog_reason: string | null
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
  source: 'extensions'
  types: Array<'presence' | 'personal_parking' | 'speed_dial'>
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

export type LineKeySyncRun = {
  id: string
  resource_type: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  processed_count: number
  upserted_count: number
  deleted_count: number
  error_message: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
}

export type LineKeySyncState = {
  status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_successful_at: string | null
  error_message: string | null
}
