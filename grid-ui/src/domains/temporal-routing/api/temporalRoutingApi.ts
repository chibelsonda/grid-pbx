import { http, unwrapApiData, type ApiResponse, type PaginatedResponse } from '@/shared/api/http'
import type { TemporalOptions, TemporalRule, TemporalRuleInput, TemporalRuleSet, TemporalRuleSetInput, TemporalSyncRun } from '../types/temporalRouting'

export const temporalRoutingApi = {
  async rules(accountId: string, search = '', page = 1): Promise<PaginatedResponse<TemporalRule>> { return (await http.get<PaginatedResponse<TemporalRule>>(`/api/v1/accounts/${accountId}/temporal-rules`, { params: { search: search || undefined, page } })).data },
  async rule(accountId: string, id: string): Promise<TemporalRule> { return unwrapApiData(await http.get<ApiResponse<TemporalRule>>(`/api/v1/accounts/${accountId}/temporal-rules/${id}`)) },
  async createRule(accountId: string, input: TemporalRuleInput): Promise<TemporalRule> { return unwrapApiData(await http.post<ApiResponse<TemporalRule>>(`/api/v1/accounts/${accountId}/temporal-rules`, input)) },
  async updateRule(accountId: string, id: string, input: TemporalRuleInput): Promise<TemporalRule> { return unwrapApiData(await http.put<ApiResponse<TemporalRule>>(`/api/v1/accounts/${accountId}/temporal-rules/${id}`, input)) },
  async removeRule(accountId: string, id: string): Promise<void> { await http.delete(`/api/v1/accounts/${accountId}/temporal-rules/${id}`) },
  async sets(accountId: string, search = '', page = 1): Promise<PaginatedResponse<TemporalRuleSet>> { return (await http.get<PaginatedResponse<TemporalRuleSet>>(`/api/v1/accounts/${accountId}/temporal-rule-sets`, { params: { search: search || undefined, page } })).data },
  async set(accountId: string, id: string): Promise<TemporalRuleSet> { return unwrapApiData(await http.get<ApiResponse<TemporalRuleSet>>(`/api/v1/accounts/${accountId}/temporal-rule-sets/${id}`)) },
  async options(accountId: string): Promise<TemporalOptions> { return unwrapApiData(await http.get<ApiResponse<TemporalOptions>>(`/api/v1/accounts/${accountId}/temporal-rule-sets/options`)) },
  async createSet(accountId: string, input: TemporalRuleSetInput): Promise<TemporalRuleSet> { return unwrapApiData(await http.post<ApiResponse<TemporalRuleSet>>(`/api/v1/accounts/${accountId}/temporal-rule-sets`, input)) },
  async updateSet(accountId: string, id: string, input: TemporalRuleSetInput): Promise<TemporalRuleSet> { return unwrapApiData(await http.put<ApiResponse<TemporalRuleSet>>(`/api/v1/accounts/${accountId}/temporal-rule-sets/${id}`, input)) },
  async removeSet(accountId: string, id: string): Promise<void> { await http.delete(`/api/v1/accounts/${accountId}/temporal-rule-sets/${id}`) },
  async startSync(accountId: string): Promise<TemporalSyncRun> { return unwrapApiData(await http.post<ApiResponse<TemporalSyncRun>>(`/api/v1/accounts/${accountId}/sync/temporal-routing`)) },
  async syncStatus(accountId: string, id: string): Promise<TemporalSyncRun> { return unwrapApiData(await http.get<ApiResponse<TemporalSyncRun>>(`/api/v1/accounts/${accountId}/sync/temporal-routing/${id}`)) },
}
