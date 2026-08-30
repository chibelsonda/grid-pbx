import axios from 'axios'
import { defineStore } from 'pinia'
import { callflowApi } from '../api/callflowApi'
import type {
  Callflow,
  CallflowEditor,
  CallflowFilters,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowTreeNodeCreateInput,
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
    deleting: false,
    treeMoving: false,
    treeEditor: null as CallflowEditor | null,
    treeEditorLoading: false,
    treeNodeSaving: false,
    treeNodeError: null as string | null,
    treeNodeFieldErrors: {} as Record<string, string[]>,
    synchronizing: false,
    error: null as string | null,
    detailError: null as string | null,
    editorError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    mutationError: null as string | null,
    treeMutationError: null as string | null,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.editor = null
      this.editorOpen = false
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
      this.treeEditor = null
      this.treeEditorLoading = false
      this.treeNodeError = null
      this.treeNodeFieldErrors = {}
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
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load call routes.')
          : 'Unable to load call routes.'
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, callflowId: string): Promise<void> {
      this.detail = null
      this.detailError = null
      this.detailLoading = true

      try {
        this.detail = await callflowApi.detail(accountId, callflowId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the call route.')
          : 'Unable to load the call route.'
      } finally {
        this.detailLoading = false
      }
    },
    closeDetail(): void {
      this.detail = null
      this.detailError = null
    },
    async openEditor(accountId: string, callflowId: string): Promise<void> {
      this.editorOpen = true
      this.editor = null
      this.editorError = null
      this.fieldErrors = {}
      this.editorLoading = true

      try {
        this.editor = await callflowApi.editor(accountId, callflowId)
      } catch (error) {
        this.editorError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load routing editor options.')
          : 'Unable to load routing editor options.'
      } finally {
        this.editorLoading = false
      }
    },
    async openCreateEditor(accountId: string): Promise<void> {
      this.detail = null
      this.editorOpen = true
      this.editor = null
      this.editorError = null
      this.fieldErrors = {}
      this.editorLoading = true

      try {
        this.editor = await callflowApi.createEditor(accountId)
      } catch (error) {
        this.editorError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load route creation options.')
          : 'Unable to load route creation options.'
      } finally {
        this.editorLoading = false
      }
    },
    closeEditor(): void {
      this.editorOpen = false
      this.editor = null
      this.editorError = null
      this.fieldErrors = {}
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
        this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
        this.editorError =
          Object.keys(this.fieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to update the call route.')
              : 'Unable to update the call route.'

        return null
      } finally {
        this.saving = false
      }
    },
    async create(accountId: string, input: CallflowUpdate): Promise<Callflow | null> {
      this.saving = true
      this.editorError = null
      this.fieldErrors = {}

      try {
        const created = await callflowApi.create(accountId, input)
        this.records.unshift(created)
        this.total += 1
        this.detail = created
        this.closeEditor()

        return created
      } catch (error) {
        this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
        this.editorError =
          Object.keys(this.fieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to create the call route.')
              : 'Unable to create the call route.'

        return null
      } finally {
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
        this.mutationError = axios.isAxiosError(error)
          ? (error.response?.data?.errors?.callflow?.[0] ??
            error.response?.data?.message ??
            'Unable to delete the call route.')
          : 'Unable to delete the call route.'

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
        this.treeMutationError = axios.isAxiosError(error)
          ? (error.response?.data?.errors?.source_path?.[0] ??
            error.response?.data?.errors?.destination_parent_path?.[0] ??
            error.response?.data?.errors?.destination_branch?.[0] ??
            error.response?.data?.message ??
            'Unable to move the callflow node.')
          : 'Unable to move the callflow node.'

        return null
      } finally {
        this.treeMoving = false
      }
    },
    async loadTreeEditor(accountId: string, callflowId: string): Promise<boolean> {
      this.treeEditor = null
      this.treeEditorLoading = true
      this.treeNodeError = null
      this.treeNodeFieldErrors = {}

      try {
        this.treeEditor = await callflowApi.editor(accountId, callflowId)

        return true
      } catch (error) {
        this.treeNodeError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load action destinations.')
          : 'Unable to load action destinations.'

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
        this.treeMutationError = axios.isAxiosError(error)
          ? (error.response?.data?.errors?.source_path?.[0] ??
            error.response?.data?.errors?.target_path?.[0] ??
            error.response?.data?.errors?.mode?.[0] ??
            error.response?.data?.message ??
            'Unable to reorder the callflow nodes.')
          : 'Unable to reorder the callflow nodes.'

        return null
      } finally {
        this.treeMoving = false
      }
    },
    closeTreeEditor(): void {
      this.treeEditor = null
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
        this.closeTreeEditor()

        return updated
      } catch (error) {
        this.treeNodeFieldErrors = axios.isAxiosError(error)
          ? (error.response?.data?.errors ?? {})
          : {}
        this.treeNodeError =
          Object.keys(this.treeNodeFieldErrors).length > 0
            ? null
            : axios.isAxiosError(error)
              ? (error.response?.data?.message ?? 'Unable to save the callflow action.')
              : 'Unable to save the callflow action.'

        return null
      } finally {
        this.treeNodeSaving = false
      }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true
      this.error = null

      try {
        let run = await callflowApi.startProjectionSync(accountId)

        for (
          let attempt = 0;
          attempt < 40 && ['queued', 'running'].includes(run.status);
          attempt += 1
        ) {
          await new Promise((resolve) => window.setTimeout(resolve, 500))
          run = await callflowApi.syncStatus(accountId, run.id)
        }

        if (run.status === 'failed') throw new Error(run.error_message ?? 'Routing sync failed.')
        if (run.status !== 'succeeded')
          throw new Error('Routing sync is still running. Reload shortly.')

        await this.load(accountId, 1)
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to synchronize call routes.')
          : error instanceof Error
            ? error.message
            : 'Unable to synchronize call routes.'
      } finally {
        this.synchronizing = false
      }
    },
  },
})
