import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { callflowApi, type CallflowPage } from '../api/callflowApi'
import type {
  Callflow,
  CallflowEditor,
  CallflowInlineNodeCreateInput,
  CallflowInlineNodeUpdateInput,
  CallflowTreeMoveInput,
  CallflowTreeReorderInput,
  CallflowTreeNodeCreateInput,
  CallflowTreeNodeUpdateInput,
  CallflowUpdate,
  SyncRun,
} from '../types/callRouting'
import { useCallflowStore } from './callflowStore'

vi.mock('../api/callflowApi', () => ({
  callflowApi: {
    list: vi.fn<(accountId: string, filters: object, page?: number) => Promise<CallflowPage>>(),
    detail: vi.fn<(accountId: string, callflowId: string) => Promise<Callflow>>(),
    editor: vi.fn<(accountId: string, callflowId: string) => Promise<CallflowEditor>>(),
    createEditor: vi.fn<(accountId: string) => Promise<CallflowEditor>>(),
    create: vi.fn<(accountId: string, input: CallflowUpdate) => Promise<Callflow>>(),
    update:
      vi.fn<(accountId: string, callflowId: string, input: CallflowUpdate) => Promise<Callflow>>(),
    moveTreeNode:
      vi.fn<
        (accountId: string, callflowId: string, input: CallflowTreeMoveInput) => Promise<Callflow>
      >(),
    reorderTreeNodes:
      vi.fn<
        (
          accountId: string,
          callflowId: string,
          input: CallflowTreeReorderInput,
        ) => Promise<Callflow>
      >(),
    createTreeNode:
      vi.fn<
        (
          accountId: string,
          callflowId: string,
          input: CallflowTreeNodeCreateInput,
        ) => Promise<Callflow>
      >(),
    updateTreeNode:
      vi.fn<
        (
          accountId: string,
          callflowId: string,
          input: CallflowTreeNodeUpdateInput,
        ) => Promise<Callflow>
      >(),
    createInlineTreeNode:
      vi.fn<
        (
          accountId: string,
          callflowId: string,
          input: CallflowInlineNodeCreateInput,
        ) => Promise<Callflow>
      >(),
    updateInlineTreeNode:
      vi.fn<
        (
          accountId: string,
          callflowId: string,
          input: CallflowInlineNodeUpdateInput,
        ) => Promise<Callflow>
      >(),
    delete: vi.fn<(accountId: string, callflowId: string) => Promise<void>>(),
    startProjectionSync: vi.fn<(accountId: string) => Promise<SyncRun>>(),
    syncStatus: vi.fn<(accountId: string, runId: string) => Promise<SyncRun>>(),
  },
}))

const callflow: Callflow = {
  id: '6db510f0-7821-4ffc-a7fa-eae51d94b6b3',
  name: 'Main Reception',
  route_type: 'phone_number',
  numbers: ['+15551234567'],
  patterns: [],
  flags: [],
  modules: ['ring_group', 'voicemail'],
  root_module: 'ring_group',
  node_count: 2,
  max_depth: 2,
  feature_code: null,
  flow: {
    module: 'ring_group',
    target: null,
    reference_status: 'not_applicable',
    children: {
      _: {
        module: 'voicemail',
        target: { type: 'voicemail', id: 'mailbox-public-id', label: 'Reception mailbox' },
        reference_status: 'resolved',
        children: {},
      },
    },
  },
  linked_extension: { id: 'extension-public-id', display_name: 'Reception', extension: '1001' },
  phone_numbers: [{ id: 'number-public-id', number: '+15551234567', state: 'in_service' }],
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T10:00:00+08:00',
}

