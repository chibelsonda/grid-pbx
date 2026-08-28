import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { PaginatedResponse } from '@/shared/api/http'
import { temporalRoutingApi } from '../api/temporalRoutingApi'
import type { TemporalOptions, TemporalRule, TemporalRuleSet, TemporalSyncRun } from '../types/temporalRouting'
import { useTemporalRoutingStore } from './temporalRoutingStore'

vi.mock('../api/temporalRoutingApi', () => ({ temporalRoutingApi: { rules: vi.fn<() => Promise<PaginatedResponse<TemporalRule>>>(), rule: vi.fn<() => Promise<TemporalRule>>(), createRule: vi.fn<() => Promise<TemporalRule>>(), updateRule: vi.fn<() => Promise<TemporalRule>>(), removeRule: vi.fn<() => Promise<void>>(), sets: vi.fn<() => Promise<PaginatedResponse<TemporalRuleSet>>>(), set: vi.fn<() => Promise<TemporalRuleSet>>(), options: vi.fn<() => Promise<TemporalOptions>>(), createSet: vi.fn<() => Promise<TemporalRuleSet>>(), updateSet: vi.fn<() => Promise<TemporalRuleSet>>(), removeSet: vi.fn<() => Promise<void>>(), startSync: vi.fn<() => Promise<TemporalSyncRun>>(), syncStatus: vi.fn<() => Promise<TemporalSyncRun>>() } }))
const meta = { current_page: 1, from: 1, last_page: 1, per_page: 25, to: 1, total: 1 }
const links = { first: null, last: null, prev: null, next: null }
const rule: TemporalRule = { id: 'rule-public', name: 'Business hours', cycle: 'weekly', interval: 1, start_date: '2026-09-01', time_window_start: 32400, time_window_stop: 61200, enabled: true, days: [], weekdays: ['monday'], month: null, ordinal: null, sync_status: 'healthy', last_synced_at: null }
const set: TemporalRuleSet = { id: 'set-public', name: 'Office schedule', rule_count: 1, sync_status: 'healthy', last_synced_at: null }
describe('temporal routing store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })
  it('loads account-scoped rules and sets together', async () => { vi.mocked(temporalRoutingApi.rules).mockResolvedValue({ data: [rule], meta, links }); vi.mocked(temporalRoutingApi.sets).mockResolvedValue({ data: [set], meta, links }); const store = useTemporalRoutingStore(); await store.load('account-1'); expect(store.rules).toEqual([rule]); expect(store.sets).toEqual([set]) })
  it('creates a rule set using public rule ids', async () => { vi.mocked(temporalRoutingApi.options).mockResolvedValue({ rules: [{ id: rule.id, label: rule.name, detail: rule.cycle }] }); vi.mocked(temporalRoutingApi.createSet).mockResolvedValue(set); vi.mocked(temporalRoutingApi.rules).mockResolvedValue({ data: [rule], meta, links }); vi.mocked(temporalRoutingApi.sets).mockResolvedValue({ data: [set], meta, links }); const store = useTemporalRoutingStore(); await store.prepareSet('account-1'); expect(await store.saveSet('account-1', { name: set.name, rule_ids: [rule.id] })).toBe(true); expect(temporalRoutingApi.createSet).toHaveBeenCalledWith('account-1', { name: set.name, rule_ids: [rule.id] }) })
})
