import { describe, expect, it } from 'vitest'
import { useAgentStatusForm } from './useAgentStatusForm'

describe('useAgentStatusForm', () => {
  it('requires a bounded timeout only for pause commands', () => {
    const { form, validate } = useAgentStatusForm()
    form.status = 'pause'
    form.pause_timeout = null

    expect(validate()).toMatchObject({
      success: false,
      errors: { pause_timeout: ['Enter the pause timeout in seconds.'] },
    })

    form.status = 'logout'
    expect(validate()).toMatchObject({ success: true, data: { status: 'logout', pause_timeout: null } })
  })
})
