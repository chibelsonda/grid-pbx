import axios from 'axios'
import { defineStore } from 'pinia'
import { voicemailApi } from '../api/voicemailApi'
import { defaultVoicemailFormOptions } from '../voicemailForm'
import type {
  SyncState,
  VoicemailBox,
  VoicemailBoxInput,
  VoicemailMessage,
  VoicemailMessageFolder,
  VoicemailFormOptions,
} from '../types/voicemail'

const defaultSync: SyncState = { status: 'stale', last_successful_at: null, error_message: null }

export const useVoicemailStore = defineStore('voicemail', {
  state: () => ({
    records: [] as VoicemailBox[],
    detail: null as VoicemailBox | null,
    sync: { ...defaultSync },
    search: '',
    page: 1,
    lastPage: 1,
    total: 0,
    loading: false,
    detailLoading: false,
    error: null as string | null,
    detailError: null as string | null,
    mutationLoading: false,
    mutationError: null as string | null,
    fieldErrors: {} as Record<string, string[]>,
    formOptions: defaultVoicemailFormOptions() as VoicemailFormOptions,
    messages: [] as VoicemailMessage[],
    messageSearch: '',
    messageFolder: '',
    messagePage: 1,
    messageLastPage: 1,
    messageTotal: 0,
    messagesLoading: false,
    messagesError: null as string | null,
    selectedMessageIds: [] as string[],
    messageMutationLoading: false,
    messageMutationError: null as string | null,
    greetingMutationLoading: false,
    greetingMutationError: null as string | null,
  }),
  actions: {
    reset(): void {
      this.records = []
      this.detail = null
      this.sync = { ...defaultSync }
      this.page = 1
      this.lastPage = 1
      this.total = 0
      this.error = null
      this.detailError = null
      this.mutationError = null
      this.fieldErrors = {}
      this.formOptions = defaultVoicemailFormOptions()
      this.messages = []
      this.messageSearch = ''
      this.messageFolder = ''
      this.messagePage = 1
      this.messageLastPage = 1
      this.messageTotal = 0
      this.messagesError = null
      this.selectedMessageIds = []
      this.messageMutationError = null
      this.greetingMutationError = null
    },
    async load(accountId: string, page?: number): Promise<void> {
      this.loading = true
      this.error = null

      try {
        const response = await voicemailApi.list(accountId, this.search, page ?? this.page)
        this.records = response.data
        this.sync = response.meta.sync
        this.page = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      } catch (error) {
        this.error = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load voicemail boxes.')
          : 'Unable to load voicemail boxes.'
      } finally {
        this.loading = false
      }
    },
    async loadDetail(accountId: string, voicemailBoxId: string): Promise<void> {
      this.detailLoading = true
      this.detailError = null
      this.detail = null

      try {
        this.detail = await voicemailApi.detail(accountId, voicemailBoxId)
      } catch (error) {
        this.detailError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load the voicemail box.')
          : 'Unable to load the voicemail box.'
      } finally {
        this.detailLoading = false
      }
    },
    async loadFormOptions(accountId: string): Promise<void> {
      try {
        this.formOptions = await voicemailApi.options(accountId)
      } catch {
        this.formOptions = defaultVoicemailFormOptions()
      }
    },
    async loadMessages(accountId: string, voicemailBoxId: string, page?: number): Promise<void> {
      this.messagesLoading = true
      this.messagesError = null

      try {
        const response = await voicemailApi.messages(
          accountId,
          voicemailBoxId,
          this.messageSearch,
          this.messageFolder,
          page ?? this.messagePage,
        )
        this.messages = response.data
        this.messagePage = response.meta.current_page
        this.messageLastPage = response.meta.last_page
        this.messageTotal = response.meta.total
        this.selectedMessageIds = []
      } catch (error) {
        this.messagesError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to load voicemail messages.')
          : 'Unable to load voicemail messages.'
      } finally {
        this.messagesLoading = false
      }
    },
    async changeMessageFolder(
      accountId: string,
      voicemailBoxId: string,
      messageId: string,
      folder: VoicemailMessageFolder,
    ): Promise<boolean> {
      this.messageMutationLoading = true
      this.messageMutationError = null

      try {
        const updated = await voicemailApi.changeMessageFolder(
          accountId,
          voicemailBoxId,
          messageId,
          folder,
        )
        const index = this.messages.findIndex((message) => message.id === messageId)
        if (index !== -1) {
          this.adjustMessageCounts(this.messages[index]?.folder ?? null, updated.folder)
          this.messages[index] = updated
        }

        return true
      } catch (error) {
        this.messageMutationError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to update the voicemail message.')
          : 'Unable to update the voicemail message.'
        return false
      } finally {
        this.messageMutationLoading = false
      }
    },
    async bulkChangeMessageFolder(
      accountId: string,
      voicemailBoxId: string,
      folder: VoicemailMessageFolder,
    ): Promise<boolean> {
      if (this.selectedMessageIds.length === 0) return false
      this.messageMutationLoading = true
      this.messageMutationError = null

      try {
        const result = await voicemailApi.bulkChangeMessageFolder(
          accountId,
          voicemailBoxId,
          [...this.selectedMessageIds],
          folder,
        )
        const succeeded = new Set(result.succeeded)
        this.messages = this.messages.map((message) => {
          if (!succeeded.has(message.id)) return message
          this.adjustMessageCounts(message.folder, result.folder)
          return { ...message, folder: result.folder }
        })
        this.selectedMessageIds = []

        if (result.failed.length > 0) {
          this.messageMutationError = `${result.failed.length} message${result.failed.length === 1 ? '' : 's'} could not be updated.`
        }

        return result.succeeded.length > 0
      } catch (error) {
        this.messageMutationError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to update the selected voicemail messages.')
          : 'Unable to update the selected voicemail messages.'
        return false
      } finally {
        this.messageMutationLoading = false
      }
    },
    adjustMessageCounts(
      from: VoicemailMessageFolder | null,
      to: VoicemailMessageFolder | null,
    ): void {
      if (!this.detail || from === to) return
      if (from) this.detail.message_counts[from] = Math.max(0, this.detail.message_counts[from] - 1)
      if (to) this.detail.message_counts[to] += 1
    },
    async uploadGreeting(
      accountId: string,
      voicemailBoxId: string,
      name: string,
      audio: File,
    ): Promise<boolean> {
      this.greetingMutationLoading = true
      this.greetingMutationError = null

      try {
        const greeting = await voicemailApi.uploadGreeting(accountId, voicemailBoxId, name, audio)
        if (this.detail?.id === voicemailBoxId) this.detail.unavailable_greeting = greeting
        return true
      } catch (error) {
        this.greetingMutationError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to upload the voicemail greeting.')
          : 'Unable to upload the voicemail greeting.'
        return false
      } finally {
        this.greetingMutationLoading = false
      }
    },
    async removeGreeting(accountId: string, voicemailBoxId: string): Promise<boolean> {
      this.greetingMutationLoading = true
      this.greetingMutationError = null

      try {
        await voicemailApi.removeGreeting(accountId, voicemailBoxId)
        if (this.detail?.id === voicemailBoxId) this.detail.unavailable_greeting = null
        return true
      } catch (error) {
        this.greetingMutationError = axios.isAxiosError(error)
          ? (error.response?.data?.message ?? 'Unable to remove the voicemail greeting.')
          : 'Unable to remove the voicemail greeting.'
        return false
      } finally {
        this.greetingMutationLoading = false
      }
    },
    async create(accountId: string, input: VoicemailBoxInput): Promise<VoicemailBox | null> {
      return this.mutate(() => voicemailApi.create(accountId, input))
    },
    async update(
      accountId: string,
      voicemailBoxId: string,
      input: VoicemailBoxInput,
    ): Promise<VoicemailBox | null> {
      return this.mutate(() => voicemailApi.update(accountId, voicemailBoxId, input))
    },
    async remove(accountId: string, voicemailBoxId: string): Promise<boolean> {
      this.mutationLoading = true
      this.mutationError = null

      try {
        await voicemailApi.remove(accountId, voicemailBoxId)
        this.records = this.records.filter((record) => record.id !== voicemailBoxId)
        this.detail = null

        return true
      } catch (error) {
        this.captureMutationError(error, 'Unable to delete the voicemail box.')
        return false
      } finally {
        this.mutationLoading = false
      }
    },
    async mutate(operation: () => Promise<VoicemailBox>): Promise<VoicemailBox | null> {
      this.mutationLoading = true
      this.mutationError = null
      this.fieldErrors = {}

      try {
        const voicemailBox = await operation()
        this.detail = voicemailBox
        const index = this.records.findIndex((record) => record.id === voicemailBox.id)
        if (index === -1) this.records.unshift(voicemailBox)
        else this.records[index] = voicemailBox
        return voicemailBox
      } catch (error) {
        this.captureMutationError(error, 'Unable to save the voicemail box.')
        return null
      } finally {
        this.mutationLoading = false
      }
    },
    captureMutationError(error: unknown, fallback: string): void {
      if (axios.isAxiosError(error)) {
        this.fieldErrors = error.response?.data?.errors ?? {}
        this.mutationError = Object.keys(this.fieldErrors).length
          ? null
          : (error.response?.data?.message ?? fallback)
        return
      }
      this.mutationError = fallback
    },
  },
})
