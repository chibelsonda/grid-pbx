import { defineStore } from 'pinia'
import { normalizeApiError } from '@/shared/api/apiError'
import { callflowApi } from '../api/callflowApi'
import type {
  Callflow,
  CallflowCreateInput,
  CallflowEditor,
  CallflowEntryPointsUpdate,
  CallflowFilters,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeDeleteInput,
  CallflowTreeNodeUpdateInput,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
  CallflowUpdate,
  SyncState,
} from '../types/callRouting'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

export const useCallflowStore = defineStore('call-routing', {
  state: () => ({
    records: [] as Callflow[],
    detail: null as Callflow | null,
    editor: null as CallflowEditor | null,
    filters: { search: '', type: '', module: '' } as CallflowFilters,
    sync: { ...defaultSync },
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    detailLoading: false,
    editorLoading: false,
    editorOpen: false,
    saving: false,
    preparingCreation: false,
    deleting: false,
    treeMoving: false,
    treeDeleting: false,
    treeEditor: null as CallflowEditor | null,
    treeEditorLoading: false,
    treeNodeSaving: false,
    treeNodeError: null as string | null,
    treeNodeFieldErrors: {} as Record<string, string[]>,
    entryPointSaving: false,
    entryPointError: null as string | null,
    entryPointFieldErrors: {} as Record<string, string[]>,
    synchronizing: false,
    error: null as string | null,
    detailError: null as string | null,
    editorError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    mutationError: null as string | null,
    treeMutationError: null as string | null,
    capabilityRefreshSequence: 0,
  }),
  actions: {
    reset(): void {
      this.capabilityRefreshSequence += 1
      this.records = []
      this.detail = null
      this.editor = null
      this.editorOpen = false
      this.preparingCreation = false
      this.sync = { ...defaultSync }
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
      this.detailError = null
      this.editorError = null
      this.fieldErrors = {}
      this.mutationError = null
      this.treeMutationError = null
      this.treeDeleting = false
      this.treeEditor = null
      this.treeEditorLoading = false
      this.treeNodeError = null
      this.treeNodeFieldErrors = {}
      this.entryPointSaving = false
      this.entryPointError = null
      this.entryPointFieldErrors = {}
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await callflowApi.list(accountId, this.filters, page)
        this.records = response.data
        this.sync = response.meta.sync
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = normalizeApiError(error, 'Unable to load callflows.').message
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, callflowId: string): Promise<void> {
      this.detail = null
      await this.refreshDetail(accountId, callflowId)
    },
    async refreshDetail(accountId: string, callflowId: string): Promise<boolean> {
      this.detailError = null
      this.detailLoading = true

      try {
        const refreshed = await callflowApi.detail(accountId, callflowId)
        this.detail = refreshed
        const index = this.records.findIndex((record) => record.id === refreshed.id)
        if (index >= 0) this.records[index] = refreshed

        return true
      } catch (error) {
        this.detailError = normalizeApiError(error, 'Unable to load the callflow.').message

        return false
      } finally {
        this.detailLoading = false
      }
    },
    closeDetail(): void {
      this.capabilityRefreshSequence += 1
      this.detail = null
      this.detailError = null
    },
    async openEditor(accountId: string, callflowId: string): Promise<void> {
      this.capabilityRefreshSequence += 1
      this.editorOpen = true
      this.editor = null
      this.editorError = null
      this.fieldErrors = {}
      this.editorLoading = true

      try {
        this.editor = await callflowApi.editor(accountId, callflowId)
      } catch (error) {
        this.editorError = normalizeApiError(
          error,
          'Unable to load routing editor options.',
        ).message
      } finally {
        this.editorLoading = false
      }
    },
    async openCreateEditor(accountId: string): Promise<void> {
      this.capabilityRefreshSequence += 1
      this.detail = null
      this.editorOpen = true
      this.editor = null
      this.editorError = null
      this.fieldErrors = {}
      this.editorLoading = true

      try {
        this.editor = await callflowApi.createEditor(accountId)
      } catch (error) {
        this.editorError = normalizeApiError(
          error,
          'Unable to load route creation options.',
        ).message
      } finally {
        this.editorLoading = false
      }
    },
    async refreshCapabilityOptions(
      accountId: string,
      callflowId: string | null,
    ): Promise<boolean> {
      const refreshSequence = ++this.capabilityRefreshSequence

      try {
        const refreshed = callflowId
          ? await callflowApi.editor(accountId, callflowId)
          : await callflowApi.createEditor(accountId)
        if (refreshSequence !== this.capabilityRefreshSequence) return false

        const current = callflowId ? this.treeEditor : this.editor

        if (current) Object.assign(current, refreshed)
        else if (callflowId) this.treeEditor = refreshed
        else this.editor = refreshed

        return true
      } catch (error) {
        if (refreshSequence !== this.capabilityRefreshSequence) return false

        const message = normalizeApiError(
          error,
          'Unable to refresh Callflow capabilities.',
        ).message

        if (callflowId) this.treeNodeError = message
        else this.editorError = message

        return false
      }
    },
    closeEditor(): void {
      this.capabilityRefreshSequence += 1
      this.editorOpen = false
      this.editor = null
      this.editorError = null
      this.fieldErrors = {}
      this.preparingCreation = false
    },
    async update(
      accountId: string,
      callflowId: string,
      input: CallflowUpdate,
    ): Promise<Callflow | null> {
      this.saving = true
      this.editorError = null
      this.fieldErrors = {}

      try {
        const updated = await callflowApi.update(accountId, callflowId, input)
        this.detail = updated
        const index = this.records.findIndex((record) => record.id === updated.id)
        if (index >= 0) this.records[index] = updated
        this.closeEditor()

        return updated
      } catch (error) {
        const normalized = normalizeApiError(error, 'Unable to update the callflow.')
        this.fieldErrors = normalized.fieldErrors
        this.editorError = normalized.fieldErrorCount > 0 ? null : normalized.message

        return null
      } finally {
        this.saving = false
      }
    },
    async updateEntryPoints(
      accountId: string,
      callflowId: string,
      input: CallflowEntryPointsUpdate,
    ): Promise<Callflow | null> {
      this.entryPointSaving = true
      this.entryPointError = null
      this.entryPointFieldErrors = {}

      try {
        const updated = await callflowApi.updateEntryPoints(accountId, callflowId, input)
        this.detail = updated
        const index = this.records.findIndex((record) => record.id === updated.id)
        if (index >= 0) this.records[index] = updated

        return updated
      } catch (error) {
        const normalized = normalizeApiError(error, 'Unable to add the callflow entry number.')
        this.entryPointFieldErrors = normalized.fieldErrors
        this.entryPointError = normalized.fieldErrorCount > 0 ? null : normalized.message

        return null
      } finally {
        this.entryPointSaving = false
      }
    },
    async create(accountId: string, input: CallflowCreateInput): Promise<Callflow | null> {
      this.saving = true
      this.preparingCreation = true
      this.editorError = null
      this.fieldErrors = {}
      let projectionVerified = false

      try {
        await this.waitForProjectionSync(accountId, false)
        projectionVerified = true
        this.preparingCreation = false
        const created = await callflowApi.create(accountId, input)
        this.records.unshift(created)
        this.total += 1
        this.detail = created
        this.closeEditor()

        return created
      } catch (error) {
        const normalized = normalizeApiError(
          error,
          projectionVerified
            ? 'Unable to create the callflow.'
            : 'Unable to verify the latest callflow assignments before creation.',
        )
        this.fieldErrors = normalized.fieldErrors
        this.editorError = normalized.fieldErrorCount > 0 ? null : normalized.message

        return null
      } finally {
        this.preparingCreation = false
        this.saving = false
      }
    },
    async destroy(accountId: string, callflowId: string): Promise<boolean> {
      this.deleting = true
      this.mutationError = null

      try {
        await callflowApi.delete(accountId, callflowId)
        this.records = this.records.filter(({ id }) => id !== callflowId)
        this.total = Math.max(0, this.total - 1)
        this.detail = null

        return true
      } catch (error) {
        this.mutationError = normalizeApiError(error, 'Unable to delete the callflow.').message

        return false
      } finally {
        this.deleting = false
      }
    },
    async moveTreeNode(
      accountId: string,
      callflowId: string,
      input: CallflowTreeMoveInput,
    ): Promise<Callflow | null> {
      this.treeMoving = true
      this.treeMutationError = null

      try {
        const updated = await callflowApi.moveTreeNode(accountId, callflowId, input)
        this.detail = updated
        const index = this.records.findIndex((record) => record.id === updated.id)
        if (index >= 0) this.records[index] = updated

        return updated
      } catch (error) {
        this.treeMutationError = normalizeApiError(
          error,
          'Unable to move the callflow node.',
        ).message

        return null
      } finally {
        this.treeMoving = false
      }
    },
    async loadTreeEditor(accountId: string, callflowId: string): Promise<boolean> {
      this.capabilityRefreshSequence += 1
      this.treeEditor = null
      this.treeEditorLoading = true
      this.treeNodeError = null
      this.treeNodeFieldErrors = {}

      try {
        this.treeEditor = await callflowApi.editor(accountId, callflowId)

        return true
      } catch (error) {
        this.treeNodeError = normalizeApiError(
          error,
          'Unable to load action destinations.',
        ).message

        return false
      } finally {
        this.treeEditorLoading = false
      }
    },
    async reorderTreeNodes(
      accountId: string,
      callflowId: string,
      input: CallflowTreeReorderInput,
    ): Promise<Callflow | null> {
      this.treeMoving = true
      this.treeMutationError = null

      try {
        const updated = await callflowApi.reorderTreeNodes(accountId, callflowId, input)
        this.detail = updated
        const index = this.records.findIndex((record) => record.id === updated.id)
        if (index >= 0) this.records[index] = updated

        return updated
      } catch (error) {
        this.treeMutationError = normalizeApiError(
          error,
          'Unable to reorder the callflow nodes.',
        ).message

        return null
      } finally {
        this.treeMoving = false
      }
    },
    closeTreeEditor(): void {
      this.capabilityRefreshSequence += 1
      this.treeEditor = null
      this.clearTreeNodeErrors()
    },
    clearTreeNodeErrors(): void {
      this.treeNodeError = null
      this.treeNodeFieldErrors = {}
    },
    async createTreeNode(
      accountId: string,
      callflowId: string,
      input: CallflowTreeNodeCreateInput,
    ): Promise<Callflow | null> {
      return this.saveTreeNode(() => callflowApi.createTreeNode(accountId, callflowId, input))
    },
    async updateTreeNode(
      accountId: string,
      callflowId: string,
      input: CallflowTreeNodeUpdateInput,
    ): Promise<Callflow | null> {
      return this.saveTreeNode(() => callflowApi.updateTreeNode(accountId, callflowId, input))
    },
    async deleteTreeNode(
      accountId: string,
      callflowId: string,
      input: CallflowTreeNodeDeleteInput,
    ): Promise<Callflow | null> {
      this.treeDeleting = true
      this.treeMutationError = null

      try {
        const updated = await callflowApi.deleteTreeNode(accountId, callflowId, input)
        this.detail = updated
        const index = this.records.findIndex((record) => record.id === updated.id)
        if (index >= 0) this.records[index] = updated

        return updated
      } catch (error) {
        this.treeMutationError = normalizeApiError(
          error,
          'Unable to remove the callflow action.',
        ).message

        return null
      } finally {
        this.treeDeleting = false
      }
    },
    async createInlineTreeNode(
      accountId: string,
      callflowId: string,
      input: CallflowInlineNodeCreateInput,
    ): Promise<Callflow | null> {
      return this.saveTreeNode(() => callflowApi.createInlineTreeNode(accountId, callflowId, input))
    },
    async updateInlineTreeNode(
      accountId: string,
      callflowId: string,
      input: CallflowInlineNodeUpdateInput,
    ): Promise<Callflow | null> {
      return this.saveTreeNode(() => callflowApi.updateInlineTreeNode(accountId, callflowId, input))
    },
    async saveTreeNode(request: () => Promise<Callflow>): Promise<Callflow | null> {
      this.treeNodeSaving = true
      this.treeNodeError = null
      this.treeNodeFieldErrors = {}

      try {
        const updated = await request()
        this.detail = updated
        const index = this.records.findIndex((record) => record.id === updated.id)
        if (index >= 0) this.records[index] = updated
        // The editor also carries account-scoped action capabilities and safe
        // resource choices used by the persistent palette. Keep it available
        // after a node mutation; only the transient form errors are cleared.
        this.clearTreeNodeErrors()

        return updated
      } catch (error) {
        const normalized = normalizeApiError(error, 'Unable to save the callflow action.')
        this.treeNodeFieldErrors = normalized.fieldErrors
        this.treeNodeError = normalized.fieldErrorCount > 0 ? null : normalized.message

        return null
      } finally {
        this.treeNodeSaving = false
      }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null

      try {
        await this.waitForProjectionSync(accountId)

        await this.load(accountId, 1)
        const activeCallflowId = this.detail?.id
        if (activeCallflowId) await this.refreshDetail(accountId, activeCallflowId)
      } catch (error) {
        this.error = normalizeApiError(error, 'Unable to synchronize callflows.').message
      } finally {
        this.synchronizing = false
      }
    },
    async waitForProjectionSync(accountId: string, globalNotification = true): Promise<void> {
      let run = globalNotification
        ? await callflowApi.startProjectionSync(accountId)
        : await callflowApi.startProjectionSync(accountId, false)

      for (
        let attempt = 0;
        attempt < 40 && ['queued', 'running'].includes(run.status);
        attempt += 1
      ) {
        await new Promise((resolve) => window.setTimeout(resolve, 500))
        run = await callflowApi.syncStatus(accountId, run.id)
      }

      if (run.status === 'failed') throw new Error(run.error_message ?? 'Routing sync failed.')
      if (run.status !== 'succeeded') {
        throw new Error('Routing sync is still running. Reload shortly.')
      }
    },
  },
})
