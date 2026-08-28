import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { voicemailApi, type VoicemailBoxPage, type VoicemailMessagePage } from '../api/voicemailApi'
import type {
  ExtensionOption,
  VoicemailBox,
  VoicemailBoxInput,
  VoicemailMessage,
  VoicemailMessageBulkResult,
  VoicemailMessageFolder,
  VoicemailGreeting,
} from '../types/voicemail'
import { useVoicemailStore } from './voicemailStore'

vi.mock('../api/voicemailApi', () => ({
  voicemailApi: {
    list: vi.fn<(accountId: string, search?: string, page?: number) => Promise<VoicemailBoxPage>>(),
    detail: vi.fn<(accountId: string, voicemailBoxId: string) => Promise<VoicemailBox>>(),
    create: vi.fn<(accountId: string, input: VoicemailBoxInput) => Promise<VoicemailBox>>(),
    update:
      vi.fn<
        (
          accountId: string,
          voicemailBoxId: string,
          input: VoicemailBoxInput,
        ) => Promise<VoicemailBox>
      >(),
    remove: vi.fn<(accountId: string, voicemailBoxId: string) => Promise<void>>(),
    extensionOptions: vi.fn<(accountId: string) => Promise<ExtensionOption[]>>(),
    messages:
      vi.fn<
        (
          accountId: string,
          voicemailBoxId: string,
          search?: string,
          folder?: string,
          page?: number,
        ) => Promise<VoicemailMessagePage>
      >(),
    changeMessageFolder:
      vi.fn<
        (
          accountId: string,
          voicemailBoxId: string,
          messageId: string,
          folder: VoicemailMessageFolder,
        ) => Promise<VoicemailMessage>
      >(),
    bulkChangeMessageFolder:
      vi.fn<
        (
          accountId: string,
          voicemailBoxId: string,
          messageIds: string[],
          folder: VoicemailMessageFolder,
        ) => Promise<VoicemailMessageBulkResult>
      >(),
    uploadGreeting:
      vi.fn<
        (
          accountId: string,
          voicemailBoxId: string,
          name: string,
          audio: File,
        ) => Promise<VoicemailGreeting>
      >(),
    removeGreeting: vi.fn<(accountId: string, voicemailBoxId: string) => Promise<void>>(),
  },
}))

const voicemailBox: VoicemailBox = {
  id: 'voicemail-1',
  name: 'Reception voicemail',
  mailbox: '1001',
  timezone: 'Asia/Manila',
  notification_emails: ['ops@example.com'],
  transcribe: true,
  require_pin: true,
  is_setup: false,
  message_counts: { total: 2, new: 1, saved: 1, deleted: 0 },
  unavailable_greeting: null,
  assigned_extension: { id: 'extension-1', display_name: 'Reception', extension: '1001' },
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T06:30:00Z',
}

const message: VoicemailMessage = {
  id: 'message-1',
  folder: 'new',
  caller_id_name: 'Alice Customer',
  caller_id_number: '+15551234567',
  from_address: 'sip:+15551234567@example.com',
  to_address: 'sip:1001@example.com',
  length: 42000,
  occurred_at: '2026-08-28T06:30:00Z',
  transcription_result: 'success',
  transcription_text: 'Please call me back.',
  sync_status: 'healthy',
  last_synced_at: '2026-08-28T06:31:00Z',
}

