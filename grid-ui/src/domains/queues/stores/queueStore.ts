import axios from 'axios'
import { defineStore } from 'pinia'
import { queueApi } from '../api/queueApi'
import { agentQueueMembershipInputSchema } from '../schemas/agentQueueMembershipSchema'
import type {
  Agent,
  AgentQueueMembership,
  AgentQueueMembershipInput,
  AgentStatistics,
  AgentStatus,
  AgentStatusInput,
  Queue,
  QueueInput,
  QueueOptions,
  QueueStatistics,
} from '../types/queue'

function emptyOptions(): QueueOptions {
  return {
    agents: [],
    media: [],
    capabilities: {
      configuration_available: false,
      live_agent_controls_available: false,
      agent_statistics_available: false,
      statistics_available: false,
    },
  }
}
function message(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

export const useQueueStore = defineStore('queues', {
  state: () => ({
    records: [] as Queue[],
    detail: null as Queue | null,
    options: emptyOptions(),
    agents: [] as Agent[],
    selectedAgent: null as Agent | null,
    agentStatus: null as AgentStatus | null,
    agentQueueMembership: null as AgentQueueMembership | null,
    search: '',
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    saving: false,
    synchronizing: false,
    statusLoading: false,
    statusRefreshing: false,
    statusLastObservedAt: null as string | null,
    statusRefreshError: null as string | null,
    statusCommandAccepted: false,
    membershipLoading: false,
    membershipSaving: false,
    membershipError: null as string | null,
    membershipCommandAccepted: false,
    agentStatistics: null as AgentStatistics | null,
    agentStatisticsLoading: false,
    agentStatisticsRefreshing: false,
    agentStatisticsError: null as string | null,
    statistics: null as QueueStatistics | null,
    statisticsLoading: false,
    statisticsRefreshing: false,
    statisticsError: null as string | null,
    error: null as string | null,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.options = emptyOptions()
      this.agents = []
      this.selectedAgent = null
      this.agentStatus = null
      this.agentQueueMembership = null
      this.statusRefreshing = false
      this.statusLastObservedAt = null
      this.statusRefreshError = null
      this.statusCommandAccepted = false
      this.membershipLoading = false
      this.membershipSaving = false
      this.membershipError = null
      this.membershipCommandAccepted = false
      this.agentStatistics = null
      this.agentStatisticsLoading = false
      this.agentStatisticsRefreshing = false
      this.agentStatisticsError = null
      this.statistics = null
      this.statisticsLoading = false
      this.statisticsRefreshing = false
      this.statisticsError = null
      this.total = 0
      this.error = null
      this.clearMutationError()
    },
    clearMutationError(): void {
      this.mutationError = null
      this.fieldErrors = {}
    },
    capture(error: unknown, fallback: string): void {
      this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
      this.mutationError =
        Object.keys(this.fieldErrors).length > 0 ? null : message(error, fallback)
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true
      this.error = null
      try {
        const [response, agents, options] = await Promise.all([
          queueApi.list(accountId, this.search, page),
          queueApi.agents(accountId),
          queueApi.options(accountId),
        ])
        this.records = response.data
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
        this.agents = agents
        this.options = options
        await Promise.all([
          options.capabilities.statistics_available
            ? this.refreshStatistics(accountId, true)
            : Promise.resolve(false),
          options.capabilities.agent_statistics_available
            ? this.refreshAgentStatistics(accountId, true)
            : Promise.resolve(false),
        ])
      } catch (error) {
        this.error = message(error, 'Unable to load queues and agents.')
      } finally {
        this.loading = false
      }
    },
    async refreshStatistics(accountId: string, initial = false): Promise<boolean> {
      if (this.statisticsLoading || this.statisticsRefreshing) return false

      if (initial) this.statisticsLoading = true
      else this.statisticsRefreshing = true
      try {
        this.statistics = await queueApi.statistics(accountId)
        this.statisticsError = null
        return true
      } catch (error) {
        this.statisticsError = message(error, 'Unable to refresh live queue activity.')
        return false
      } finally {
        this.statisticsLoading = false
        this.statisticsRefreshing = false
      }
    },
    async refreshAgentStatistics(accountId: string, initial = false): Promise<boolean> {
      if (this.agentStatisticsLoading || this.agentStatisticsRefreshing) return false

      if (initial) this.agentStatisticsLoading = true
      else this.agentStatisticsRefreshing = true
      try {
        this.agentStatistics = await queueApi.agentStatistics(accountId)
        this.agentStatisticsError = null
        return true
      } catch (error) {
        this.agentStatisticsError = message(error, 'Unable to refresh live agent performance.')
        return false
      } finally {
        this.agentStatisticsLoading = false
        this.agentStatisticsRefreshing = false
      }
    },
    async prepare(accountId: string, id?: string): Promise<void> {
      this.loading = true
      this.clearMutationError()
      try {
        const [options, detail] = await Promise.all([
          queueApi.options(accountId),
          id ? queueApi.detail(accountId, id) : Promise.resolve(null),
        ])
        this.options = options
        this.detail = detail
      } catch (error) {
        this.error = message(error, 'Unable to prepare the queue form.')
      } finally {
        this.loading = false
      }
    },
    async prepareAgent(accountId: string, agent: Agent): Promise<void> {
      this.selectedAgent = agent
      this.agentStatus = null
      this.statusLastObservedAt = null
      this.statusRefreshError = null
      this.statusCommandAccepted = false
      this.agentQueueMembership = null
      this.membershipError = null
      this.membershipCommandAccepted = false
      this.statusLoading = true
      this.clearMutationError()
      try {
        this.agentStatus = await queueApi.agentStatus(accountId, agent.id)
        this.statusLastObservedAt = new Date().toISOString()
      } catch (error) {
        this.capture(error, 'Unable to load live agent status.')
      } finally {
        this.statusLoading = false
      }
      await this.refreshAgentQueueMemberships(accountId, true)
    },
    async refreshAgentQueueMemberships(accountId: string, initial = false): Promise<boolean> {
      const agent = this.selectedAgent
      if (!agent || this.membershipLoading || this.membershipSaving) return false

      if (initial) this.membershipLoading = true
      try {
        const membership = await queueApi.agentQueueMemberships(accountId, agent.id)
        if (this.selectedAgent?.id !== agent.id) return false

        this.agentQueueMembership = membership
        this.membershipError = null
        return true
      } catch (error) {
        this.membershipError = message(error, 'Unable to refresh Agent Queue memberships.')
        return false
      } finally {
        this.membershipLoading = false
      }
    },
    async refreshAgentStatus(accountId: string): Promise<boolean> {
      const agent = this.selectedAgent
      if (!agent || this.statusLoading || this.statusRefreshing) return false

      this.statusRefreshing = true
      try {
        const status = await queueApi.agentStatus(accountId, agent.id)
        if (this.selectedAgent?.id !== agent.id) return false

        this.agentStatus = status
        this.statusLastObservedAt = new Date().toISOString()
        this.statusRefreshError = null
        return true
      } catch (error) {
        this.statusRefreshError = message(error, 'Unable to refresh live agent status.')
        return false
      } finally {
        this.statusRefreshing = false
      }
    },
    replace(record: Queue): void {
      const index = this.records.findIndex(({ id }) => id === record.id)
      if (index >= 0) this.records[index] = record
      else this.records.unshift(record)
      this.detail = record
    },
    async save(accountId: string, input: QueueInput): Promise<boolean> {
      this.saving = true
      this.clearMutationError()
      try {
        const isNew = !this.detail
        this.replace(
          isNew
            ? await queueApi.create(accountId, input)
            : await queueApi.update(accountId, this.detail!.id, input),
        )
        if (isNew) this.total += 1
        await this.load(accountId, this.page)
        return true
      } catch (error) {
        this.capture(error, 'Unable to save queue.')
        return false
      } finally {
        this.saving = false
      }
    },
    async remove(accountId: string): Promise<boolean> {
      if (!this.detail) return false
      this.saving = true
      this.clearMutationError()
      try {
        const id = this.detail.id
        await queueApi.remove(accountId, id)
        this.records = this.records.filter((record) => record.id !== id)
        this.detail = null
        this.total = Math.max(0, this.total - 1)
        await this.load(accountId, this.page)
        return true
      } catch (error) {
        this.capture(error, 'Unable to delete queue.')
        return false
      } finally {
        this.saving = false
      }
    },
    async updateAgentStatus(accountId: string, input: AgentStatusInput): Promise<boolean> {
      if (!this.selectedAgent) return false
      this.statusLoading = true
      this.statusCommandAccepted = false
      this.clearMutationError()
      try {
        await queueApi.updateAgentStatus(accountId, this.selectedAgent.id, input)
        this.statusCommandAccepted = true
        return true
      } catch (error) {
        this.capture(error, 'Unable to update agent status.')
        return false
      } finally {
        this.statusLoading = false
      }
    },
    async updateAgentQueueMembership(
      accountId: string,
      input: AgentQueueMembershipInput,
    ): Promise<boolean> {
      const agent = this.selectedAgent
      if (!agent || this.membershipSaving) return false
      const parsed = agentQueueMembershipInputSchema.safeParse(input)

      if (!parsed.success) {
        this.membershipError = parsed.error.issues[0]?.message ?? 'Select a valid Queue.'
        return false
      }

      this.membershipSaving = true
      this.membershipCommandAccepted = false
      this.membershipError = null
      try {
        const membership = await queueApi.updateAgentQueueMembership(
          accountId,
          agent.id,
          parsed.data,
        )
        if (this.selectedAgent?.id !== agent.id) return false

        this.agentQueueMembership = membership
        this.selectedAgent.queues = membership.assigned_queues
        const index = this.agents.findIndex(({ id }) => id === agent.id)
        if (index >= 0) this.agents[index] = { ...this.selectedAgent }
        this.membershipCommandAccepted = true
        return true
      } catch (error) {
        this.membershipError = message(error, 'Unable to update Agent Queue membership.')
        return false
      } finally {
        this.membershipSaving = false
      }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null
      try {
        let run = await queueApi.startSync(accountId)
        for (
          let attempt = 0;
          attempt < 40 && ['queued', 'running'].includes(run.status);
          attempt += 1
        ) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await queueApi.syncStatus(accountId, run.id)
        }
        if (run.status !== 'succeeded')
          throw new Error(run.error_message ?? 'Queue sync did not finish.')
        await this.load(accountId)
      } catch (error) {
        this.error =
          error instanceof Error ? error.message : message(error, 'Unable to synchronize queues.')
      } finally {
        this.synchronizing = false
      }
    },
  },
})
