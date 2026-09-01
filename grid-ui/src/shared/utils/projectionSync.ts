export type ProjectionRecord = { last_synced_at?: string | null }

export function latestSynchronizedAt(records: ProjectionRecord[]): string | null {
  let latest: string | null = null
  let latestTime = Number.NEGATIVE_INFINITY

  for (const record of records) {
    if (!record.last_synced_at) continue

    const time = new Date(record.last_synced_at).getTime()
    if (Number.isNaN(time) || time <= latestTime) continue

    latest = record.last_synced_at
    latestTime = time
  }

  return latest
}
