import axios from 'axios'
import { defineStore } from 'pinia'
import { menuApi } from '../api/menuApi'
import type { Menu, MenuInput, MenuOptions } from '../types/menu'

const emptyOptions: MenuOptions = { media: [] }
function message(error: unknown, fallback: string): string {
  return axios.isAxiosError(error) ? (error.response?.data?.message ?? fallback) : fallback
}

export const useMenuStore = defineStore('menus', {
  state: () => ({
    records: [] as Menu[], detail: null as Menu | null, options: { ...emptyOptions }, search: '',
    page: 1, lastPage: 1, total: 0, loading: false, saving: false, synchronizing: false,
    error: null as string | null, mutationError: null as string | null, fieldErrors: {} as Record<string, string[]>,
  }),
  actions: {
    reset(): void { this.records = []; this.detail = null; this.options = { ...emptyOptions }; this.total = 0; this.error = null; this.clearMutationError() },
    clearMutationError(): void { this.mutationError = null; this.fieldErrors = {} },
    capture(error: unknown, fallback: string): void {
      this.fieldErrors = axios.isAxiosError(error) ? (error.response?.data?.errors ?? {}) : {}
      this.mutationError = Object.keys(this.fieldErrors).length > 0 ? null : message(error, fallback)
    },
    async load(accountId: string, page = 1): Promise<void> {
      this.loading = true; this.error = null
      try {
        const response = await menuApi.list(accountId, this.search, page)
        this.records = response.data; this.page = response.meta.current_page; this.lastPage = response.meta.last_page; this.total = response.meta.total
      } catch (error) { this.error = message(error, 'Unable to load menus.') } finally { this.loading = false }
    },
    async prepare(accountId: string, id?: string): Promise<void> {
      this.loading = true; this.clearMutationError()
      try {
        const [options, detail] = await Promise.all([menuApi.options(accountId), id ? menuApi.detail(accountId, id) : Promise.resolve(null)])
        this.options = options; this.detail = detail
      } catch (error) { this.error = message(error, 'Unable to prepare the menu form.') } finally { this.loading = false }
    },
    replace(record: Menu): void {
      const index = this.records.findIndex(({ id }) => id === record.id)
      if (index >= 0) this.records[index] = record; else this.records.unshift(record)
      this.detail = record
    },
    async save(accountId: string, input: MenuInput): Promise<boolean> {
      this.saving = true; this.clearMutationError()
      try {
        const isNew = !this.detail
        this.replace(isNew ? await menuApi.create(accountId, input) : await menuApi.update(accountId, this.detail!.id, input))
        if (isNew) this.total += 1
        await this.load(accountId, this.page); return true
      } catch (error) { this.capture(error, 'Unable to save menu.'); return false } finally { this.saving = false }
    },
    async remove(accountId: string): Promise<boolean> {
      if (!this.detail) return false
      this.saving = true; this.clearMutationError()
      try {
        const id = this.detail.id; await menuApi.remove(accountId, id)
        this.records = this.records.filter((record) => record.id !== id); this.detail = null; this.total = Math.max(0, this.total - 1)
        await this.load(accountId, this.page); return true
      } catch (error) { this.capture(error, 'Unable to delete menu.'); return false } finally { this.saving = false }
    },
    async synchronize(accountId: string): Promise<void> {
      this.synchronizing = true; this.error = null
      try {
        let run = await menuApi.startSync(accountId)
        for (let attempt = 0; attempt < 40 && ['queued', 'running'].includes(run.status); attempt += 1) {
          await new Promise((resolve) => window.setTimeout(resolve, 500)); run = await menuApi.syncStatus(accountId, run.id)
        }
        if (run.status !== 'succeeded') throw new Error(run.error_message ?? 'Menu sync did not finish.')
        await this.load(accountId)
      } catch (error) { this.error = error instanceof Error ? error.message : message(error, 'Unable to synchronize menus.') }
      finally { this.synchronizing = false }
    },
  },
})