describe('voicemail store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('hydrates an account-scoped voicemail page and sync metadata', async () => {
    const page: VoicemailBoxPage = {
      data: [voicemailBox],
      links: { prev: null, next: null },
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 1,
        sync: {
          status: 'healthy',
          last_successful_at: '2026-08-28T06:30:00Z',
          error_message: null,
        },
      },
    }
    vi.mocked(voicemailApi.list).mockResolvedValue(page)
    const store = useVoicemailStore()
    store.search = 'Reception'

    await store.load('account-1', 1)

    expect(voicemailApi.list).toHaveBeenCalledWith('account-1', 'Reception', 1)
    expect(store.records).toEqual([voicemailBox])
    expect(store.sync.status).toBe('healthy')
  })

  it('creates a mailbox and makes it the active projection', async () => {
    const input: VoicemailBoxInput = {
      name: 'Reception voicemail',
      mailbox: '1001',
      assigned_extension_id: 'extension-1',
      timezone: 'Asia/Manila',
      notification_emails: ['ops@example.com'],
      transcribe: true,
      require_pin: true,
      pin: '123456',
    }
    vi.mocked(voicemailApi.create).mockResolvedValue(voicemailBox)
    const store = useVoicemailStore()

    const created = await store.create('account-1', input)

    expect(voicemailApi.create).toHaveBeenCalledWith('account-1', input)
    expect(created).toEqual(voicemailBox)
    expect(store.detail).toEqual(voicemailBox)
    expect(store.records).toEqual([voicemailBox])
  })

  it('loads projected message metadata with account, mailbox, and folder scope', async () => {
    vi.mocked(voicemailApi.messages).mockResolvedValue({
      data: [message],
      links: { prev: null, next: null },
      meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
    })
    const store = useVoicemailStore()
    store.messageSearch = 'Alice'
    store.messageFolder = 'new'

    await store.loadMessages('account-1', 'voicemail-1', 1)

    expect(voicemailApi.messages).toHaveBeenCalledWith(
      'account-1',
      'voicemail-1',
      'Alice',
      'new',
      1,
    )
    expect(store.messages).toEqual([message])
    expect(store.messageTotal).toBe(1)
  })

  it('updates one projected message after a successful folder change', async () => {
    const updated = { ...message, folder: 'saved' as const }
    vi.mocked(voicemailApi.changeMessageFolder).mockResolvedValue(updated)
    const store = useVoicemailStore()
    store.messages = [message]

    const succeeded = await store.changeMessageFolder(
      'account-1',
      'voicemail-1',
      'message-1',
      'saved',
    )

    expect(succeeded).toBe(true)
    expect(store.messages[0]?.folder).toBe('saved')
    expect(voicemailApi.changeMessageFolder).toHaveBeenCalledWith(
      'account-1',
      'voicemail-1',
      'message-1',
      'saved',
    )
  })

  it('applies successful bulk changes and reports partial failures', async () => {
    const second = { ...message, id: 'message-2', caller_id_name: 'Bob Customer' }
    vi.mocked(voicemailApi.bulkChangeMessageFolder).mockResolvedValue({
      folder: 'deleted',
      succeeded: ['message-1'],
      failed: [{ id: 'message-2', reason: 'not_found' }],
    })
    const store = useVoicemailStore()
    store.messages = [message, second]
    store.selectedMessageIds = ['message-1', 'message-2']

    const succeeded = await store.bulkChangeMessageFolder('account-1', 'voicemail-1', 'deleted')

    expect(succeeded).toBe(true)
    expect(store.messages[0]?.folder).toBe('deleted')
    expect(store.messages[1]?.folder).toBe('new')
    expect(store.selectedMessageIds).toEqual([])
    expect(store.messageMutationError).toBe('1 message could not be updated.')
  })

  it('uploads and attaches a greeting projection to the active mailbox', async () => {
    const greeting: VoicemailGreeting = {
      id: 'greeting-1',
      type: 'unavailable',
      name: 'Reception greeting',
      description: 'greeting.mp3',
      content_type: 'audio/mpeg',
      content_length: 4096,
      media_source: 'upload',
      streamable: true,
      sync_status: 'healthy',
      last_synced_at: '2026-08-28T09:00:00Z',
    }
    vi.mocked(voicemailApi.uploadGreeting).mockResolvedValue(greeting)
    const store = useVoicemailStore()
    store.detail = { ...voicemailBox }
    const audio = new File(['MP3!'], 'greeting.mp3', { type: 'audio/mpeg' })

    const succeeded = await store.uploadGreeting(
      'account-1',
      'voicemail-1',
      'Reception greeting',
      audio,
    )

    expect(succeeded).toBe(true)
    expect(store.detail.unavailable_greeting).toEqual(greeting)
  })
})
