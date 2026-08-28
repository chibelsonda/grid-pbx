import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { extensionApi, type ExtensionPage } from '../api/extensionApi'
import { defaultExtensionUserConfiguration } from '../extensionForm'
import type {
  ExtensionCreate,
  ExtensionDeletionPreview,
  ExtensionDetail,
  ExtensionRecoveryOperation,
  ExtensionUpdate,
  SyncRun,
} from '../types/extension'
import { useExtensionStore } from './extensionStore'

vi.mock('../api/extensionApi', () => ({
  extensionApi: {
    list: vi.fn<(accountId: string, search?: string, page?: number) => Promise<ExtensionPage>>(),
    detail: vi.fn<(accountId: string, extensionId: string) => Promise<ExtensionDetail>>(),
    create: vi.fn<(accountId: string, input: ExtensionCreate) => Promise<ExtensionDetail>>(),
    update:
      vi.fn<
        (accountId: string, extensionId: string, input: ExtensionUpdate) => Promise<ExtensionDetail>
      >(),
    deletionPreview:
      vi.fn<(accountId: string, extensionId: string) => Promise<ExtensionDeletionPreview>>(),
    remove:
      vi.fn<(accountId: string, extensionId: string, confirmation: string) => Promise<void>>(),
    recoveryQueue: vi.fn<(accountId: string) => Promise<ExtensionRecoveryOperation[]>>(),
    recover:
      vi.fn<
        (
          accountId: string,
          operationId: string,
          confirmation?: string | null,
        ) => Promise<ExtensionRecoveryOperation>
      >(),
    startSync: vi.fn<(accountId: string) => Promise<SyncRun>>(),
    syncRun: vi.fn<(accountId: string, runId: string) => Promise<SyncRun>>(),
  },
}))

const extension: ExtensionDetail = {
  id: 'extension-1',
  display_name: 'Alice Operator',
  first_name: 'Alice',
  last_name: 'Operator',
  username: 'alice',
  email: 'alice@example.test',
  extension: '1001',
  timezone: 'Asia/Manila',
  is_enabled: true,
  is_managed: true,
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T10:00:00+08:00',
  configuration: defaultExtensionUserConfiguration(),
  devices: [],
  voicemail_boxes: [],
  callflows: [],
}

const input: ExtensionUpdate = {
  first_name: 'Alice',
  last_name: 'Operations',
  username: 'alice',
  email: 'alice@example.test',
  extension: '1002',
  timezone: 'Asia/Manila',
  is_enabled: true,
  ...defaultExtensionUserConfiguration(),
  voicemail: {
    enabled: false,
    notification_emails: [],
    transcribe: false,
    require_pin: false,
    pin: null,
  },
}

describe('extension store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('updates the active extension and replaces its list projection', async () => {
    const updated = { ...extension, last_name: 'Operations', display_name: 'Alice Operations' }
    vi.mocked(extensionApi.update).mockResolvedValue(updated)
    const store = useExtensionStore()
    store.records = [extension]

    const result = await store.update('account-1', extension.id, input)

    expect(extensionApi.update).toHaveBeenCalledWith('account-1', extension.id, input)
    expect(result).toEqual(updated)
    expect(store.detail).toEqual(updated)
    expect(store.records).toEqual([updated])
    expect(store.mutationLoading).toBe(false)
  })

  it('loads a read-only dependency preview without mutating extension detail', async () => {
    const preview: ExtensionDeletionPreview = {
      extension: {
        id: extension.id,
        display_name: extension.display_name,
        extension: extension.extension,
        managed: true,
      },
      can_delete: false,
      blockers: [{ code: 'shared_devices', message: 'Detach the shared device first.' }],
      managed_resources: { devices: [], voicemail_boxes: [], callflows: [] },
      shared_resources: { device_count: 1, voicemail_box_count: 0, callflow_count: 0 },
      referencing_callflows: [],
      unresolved_callflows: [],
      recovery: null,
    }
    vi.mocked(extensionApi.deletionPreview).mockResolvedValue(preview)
    const store = useExtensionStore()
    store.detail = extension

    await store.loadDeletionPreview('account-1', extension.id)

    expect(extensionApi.deletionPreview).toHaveBeenCalledWith('account-1', extension.id)
    expect(store.deletionPreview).toEqual(preview)
    expect(store.detail).toEqual(extension)
    expect(store.previewLoading).toBe(false)
  })

  it('removes a successfully deleted extension from local UI state', async () => {
    vi.mocked(extensionApi.remove).mockResolvedValue()
    const store = useExtensionStore()
    store.records = [extension]
    store.detail = extension
    store.total = 1

    const removed = await store.remove('account-1', extension.id, '1001')

    expect(extensionApi.remove).toHaveBeenCalledWith('account-1', extension.id, '1001')
    expect(removed).toBe(true)
    expect(store.records).toEqual([])
    expect(store.detail).toBeNull()
    expect(store.total).toBe(0)
  })

  it('loads and clears a recovered provisioning cleanup without exposing its context', async () => {
    const operation: ExtensionRecoveryOperation = {
      id: 'operation-1',
      operation: 'provision',
      status: 'failed',
      display_name: 'Alice Operator',
      extension: '1001',
      extension_id: null,
      completed_steps: ['user'],
      failed_step: 'device',
      recovery_action: 'cleanup',
      repair_required: true,
      updated_at: '2026-08-28T10:00:00+08:00',
    }
    vi.mocked(extensionApi.recoveryQueue).mockResolvedValue([operation])
    vi.mocked(extensionApi.recover).mockResolvedValue({
      ...operation,
      status: 'recovered',
      repair_required: false,
    })
    const store = useExtensionStore()

    await store.loadRecoveryQueue('account-1')
    const recovered = await store.recover('account-1', operation)

    expect(extensionApi.recoveryQueue).toHaveBeenCalledWith('account-1')
    expect(extensionApi.recover).toHaveBeenCalledWith('account-1', 'operation-1', null)
    expect(recovered).toBe(true)
    expect(store.recoveryRecords).toEqual([])
    expect(store.recoveryActionLoading).toBe(false)
  })
})
