export type FeatureCodeRoute = {
  id: string
  numbers: string[]
  patterns: string[]
  root_module: string | null
  feature_code: {
    name: string | null
    number: string | null
  }
  sync_status: 'healthy' | 'syncing' | 'stale' | 'error'
  last_synced_at: string | null
}

export type FeatureCodePage = {
  data: FeatureCodeRoute[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    sync: {
      status: 'healthy' | 'syncing' | 'stale' | 'error'
      last_successful_at: string | null
      error_message: string | null
      scope: 'pbx_projection'
    }
  }
}

export type FeatureCodePresentation = {
  label: string
  category: string
  action: string
  dialCode: string
  dependency: string
}
