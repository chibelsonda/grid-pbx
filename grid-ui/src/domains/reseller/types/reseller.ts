export type ResellerAccountSummary = {
  id: string
  name: string
  realm: string | null
  enabled: boolean
  is_reseller: boolean
  is_superduper_admin: boolean
  billing_mode: string | null
  descendants_count: number
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
}
