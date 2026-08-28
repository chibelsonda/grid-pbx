export type BlacklistEntry = { id: string; number: string }
export type Blacklist = { id: string; name: string; should_block_anonymous: boolean; is_active: boolean; number_count?: number; numbers?: BlacklistEntry[]; sync_status: string; last_synced_at: string | null }
export type BlacklistInput = { name: string; should_block_anonymous: boolean; is_active: boolean; numbers: string[] }
export type BlacklistSyncRun = { id: string; status: 'queued' | 'running' | 'succeeded' | 'failed'; error_message: string | null }
