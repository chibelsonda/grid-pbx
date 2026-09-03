import type { AgentStatusInput } from '../types/queue'

export type AgentStatusAction = AgentStatusInput['status']

const fallback: AgentStatusAction[] = ['logout']

const recommendations: Record<string, AgentStatusAction[]> = {
  unknown: ['login', 'logout'],
  logged_out: ['login'],
  logged_in: ['pause', 'logout'],
  ready: ['pause', 'logout'],
  paused: ['resume', 'logout'],
  outbound: ['resume', 'logout'],
  wrapup: ['end_wrapup', 'resume', 'pause', 'logout'],
  connecting: ['logout'],
  connected: ['logout'],
}

/**
 * Switch's callflow helper uses these state-aware recommendations, while the
 * REST endpoint intentionally accepts asynchronous commands beyond this list.
 */
export function recommendedAgentStatusActions(
  status: string | null | undefined,
): AgentStatusAction[] {
  if (!status) return ['login', 'logout']

  return recommendations[status.toLowerCase()] ?? fallback
}