describe('callflow store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads filtered routing projections and freshness metadata', async () => {
    vi.mocked(callflowApi.list).mockResolvedValue({
      data: [callflow],
      links: { prev: null, next: null },
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 1,
        sync: {
          status: 'healthy',
          last_successful_at: '2026-08-28T10:00:00+08:00',
          error_message: null,
        },
      },
    })
    const store = useCallflowStore()
    store.filters.search = 'Reception'
    store.filters.module = 'voicemail'

    await store.load('account-1', 1)

    expect(callflowApi.list).toHaveBeenCalledWith('account-1', store.filters, 1)
    expect(store.records).toEqual([callflow])
    expect(store.sync.status).toBe('healthy')
  })

  it('loads a safe route tree for the right-side panel', async () => {
    vi.mocked(callflowApi.detail).mockResolvedValue(callflow)
    const store = useCallflowStore()

    await store.loadDetail('account-1', callflow.id)

    expect(callflowApi.detail).toHaveBeenCalledWith('account-1', callflow.id)
    expect(store.detail?.flow?.children._?.module).toBe('voicemail')
  })

  it('reloads routes after the shared PBX projection sync completes', async () => {
    vi.mocked(callflowApi.startProjectionSync).mockResolvedValue({
      id: 'run-1',
      resource_type: 'extensions',
      status: 'succeeded',
      error_message: null,
    })
    vi.mocked(callflowApi.list).mockResolvedValue({
      data: [],
      links: { prev: null, next: null },
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 0,
        sync: { status: 'healthy', last_successful_at: null, error_message: null },
      },
    })
    const store = useCallflowStore()

    await store.synchronize('account-1')

    expect(callflowApi.startProjectionSync).toHaveBeenCalledWith('account-1')
    expect(callflowApi.list).toHaveBeenCalledWith('account-1', store.filters, 1)
    expect(store.synchronizing).toBe(false)
  })

  it('loads public editor options and replaces the projected record after saving', async () => {
    vi.mocked(callflowApi.editor).mockResolvedValue({
      mode: 'update',
      editable: true,
      blocked_reason: null,
      fallback: { editable: true, blocked_reason: null, target: null },
      menu_branches: {
        editable: true,
        blocked_reason: null,
        branches: [],
        legacy_hash_present: false,
        unknown_branch_keys: [],
      },
      temporal_match: {
        editable: true,
        blocked_reason: null,
        target: null,
        preserved_branch_count: 0,
      },
      direct_temporal_routes: [],
      temporal_rule_sets: {},
      temporal_rules: [],
      caller_id_lists: [],
      destination_types: [{ value: 'extension', label: 'Extension' }],
      destinations: {
        extension: [{ id: 'extension-public-id', label: 'Reception', detail: '1001' }],
        device: [],
        voicemail: [],
        callflow: [],
        media: [],
        directory: [],
        group: [],
        queue: [],
        menu: [],
        conference: [],
        fax_box: [],
        temporal_rule_set: [],
        temporal_rules: [],
      },
      phone_numbers: [
        {
          id: 'number-public-id',
          number: '+15551234567',
          state: 'in_service',
          selected: true,
          available: true,
          assigned_callflow: { id: callflow.id, name: callflow.name },
        },
      ],
    })
    const updated = {
      ...callflow,
      name: 'Updated reception route',
      root_module: 'user',
      flow: {
        module: 'user',
        target: { type: 'extension' as const, id: 'extension-public-id', label: 'Reception' },
        reference_status: 'resolved' as const,
        children: {},
      },
    }
    vi.mocked(callflowApi.update).mockResolvedValue(updated)
    const store = useCallflowStore()
    store.records = [callflow]
    store.detail = callflow

    await store.openEditor('account-1', callflow.id)
    const input: CallflowUpdate = {
      name: 'Updated reception route',
      destination_type: 'extension',
      destination_id: 'extension-public-id',
      phone_number_ids: ['number-public-id'],
    }
    await store.update('account-1', callflow.id, input)

    expect(callflowApi.editor).toHaveBeenCalledWith('account-1', callflow.id)
    expect(callflowApi.update).toHaveBeenCalledWith('account-1', callflow.id, input)
    expect(store.records[0]?.name).toBe('Updated reception route')
    expect(store.detail?.flow?.target?.id).toBe('extension-public-id')
    expect(store.editorOpen).toBe(false)
  })

  it('creates and deletes a guided route through the domain API', async () => {
    const editor: CallflowEditor = {
      mode: 'create',
      editable: true,
      blocked_reason: null,
      fallback: { editable: true, blocked_reason: null, target: null },
      menu_branches: {
        editable: true,
        blocked_reason: null,
        branches: [],
        legacy_hash_present: false,
        unknown_branch_keys: [],
      },
      temporal_match: {
        editable: true,
        blocked_reason: null,
        target: null,
        preserved_branch_count: 0,
      },
      direct_temporal_routes: [],
      temporal_rule_sets: {},
      temporal_rules: [],
      caller_id_lists: [],
      destination_types: [{ value: 'extension', label: 'Extension' }],
      destinations: {
        extension: [{ id: 'extension-public-id', label: 'Reception', detail: '1001' }],
        device: [],
        voicemail: [],
        callflow: [],
        media: [],
        directory: [],
        group: [],
        queue: [],
        menu: [],
        conference: [],
        fax_box: [],
        temporal_rule_set: [],
        temporal_rules: [],
      },
      phone_numbers: [
        {
          id: 'number-public-id',
          number: '+15551234567',
          state: 'in_service',
          selected: false,
          available: true,
          assigned_callflow: null,
        },
      ],
    }
    vi.mocked(callflowApi.createEditor).mockResolvedValue(editor)
    vi.mocked(callflowApi.create).mockResolvedValue(callflow)
    vi.mocked(callflowApi.delete).mockResolvedValue()
    const store = useCallflowStore()
    const input: CallflowUpdate = {
      name: 'Main Reception',
      destination_type: 'extension',
      destination_id: 'extension-public-id',
      phone_number_ids: ['number-public-id'],
    }

    await store.openCreateEditor('account-1')
    await store.create('account-1', input)
    const deleted = await store.destroy('account-1', callflow.id)

    expect(callflowApi.createEditor).toHaveBeenCalledWith('account-1')
    expect(callflowApi.create).toHaveBeenCalledWith('account-1', input)
    expect(callflowApi.delete).toHaveBeenCalledWith('account-1', callflow.id)
    expect(deleted).toBe(true)
    expect(store.records).toEqual([])
  })

  it('keeps API validation errors inline without a duplicate editor alert', async () => {
    vi.mocked(callflowApi.create).mockRejectedValue({
      isAxiosError: true,
      response: {
        data: {
          message: 'The given data was invalid.',
          errors: { name: ['Enter a route name.'] },
        },
      },
    })
    const store = useCallflowStore()

    await store.create('account-1', {
      name: '',
      destination_type: 'extension',
      destination_id: 'extension-public-id',
      phone_number_ids: [],
    })

    expect(store.fieldErrors.name).toEqual(['Enter a route name.'])
    expect(store.editorError).toBeNull()
  })

  it('replaces the projected tree after a typed subtree move', async () => {
    const input: CallflowTreeMoveInput = {
      source_path: ['1'],
      destination_parent_path: ['2'],
      destination_branch: '_',
    }
    const updated: Callflow = {
      ...callflow,
      max_depth: 3,
      flow: {
        module: 'menu',
        target: null,
        reference_status: 'not_applicable',
        children: {
          '2': {
            module: 'group',
            target: { type: 'group', id: 'group-public', label: 'Support' },
            reference_status: 'resolved',
            children: {
              _: {
                module: 'user',
                target: { type: 'extension', id: 'extension-public', label: 'Reception' },
                reference_status: 'resolved',
                children: {},
              },
            },
          },
        },
      },
    }
    vi.mocked(callflowApi.moveTreeNode).mockResolvedValue(updated)
    const store = useCallflowStore()
    store.detail = callflow
    store.records = [callflow]

    await store.moveTreeNode('account-1', callflow.id, input)

    expect(callflowApi.moveTreeNode).toHaveBeenCalledWith('account-1', callflow.id, input)
    expect(store.detail?.max_depth).toBe(3)
    expect(store.records[0]?.flow?.children['2']?.children._?.module).toBe('user')
    expect(store.treeMutationError).toBeNull()
  })

  it('replaces the projection after adding a guided action node', async () => {
    const input: CallflowTreeNodeCreateInput = {
      parent_path: ['_'],
      branch: '_',
      destination_type: 'voicemail',
      destination_id: '4a2aedf6-41ed-46db-9496-e0468e97cc95',
    }
    const updated: Callflow = {
      ...callflow,
      node_count: 3,
      flow: {
        ...callflow.flow!,
        children: {
          _: {
            ...callflow.flow!.children._!,
            children: {
              _: {
                module: 'voicemail',
                target: {
                  type: 'voicemail',
                  id: input.destination_id,
                  label: 'After hours',
                },
                reference_status: 'resolved',
                children: {},
              },
            },
          },
        },
      },
    }
    vi.mocked(callflowApi.createTreeNode).mockResolvedValue(updated)
    const store = useCallflowStore()
    store.detail = callflow
    store.records = [callflow]

    await store.createTreeNode('account-1', callflow.id, input)

    expect(callflowApi.createTreeNode).toHaveBeenCalledWith('account-1', callflow.id, input)
    expect(store.detail?.node_count).toBe(3)
    expect(store.records[0]?.flow?.children._?.children._?.target?.label).toBe('After hours')
    expect(store.treeNodeError).toBeNull()
  })

  it('replaces the projection after adding a schema-backed inline action', async () => {
    const input: CallflowInlineNodeCreateInput = {
      parent_path: ['_'],
      branch: '_',
      module: 'tts',
      data: {
        text: 'Please hold.',
        voice: 'female',
        language: null,
        engine: null,
        endless_playback: false,
        terminators: ['#'],
        skip_module: false,
      },
    }
    const updated: Callflow = {
      ...callflow,
      node_count: 3,
      flow: {
        ...callflow.flow!,
        children: {
          _: {
            ...callflow.flow!.children._!,
            children: {
              _: {
                module: 'tts',
                target: null,
                reference_status: 'not_applicable',
                settings: input.data,
                children: {},
              },
            },
          },
        },
      },
    }
    vi.mocked(callflowApi.createInlineTreeNode).mockResolvedValue(updated)
    const store = useCallflowStore()
    store.detail = callflow
    store.records = [callflow]

    await store.createInlineTreeNode('account-1', callflow.id, input)

    expect(callflowApi.createInlineTreeNode).toHaveBeenCalledWith('account-1', callflow.id, input)
    expect(store.detail?.flow?.children._?.children._?.settings?.text).toBe('Please hold.')
    expect(store.treeNodeError).toBeNull()
  })

  it('replaces the projected tree after a lossless subtree reorder', async () => {
    const input: CallflowTreeReorderInput = {
      mode: 'swap',
      source_path: ['1'],
      target_path: ['2'],
    }
    const updated = { ...callflow, name: 'Reordered route' }
    vi.mocked(callflowApi.reorderTreeNodes).mockResolvedValue(updated)
    const store = useCallflowStore()
    store.detail = callflow
    store.records = [callflow]

    await store.reorderTreeNodes('account-1', callflow.id, input)

    expect(callflowApi.reorderTreeNodes).toHaveBeenCalledWith('account-1', callflow.id, input)
    expect(store.detail?.name).toBe('Reordered route')
    expect(store.records[0]?.name).toBe('Reordered route')
    expect(store.treeMutationError).toBeNull()
  })
})
