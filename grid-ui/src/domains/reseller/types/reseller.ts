import type { AccountAdministrationCapabilities } from '../schemas/accountAdministrationCapabilitiesSchema'

export type ResellerAccountSummary = {
  id: string
  name: string
  realm: string | null
  enabled: boolean
  is_reseller: boolean
  is_superduper_admin: boolean
  billing_mode: string | null
  descendants_count: number
  service_projection: {
    status: 'healthy' | 'syncing' | 'stale' | 'error'
    last_successful_at: string | null
    billing_reseller: {
      id: string
      name: string
      realm: string | null
    } | null
    billing_reseller_projected: boolean | null
  }
}

export type ResellerAffectedAccount = {
  id: string
  name: string
  realm: string | null
  service_projection_status: 'healthy' | 'syncing' | 'stale' | 'error'
}

export type AccountHierarchy = {
  account: ResellerAccountSummary
  parent: ResellerAccountSummary | null
  ancestors: ResellerAccountSummary[]
  children: ResellerAccountSummary[]
  descendants: ResellerAccountSummary[]
  coverage: {
    switch_descendants_count: number
    projected_descendants_count: number
    unresolved_descendants_count: number
    parent_projected: boolean
  }
  projection: {
    last_synced_at: string | null
  }
  portfolio: {
    accounts: {
      total: number
      projected: number
      healthy: number
      attention: number
    }
    billing_ownership: {
      projected: number
      unresolved: number
    }
    billing: {
      due_today: number
      recurring_amount: number
    }
    quantities: Array<{
      scope: 'account' | 'cascade' | 'manual'
      category: string
      item: string
      quantity: number
    }>
    warnings: Array<{
      code: string
      count: number
      message: string
      guidance: string
      affected_accounts: ResellerAffectedAccount[]
    }>
  }
  mutation_preflight: {
    operation: 'promote' | 'demote'
    operationally_ready: boolean
    mutation_available: false
    checks: Array<{
      code: string
      passed: boolean
      count: number
      message: string
      guidance: string
      affected_accounts: ResellerAffectedAccount[]
    }>
  }
}

export type ResellerMutationAvailability = {
  available: false
  reason: string
}

export type ResellerStatus = {
  account: ResellerAccountSummary
  billing_reseller: ResellerAccountSummary | null
  billing_reseller_projected: boolean | null
  service_projection_last_synced_at: string | null
  mutations: {
    promote: ResellerMutationAvailability
    demote: ResellerMutationAvailability
  }
  administration: AccountAdministrationCapabilities
}

export type DescendantOnboardingCandidate = {
  reference: string
  name: string
  realm: string | null
  descendants_count: number
  eligible: boolean
  blocked_reason: 'parent_not_projected' | null
}

export type DescendantOnboardingCandidates = {
  candidates: DescendantOnboardingCandidate[]
  target_organization: { id: string; name: string }
  access_inheritance: {
    member_count: number
    acknowledgement_required: true
  }
  reference_expires_at: string | null
}

export type DescendantOnboardingInput = {
  reference: string
  confirmation: string
  acknowledge_existing_access: true
}

export type DescendantOnboardingResult = {
  onboarded_account: {
    id: string
    name: string
    realm: string | null
    enabled: boolean
  }
  target_organization: { id: string; name: string }
  access_inheritance: {
    member_count: number
    acknowledged: true
  }
  hierarchy: AccountHierarchy
  service_projection: {
    status: 'queued' | 'running' | 'succeeded' | 'failed' | 'not_started'
    sync_run_id: string | null
  }
}
