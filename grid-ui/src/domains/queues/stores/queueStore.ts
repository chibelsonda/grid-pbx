import axios from 'axios'
import { defineStore } from 'pinia'
import { queueApi } from '../api/queueApi'
import type { Agent, AgentStatus, AgentStatusInput, Queue, QueueInput, QueueOptions } from '../types/queue'

const emptyOptions: QueueOptions = { agents: [], media: [] }
function message(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

export const useQueueStore = defineStore('queues', {
  state: () => ({
    records: [] as Queue[], detail: null as Queue | null, options: { ...emptyOptions },
    agents: [] as Agent[], selectedAgent: null as Agent | null, agentStatus: null as AgentStatus | null,
    search: '', page: 1, lastPage: 1, total: 0, loading: false, saving: false,
    synchronizing: false, statusLoading: false, error: null as string | null,
    mutationError: null as string | null, fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void {
      this.records = []; this.detail = null; this.options = { ...emptyOptions }; this.agents = []
      this.selectedAgent = null; this.agentStatus = null; this.total = 0; this.error = null; this.clearMutationError()
    },
    clearMutationError(): void { this.mutationError = null; this.fieldErrors = {} },
    capture(error: unknown, fallback: string): void {
      this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
      this.mutationError = Object.keys(this.fieldErrors).length > 0 ? null : message(error, fallback)
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true; this.error = null
      try {
        const [response, agents] = await Promise.all([queueApi.list(accountId, this.search, page), queueApi.agents(accountId)])
        this.records = response.data; this.page = response.meta.current_page; this.lastPage = response.meta.last_page
        this.total = response.meta.total; this.agents = agents
      } catch (error) { this.error = message(error, 'Unable to load queues and agents.') } finally { this.loading = false }
    },
    async prepare(accountId: string, id?: string): Promise<void> {
      this.loading = true; this.clearMutationError()
      try {
        const [options, detail] = await Promise.all([queueApi.options(accountId), id ? queueApi.detail(accountId, id) : Promise.resolve(null)])
        this.options = options; this.detail = detail
      } catch (error) { this.error = message(error, 'Unable to prepare the queue form.') } finally { this.loading = false }
    },
    async prepareAgent(accountId: string, agent: Agent): Promise<void> {
      this.selectedAgent = agent; this.agentStatus = null; this.statusLoading = true; this.clearMutationError()
      try { this.agentStatus = await queueApi.agentStatus(accountId, agent.id) }
      catch (error) { this.capture(error, 'Unable to load live agent status.') }
      finally { this.statusLoading = false }
    },
    replace(record: Queue): void {
      const index = this.records.findIndex(({ id }) => id === record.id)
      if (index >= 0) this.records[index] = record; else this.records.unshift(record)
      this.detail = record
    },
    async save(accountId: string, input: QueueInput): Promise<boolean> {
      this.saving = true; this.clearMutationError()
      try {
        const isNew = !this.detail
        this.replace(isNew ? await queueApi.create(accountId, input) : await queueApi.update(accountId, this.detail!.id, input))
        if (isNew) this.total += 1
        await this.load(accountId, this.page)
        return true
      } catch (error) { this.capture(error, 'Unable to save queue.'); return false } finally { this.saving = false }
    },
    async remove(accountId: string): Promise<boolean> {
      if (!this.detail) return false
      this.saving = true; this.clearMutationError()
      try {
        const id = this.detail.id; await queueApi.remove(accountId, id)
        this.records = this.records.filter((record) => record.id !== id); this.detail = null
        this.total = Math.max(0, this.total - 1); await this.load(accountId, this.page); return true
      } catch (error) { this.capture(error, 'Unable to delete queue.'); return false } finally { this.saving = false }
    },
    async updateAgentStatus(accountId: string, input: AgentStatusInput): Promise<boolean> {
      if (!this.selectedAgent) return false
      this.statusLoading = true; this.clearMutationError()
      try {
        await queueApi.updateAgentStatus(accountId, this.selectedAgent.id, input)
        this.agentStatus = { id: this.selectedAgent.id, status: input.status, timestamp: null }
        return true
      } catch (error) { this.capture(error, 'Unable to update agent status.'); return false } finally { this.statusLoading = false }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true; this.error = null
      try {
        let run = await queueApi.startSync(accountId)
        for (let attempt = 0; attempt < 40 && ['queued', 'running'].includes(run.status); attempt += 1) {
          await new Promise((resolve) => window.setTimeout(resolve, 500)); run = await queueApi.syncStatus(accountId, run.id)
        }
        if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Queue sync did not finish.')
        await this.load(accountId)
      } catch (error) { this.error = error instanceof Error ? error.message : message(error, 'Unable to synchronize queues.') }
      finally { this.synchronizing = false }
    },
  },
})
