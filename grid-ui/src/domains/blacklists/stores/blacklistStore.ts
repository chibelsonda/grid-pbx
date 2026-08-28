import axios from 'axios'
import { defineStore } from 'pinia'
import { blacklistApi } from '../api/blacklistApi'
import type { Blacklist, BlacklistInput } from '../types/blacklist'

const errorMessage = (error: unknown, fallback: string) => axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
export const useBlacklistStore = defineStore('blacklists', {
  state: () => ({ records: [] as Blacklist[], detail: null as Blacklist | null, search: '', total: 0, loading: false, saving: false, synchronizing: false, error: null as string | null, mutationError: null as string | null, fieldErrors: {} as Record<string, string[]> }),
  actions: {
    reset(): void { this.records = []; this.detail = null; this.total = 0; this.error = null; this.clearMutationError() },
    clearMutationError(): void { this.mutationError = null; this.fieldErrors = {} },
    capture(error: unknown, fallback: string): void { this.mutationError = errorMessage(error, fallback); this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {} },
    async load(accountId: string, page = 1): Promise<void> { this.loading = true; this.error = null; try { const response = await blacklistApi.list(accountId, this.search, page); this.records = response.data; this.total = response.meta.total } catch (error) { this.error = errorMessage(error, 'Unable to load blacklists.') } finally { this.loading = false } },
    async prepare(accountId: string, id?: string): Promise<void> { this.loading = true; this.clearMutationError(); try { this.detail = id ? await blacklistApi.detail(accountId, id) : null } catch (error) { this.error = errorMessage(error, 'Unable to prepare the blacklist.') } finally { this.loading = false } },
    async save(accountId: string, input: BlacklistInput): Promise<boolean> { this.saving = true; this.clearMutationError(); try { this.detail ? await blacklistApi.update(accountId, this.detail.id, input) : await blacklistApi.create(accountId, input); await this.load(accountId); return true } catch (error) { this.capture(error, 'Unable to save blacklist.'); return false } finally { this.saving = false } },
    async remove(accountId: string): Promise<boolean> { if (!this.detail) return false; this.saving = true; this.clearMutationError(); try { await blacklistApi.remove(accountId, this.detail.id); this.detail = null; await this.load(accountId); return true } catch (error) { this.capture(error, 'Unable to delete blacklist.'); return false } finally { this.saving = false } },
    async synchronize(accountId: string): Promise<void> { this.synchronizing = true; this.error = null; try { let run = await blacklistApi.startSync(accountId); for (let i = 0; i < 40 && ['queued', 'running'].includes(run.status); i += 1) { await new Promise((resolve) => window.setTimeout(resolve, 500)); run = await blacklistApi.syncStatus(accountId, run.id) } if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Blacklist sync did not finish.'); await this.load(accountId) } catch (error) { this.error = error instanceof Error ? error.message : errorMessage(error, 'Unable to synchronize blacklists.') } finally { this.synchronizing = false } },
  },
})
