import { describe, expect, it } from 'vitest'
import { recommendedAgentStatusActions } from './agentStatusActions'

describe('recommendedAgentStatusActions', () => {
  it.each([
    ['logged_out', ['login']],
    ['ready', ['pause', 'logout']],
    ['paused', ['resume', 'logout']],
    ['wrapup', ['end_wrapup', 'resume', 'pause', 'logout']],
    ['connected', ['logout']],
  ])('recommends Kazoo-aware actions for %s', (status, expected) => {
    expect(recommendedAgentStatusActions(status)).toEqual(expected)
  })

  it('falls back safely for an unrecognized live state', () => {
    expect(recommendedAgentStatusActions('future_state')).toEqual(['logout'])
  })
})
