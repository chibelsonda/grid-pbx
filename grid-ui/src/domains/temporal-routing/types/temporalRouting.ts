export type TemporalCycle = 'date' | 'daily' | 'weekly' | 'monthly' | 'yearly'
export type Weekday =
  'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday'
export type TemporalControlAction = 'enable' | 'disable' | 'reset'
export type TemporalOverride = 'scheduled' | 'forced_active' | 'forced_inactive' | 'mixed' | 'empty'
export type TemporalEffectiveStatus = {
  state: 'active' | 'inactive'
  is_active: boolean
  override: TemporalOverride
  timezone: string
  evaluated_at: string
  rule_count?: number
  resolved_rule_count?: number
  active_rule_count?: number
}
export type TemporalRule = {
  id: string
  name: string
  cycle: TemporalCycle
  interval: number
  start_date: string | null
  time_window_start: number | null
  time_window_stop: number | null
  enabled: boolean | null
  effective_status: TemporalEffectiveStatus
  days: number[]
  weekdays: Weekday[]
  month: number | null
  ordinal: string | null
  sync_status: string
  last_synced_at: string | null
}
export type TemporalRuleInput = Omit<
  TemporalRule,
  'id' | 'effective_status' | 'sync_status' | 'last_synced_at'
>
export type TemporalRuleSetMembership = {
  id: string
  rule: {
    id: string
    name: string
    cycle: TemporalCycle
    enabled: boolean | null
    effective_status: TemporalEffectiveStatus
  } | null
  position: number
  resolved: boolean
}
export type TemporalRuleSet = {
  id: string
  name: string
  rule_count?: number
  effective_status: TemporalEffectiveStatus
  rules?: TemporalRuleSetMembership[]
  sync_status: string
  last_synced_at: string | null
}
export type TemporalRuleSetInput = { name: string; rule_ids: string[] }
export type TemporalOption = { id: string; label: string; detail: string | null }
export type TemporalOptions = { rules: TemporalOption[] }
export type TemporalSyncRun = {
  id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  error_message: string | null
}
