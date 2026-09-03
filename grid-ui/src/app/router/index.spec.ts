import { describe, expect, it } from 'vitest'
import router from './index'

describe('authentication routes', () => {
  it('resolves reset links without dropping their token and email query', () => {
    const route = router.resolve('/reset-password?token=opaque-token&email=owner%40example.test')

    expect(route.name).toBe('reset-password')
    expect(route.query).toEqual({ token: 'opaque-token', email: 'owner@example.test' })
    expect(route.meta.guestOnly).toBe(true)
  })

  it('exposes the forgot-password screen to guests', () => {
    const route = router.resolve('/forgot-password')

    expect(route.name).toBe('forgot-password')
    expect(route.meta.guestOnly).toBe(true)
  })
})
